/*
 * Checkout.
 *
 * Der heikelste Ablauf im Shop, deshalb in drei klar getrennte Schritte
 * zerlegt:
 *
 *   1. begin()    – Warenkorb prüfen, Bestand buchen, Bestellung anlegen,
 *                   Zahlung starten. Ab hier existiert die Bestellung.
 *   2. complete() – Rückkehr vom Zahlungsanbieter verarbeiten.
 *   3. fail()     – Abbruch: Bestand zurückgeben, Bestellung stornieren.
 *
 * Der Bestand wird bewusst schon in Schritt 1 gebucht: sonst könnten zwei
 * Kunden im Zahlungsfenster dasselbe letzte Stück kaufen. Bricht die Zahlung
 * ab, gibt fail() die Ware wieder frei.
 */

import { transaction, update, insert, nowIso } from '../db/index.js';
import * as cartService from './cart.js';
import * as inventory from './inventory.js';
import * as orders from '../models/orders.js';
import * as customers from '../models/customers.js';
import * as discounts from '../models/discounts.js';
import { getProvider, isAvailable, availableProviders } from '../payments/index.js';
import { getGroup } from '../models/settings.js';
import { validateAddress, isEmail, str, bool } from '../lib/validate.js';
import { badRequest, conflict } from '../lib/http.js';
import config from '../config.js';

/** Beträge und Zahlarten für die Kassenseite. */
export function preview(cart) {
  return {
    ...cartService.summarize(cart),
    payment_providers: availableProviders(),
    checkout: getGroup('checkout'),
  };
}

export async function begin(cart, input = {}) {
  const checkoutSettings = getGroup('checkout');
  const summary = cartService.summarize(cart);

  if (summary.lines.length === 0) throw badRequest('Der Warenkorb ist leer');
  if (summary.has_stock_issue) {
    throw conflict('Nicht alle Artikel sind in der gewünschten Menge verfügbar', {
      problems: summary.lines.filter((l) => l.over_stock),
    });
  }
  if (summary.below_minimum) {
    throw badRequest('Der Mindestbestellwert ist nicht erreicht', {
      min_order_total: summary.min_order_total,
    });
  }

  const email = str(input.email, { max: 200 }).toLowerCase();
  if (!isEmail(email)) throw badRequest('Gültige E-Mail-Adresse erforderlich', { fields: ['email'] });

  const shippingAddress = summary.requires_shipping
    ? validateAddress(input.shipping_address)
    : { country: summary.country };
  const billingAddress = input.billing_same === false && input.billing_address
    ? validateAddress(input.billing_address)
    : shippingAddress;

  const phone = str(input.phone, { max: 40 });
  if (checkoutSettings.require_phone && !phone) {
    throw badRequest('Telefonnummer erforderlich', { fields: ['phone'] });
  }
  if (checkoutSettings.terms_required && !bool(input.accept_terms, false)) {
    throw badRequest('Bitte akzeptiere die AGB und die Widerrufsbelehrung', { fields: ['accept_terms'] });
  }

  const providerId = str(input.payment_provider, { max: 40 });
  if (!isAvailable(providerId)) throw badRequest('Zahlart nicht verfügbar', { fields: ['payment_provider'] });
  const provider = getProvider(providerId);

  // Die Adresse kann das Land und damit den Versandpreis ändern – deshalb wird
  // nach der Adressprüfung neu bepreist, nicht vorher.
  const repriced = cartService.updateCart(cart, {
    country: shippingAddress.country || summary.country,
    email,
  });
  if (repriced.discount_error) throw badRequest(repriced.discount_error);

  const order = transaction(() => {
    const customerId = customers.upsertFromOrder({
      email,
      first_name: shippingAddress.first_name,
      last_name: shippingAddress.last_name,
      phone,
      company: shippingAddress.company,
      accepts_marketing: bool(input.accepts_marketing, false),
    });

    const created = orders.createOrder({
      priced: repriced,
      email,
      phone,
      customerId,
      shippingAddress,
      billingAddress,
      shippingMethod: repriced.shipping_rate?.name || '',
      paymentProvider: providerId,
      customerNote: str(input.note, { max: 2000 }),
    });

    // Erst nach dem Anlegen buchen, damit die Bestandsbewegungen die
    // Bestellung referenzieren. War jemand schneller, wirft reserveForOrder und
    // die Transaktion nimmt die eben angelegte Bestellung wieder zurück.
    inventory.reserveForOrder(repriced.lines, { orderId: created.id });

    if (repriced.discount_code) {
      const discount = discounts.findByCode(repriced.discount_code);
      if (discount) discounts.incrementUsage(discount.id);
    }
    customers.recordOrder(customerId, created.total);
    return created;
  });

  // Zahlung starten. Läuft außerhalb der Transaktion, weil ein HTTP-Aufruf zu
  // Stripe/PayPal keine offene SQLite-Transaktion blockieren darf.
  const returnUrl = `${config.baseUrl}/checkout/return/${order.token}`;
  const cancelUrl = `${config.baseUrl}/checkout/cancel/${order.token}`;

  let result;
  try {
    result = await provider.start(order, { lines: order.lines, returnUrl, cancelUrl });
  } catch (error) {
    fail(order.id, `Zahlung konnte nicht gestartet werden: ${error.message}`);
    throw badRequest(`Die Zahlung konnte nicht gestartet werden: ${error.message}`);
  }

  recordPayment(order, providerId, result);

  if (result.reference) {
    update('orders', order.id, { payment_reference: result.reference, updated_at: nowIso() });
  }
  if (result.status === 'paid') {
    orders.markPaid(order.id, { reference: result.reference, provider: providerId });
  } else if (result.message) {
    orders.addEvent(order.id, 'payment', result.message);
  }

  // Der Warenkorb wird erst geleert, wenn die Bestellung wirklich steht.
  cartService.clear(cart);

  return {
    order: orders.getOrder(order.id),
    action: result.action,
    redirect_url: result.url || null,
    status_url: `/order/${order.token}`,
  };
}

