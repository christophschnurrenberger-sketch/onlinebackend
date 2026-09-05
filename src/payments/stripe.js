/*
 * Stripe – Kartenzahlung über Stripe Checkout.
 *
 * Angebunden direkt an die REST-API mit fetch statt über das stripe-npm-Paket:
 * wir brauchen drei Endpunkte, und das Projekt bleibt dependency-frei.
 *
 * Ablauf: Checkout-Session anlegen -> Kunde zahlt bei Stripe -> Rückkehr auf
 * die Dankeseite. Die Bestellung wird trotzdem erst durch die Bestätigung
 * (Rückkehr oder Webhook) auf "bezahlt" gesetzt – ein Kunde, der die Rückkehr-
 * URL rät, darf keine Zahlung vortäuschen können.
 */

import { createHmac, timingSafeEqual } from 'node:crypto';
import config from '../config.js';

const API = 'https://api.stripe.com/v1';

function form(data, prefix = '', out = new URLSearchParams()) {
  for (const [key, value] of Object.entries(data)) {
    if (value === undefined || value === null) continue;
    const name = prefix ? `${prefix}[${key}]` : key;
    if (Array.isArray(value)) {
      value.forEach((item, index) => {
        if (item !== null && typeof item === 'object') form(item, `${name}[${index}]`, out);
        else out.append(`${name}[${index}]`, String(item));
      });
    } else if (typeof value === 'object') {
      form(value, name, out);
    } else {
      out.append(name, String(value));
    }
  }
  return out;
}

async function call(path, { method = 'POST', body = null } = {}) {
  const key = config.payments.stripe.secretKey;
  if (!key) throw new Error('STRIPE_SECRET_KEY fehlt');

  const response = await fetch(`${API}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${key}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: body ? form(body).toString() : undefined,
  });

  const json = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(json?.error?.message || `Stripe-Fehler ${response.status}`);
  }
  return json;
}

export default {
  id: 'stripe',
  label: 'Kredit- / Debitkarte',
  redirects: true,
  available: () => Boolean(config.payments.stripe.secretKey),

  async start(order, { lines = [], returnUrl, cancelUrl }) {
    const session = await call('/checkout/sessions', {
      body: {
        mode: 'payment',
        client_reference_id: String(order.id),
        customer_email: order.email || undefined,
        success_url: returnUrl,
        cancel_url: cancelUrl,
        // Wir schicken eine einzige Position mit dem Gesamtbetrag: Rabatte,
        // Versand und Steuern sind bereits verrechnet, und so kann die Summe
        // bei Stripe nicht um Rundungscent von unserer abweichen.
        line_items: [
          {
            quantity: 1,
            price_data: {
              currency: order.currency.toLowerCase(),
              unit_amount: order.total,
              product_data: {
                name: `Bestellung ${order.number}`,
                description: lines
                  .map((l) => `${l.quantity}× ${l.title}`)
                  .join(', ')
                  .slice(0, 500) || undefined,
              },
            },
          },
        ],
        metadata: { order_id: String(order.id), order_number: String(order.number) },
      },
    });

    return { action: 'redirect', url: session.url, reference: session.id, status: 'pending' };
  },

  /** Prüft bei Stripe nach, ob die Session wirklich bezahlt wurde. */
  async confirm(order) {
    if (!order.payment_reference) return { status: 'pending' };
    const session = await call(`/checkout/sessions/${order.payment_reference}`, { method: 'GET' });
    const paid = session.payment_status === 'paid';
    return {
      status: paid ? 'paid' : session.status === 'expired' ? 'voided' : 'pending',
      reference: session.payment_intent || session.id,
      raw: session,
    };
  },

  async refund(order, amount) {
    const reference = order.payment_reference;
    if (!reference) throw new Error('Keine Stripe-Referenz an der Bestellung');
    // Kommt die Referenz noch von der Session, holen wir den PaymentIntent nach.
    const paymentIntent = reference.startsWith('cs_')
      ? (await call(`/checkout/sessions/${reference}`, { method: 'GET' })).payment_intent
      : reference;
    const refund = await call('/refunds', {
      body: { payment_intent: paymentIntent, amount },
    });
    return { ok: refund.status !== 'failed', reference: refund.id, amount: refund.amount };
  },

  /**
   * Verifiziert die Stripe-Signatur. Ohne diese Prüfung könnte jeder mit einem
   * POST auf die Webhook-URL Bestellungen als bezahlt markieren.
   */
  verifyWebhook(rawBody, signatureHeader) {
    const secret = config.payments.stripe.webhookSecret;
    if (!secret) return { ok: false, reason: 'STRIPE_WEBHOOK_SECRET fehlt' };
    if (!signatureHeader) return { ok: false, reason: 'Signatur fehlt' };

    const parts = Object.fromEntries(
      String(signatureHeader)
        .split(',')
        .map((p) => p.split('=').map((s) => s.trim())),
    );
    const timestamp = parts.t;
    const provided = parts.v1;
    if (!timestamp || !provided) return { ok: false, reason: 'Signatur unvollständig' };

    // Replays älter als fünf Minuten ablehnen.
    if (Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) {
      return { ok: false, reason: 'Zeitstempel zu alt' };
    }

    const expected = createHmac('sha256', secret)
      .update(`${timestamp}.${rawBody.toString('utf8')}`)
      .digest('hex');
    const a = Buffer.from(expected);
    const b = Buffer.from(provided);
    if (a.length !== b.length || !timingSafeEqual(a, b)) {
      return { ok: false, reason: 'Signatur ungültig' };
    }
    return { ok: true };
  },

  /** Übersetzt ein Stripe-Ereignis in unsere Begriffe. */
  parseEvent(event) {
    const object = event?.data?.object || {};
    const orderId = Number(object.metadata?.order_id || object.client_reference_id || 0);

    switch (event?.type) {
      case 'checkout.session.completed':
      case 'checkout.session.async_payment_succeeded':
        return {
          eventId: event.id,
          orderId,
          status: object.payment_status === 'paid' ? 'paid' : 'pending',
          reference: object.payment_intent || object.id,
        };
      case 'checkout.session.expired':
      case 'checkout.session.async_payment_failed':
        return { eventId: event.id, orderId, status: 'voided', reference: object.id };
      case 'charge.refunded':
        return { eventId: event.id, orderId, status: 'refunded', reference: object.payment_intent };
      default:
        return { eventId: event?.id, orderId, status: null };
    }
  },
};