/** Rückkehr vom Zahlungsanbieter: Status beim Anbieter erfragen und buchen. */
export async function complete(orderToken) {
  const order = orders.getOrderByToken(orderToken);
  if (!order) throw badRequest('Bestellung nicht gefunden');
  if (order.financial_status === 'paid') return order;

  const provider = getProvider(order.payment_provider);
  if (!provider) return order;

  let result;
  try {
    result = await provider.confirm(order);
  } catch (error) {
    orders.addEvent(order.id, 'payment_error', `Zahlungsprüfung fehlgeschlagen: ${error.message}`);
    return orders.getOrder(order.id);
  }

  if (result.status === 'paid') {
    recordPayment(order, order.payment_provider, { ...result, action: 'complete' });
    return orders.markPaid(order.id, { reference: result.reference });
  }
  if (result.status === 'voided') {
    return fail(order.id, 'Zahlung wurde abgebrochen oder abgelehnt');
  }
  return orders.getOrder(order.id);
}

/**
 * Abbruch: Ware zurück in den Bestand, Bestellung storniert. Bereits gebuchte
 * Zahlungen bleiben unangetastet – die gehören ins Backend, nicht in einen
 * automatischen Ablauf.
 */
export function fail(orderId, reason = 'Zahlung fehlgeschlagen') {
  const order = orders.getOrder(orderId);
  if (!order || order.status === 'cancelled') return order;

  return transaction(() => {
    inventory.releaseForOrder(order.lines, { orderId: order.id, reason: 'cancel' });
    orders.addEvent(order.id, 'payment_failed', reason);
    return orders.cancel(order.id, { reason });
  });
}

function recordPayment(order, providerId, result) {
  insert('payments', {
    order_id: order.id,
    provider: providerId,
    reference: result.reference || '',
    amount: order.total,
    currency: order.currency,
    status: result.status || 'pending',
    raw_json: JSON.stringify(result.raw || {}),
    created_at: nowIso(),
    updated_at: nowIso(),
  });
}

/**
 * Zahlungsereignis aus einem Webhook verarbeiten. Idempotent: doppelte
 * Zustellungen (die es bei jedem Anbieter gibt) verändern nichts.
 */
export function applyPaymentEvent({ orderId, status, reference }) {
  if (!orderId || !status) return null;
  const order = orders.getOrder(orderId);
  if (!order) return null;

  if (status === 'paid' && order.financial_status !== 'paid') {
    return orders.markPaid(order.id, { reference });
  }
  if (status === 'voided' && order.status !== 'cancelled' && order.financial_status !== 'paid') {
    return fail(order.id, 'Zahlung abgelehnt (Webhook)');
  }
  if (status === 'refunded' && order.refunded_total < order.total) {
    return orders.refund(order.id, {
      amount: order.total - order.refunded_total,
      reason: 'Erstattung beim Zahlungsanbieter ausgelöst',
      reference,
    });
  }
  return order;
}

/** Erstattung über den Anbieter, sofern er das kann. */
export async function refundPayment(orderId, amount, { reason = '', userId = null } = {}) {
  const order = orders.getOrder(orderId);
  if (!order) throw badRequest('Bestellung nicht gefunden');

  const provider = getProvider(order.payment_provider);
  let reference = '';

  if (provider?.refund && order.financial_status === 'paid') {
    try {
      const result = await provider.refund(order, amount);
      reference = result.reference || '';
    } catch (error) {
      // Die Erstattung wird trotzdem dokumentiert: der Betreiber hat sie ggf.
      // manuell beim Anbieter ausgelöst und braucht sie in seinen Zahlen.
      orders.addEvent(order.id, 'refund_error', `Erstattung beim Anbieter fehlgeschlagen: ${error.message}`, {}, userId);
    }
  }

  return orders.refund(orderId, { amount, reason, reference, userId });
}
