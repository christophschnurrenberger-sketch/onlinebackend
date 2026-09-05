/*
 * Bestellungen.
 *
 * Eine Bestellung ist ein Dokument, keine Sicht auf den Katalog: Titel, SKU und
 * Preis werden in die Positionen kopiert. Ändert der Betreiber später Preis
 * oder Produktnamen, bleibt die Bestellung so, wie der Kunde sie abgeschlossen
 * hat – das ist handelsrechtlich nötig und macht Belege reproduzierbar.
 */

import { randomBytes } from 'node:crypto';
import { all, get, run, insert, update, parseJson, transaction, nowIso } from '../db/index.js';
import { getGroup } from './settings.js';
import { str, int, oneOf } from '../lib/validate.js';
import { notFound, badRequest } from '../lib/http.js';

export const FINANCIAL_STATUSES = [
  'pending', 'authorized', 'paid', 'partially_refunded', 'refunded', 'voided',
];
export const FULFILLMENT_STATUSES = ['unfulfilled', 'partial', 'fulfilled'];
export const ORDER_STATUSES = ['open', 'archived', 'cancelled'];

// --- Lesen ------------------------------------------------------------------

export function listOrders({
  search = '',
  status = '',
  financialStatus = '',
  fulfillmentStatus = '',
  customerId = 0,
  from = '',
  to = '',
  limit = 50,
  offset = 0,
  sort = 'created-desc',
} = {}) {
  const where = [];
  const params = [];

  if (search) {
    const like = `%${search}%`;
    where.push(`(o.email LIKE ? OR CAST(o.number AS TEXT) LIKE ?
                 OR o.shipping_address LIKE ? OR o.discount_code LIKE ?)`);
    params.push(like, like, like, like);
  }
  if (status) { where.push('o.status = ?'); params.push(status); }
  if (financialStatus) { where.push('o.financial_status = ?'); params.push(financialStatus); }
  if (fulfillmentStatus) { where.push('o.fulfillment_status = ?'); params.push(fulfillmentStatus); }
  if (customerId) { where.push('o.customer_id = ?'); params.push(customerId); }
  if (from) { where.push('o.created_at >= ?'); params.push(from); }
  if (to) { where.push('o.created_at <= ?'); params.push(to); }

  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const orderBy = {
    'created-desc': 'o.created_at DESC',
    'created-asc': 'o.created_at ASC',
    'total-desc': 'o.total DESC',
    'number-desc': 'o.number DESC',
  }[sort] || 'o.created_at DESC';

  const items = all(
    `SELECT o.*,
            (SELECT COUNT(*) FROM order_lines l WHERE l.order_id = o.id) AS line_count,
            (SELECT SUM(quantity) FROM order_lines l WHERE l.order_id = o.id) AS item_count,
            c.first_name AS customer_first_name, c.last_name AS customer_last_name
       FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
       ${clause} ORDER BY ${orderBy} LIMIT ? OFFSET ?`,
    [...params, int(limit, { min: 1, max: 250, fallback: 50 }), int(offset, { min: 0, fallback: 0 })],
  );

  const totals = get(
    `SELECT COUNT(*) AS n, COALESCE(SUM(o.total),0) AS sum_total FROM orders o ${clause}`,
    params,
  );

  return {
    items: items.map((o) => ({
      ...o,
      shipping_address: parseJson(o.shipping_address, {}),
      billing_address: parseJson(o.billing_address, {}),
    })),
    total: totals?.n ?? 0,
    sum_total: totals?.sum_total ?? 0,
  };
}

export function getOrder(id) {
  const order = get('SELECT * FROM orders WHERE id = ?', [id]);
  return order ? hydrate(order) : null;
}

export function getOrderByToken(token) {
  const order = get('SELECT * FROM orders WHERE token = ?', [token]);
  return order ? hydrate(order) : null;
}

function hydrate(order) {
  return {
    ...order,
    shipping_address: parseJson(order.shipping_address, {}),
    billing_address: parseJson(order.billing_address, {}),
    lines: all('SELECT * FROM order_lines WHERE order_id = ? ORDER BY id', [order.id]),
    events: all(
      `SELECT e.*, u.name AS user_name FROM order_events e
         LEFT JOIN users u ON u.id = e.user_id
        WHERE e.order_id = ? ORDER BY e.created_at DESC, e.id DESC`,
      [order.id],
    ).map((e) => ({ ...e, data: parseJson(e.data_json, {}) })),
    fulfillments: all('SELECT * FROM fulfillments WHERE order_id = ? ORDER BY created_at', [order.id])
      .map((f) => ({ ...f, lines: parseJson(f.lines_json, []) })),
    refunds: all('SELECT * FROM refunds WHERE order_id = ? ORDER BY created_at', [order.id]),
    payments: all('SELECT * FROM payments WHERE order_id = ? ORDER BY created_at', [order.id]),
  };
}

// --- Anlegen ----------------------------------------------------------------

/** Fortlaufende Bestellnummer; Startwert kommt aus den Checkout-Einstellungen. */
function nextOrderNumber() {
  const start = getGroup('checkout').order_number_start || 1000;
  const max = get('SELECT MAX(number) AS n FROM orders')?.n;
  return Math.max(start, (max || 0) + 1);
}

/**
 * Schreibt eine Bestellung aus einem bepreisten Warenkorb.
 * Der Aufrufer (Checkout) hat Bestand und Zahlung bereits geklärt.
 */
export function createOrder({
  priced,
  email,
  phone = '',
  customerId = null,
  shippingAddress = {},
  billingAddress = {},
  shippingMethod = '',
  paymentProvider = '',
  customerNote = '',
  financialStatus = 'pending',
}) {
  return transaction(() => {
    const now = nowIso();
    const orderId = insert('orders', {
      number: nextOrderNumber(),
      token: randomBytes(20).toString('base64url'),
      customer_id: customerId,
      email: String(email || '').toLowerCase(),
      phone: str(phone, { max: 40 }),
      status: 'open',
      financial_status: oneOf(financialStatus, FINANCIAL_STATUSES, 'pending'),
      fulfillment_status: 'unfulfilled',
      currency: priced.currency,
      subtotal: priced.subtotal,
      discount_total: priced.discount_total,
      shipping_total: priced.shipping_total,
      tax_total: priced.tax_total,
      total: priced.total,
      discount_code: priced.discount_code || '',
      shipping_method: shippingMethod || priced.shipping_rate?.name || '',
      shipping_address: JSON.stringify(shippingAddress),
      billing_address: JSON.stringify(billingAddress),
      payment_provider: paymentProvider,
      customer_note: str(customerNote, { max: 2000 }),
      created_at: now,
      updated_at: now,
    });

    for (const line of priced.lines) {
      insert('order_lines', {
        order_id: orderId,
        variant_id: line.variant_id,
        product_id: line.product_id,
        title: line.title,
        variant_title: line.variant_title,
        sku: line.sku,
        image_url: line.image_url,
        quantity: line.quantity,
        price: line.price,
        discount: line.discount,
        total: line.total,
        tax_rate_bp: line.tax_rate_bp,
        tax_amount: line.tax_amount,
        requires_shipping: line.requires_shipping ? 1 : 0,
      });
    }

    addEvent(orderId, 'created', `Bestellung eingegangen (${paymentProvider || 'ohne Zahlart'})`);
    return getOrder(orderId);
  });
}

export function addEvent(orderId, type, message, data = {}, userId = null) {
  return insert('order_events', {
    order_id: orderId,
    type,
    message: str(message, { max: 1000 }),
    data_json: JSON.stringify(data),
    user_id: userId,
    created_at: nowIso(),
  });
}

// --- Status ändern ----------------------------------------------------------

export function setFinancialStatus(orderId, status, { message = '', userId = null, reference = '' } = {}) {
  const next = oneOf(status, FINANCIAL_STATUSES, 'pending');
  const patch = { financial_status: next, updated_at: nowIso() };
  if (next === 'paid') patch.paid_at = nowIso();
  if (reference) patch.payment_reference = reference;
  update('orders', orderId, patch);
  addEvent(orderId, 'financial_status', message || `Zahlungsstatus: ${next}`, { status: next }, userId);
  return getOrder(orderId);
}

export function markPaid(orderId, { reference = '', provider = '', userId = null } = {}) {
  const order = get('SELECT * FROM orders WHERE id = ?', [orderId]);
  if (!order) throw notFound('Bestellung nicht gefunden');
  if (order.financial_status === 'paid') return getOrder(orderId);

  update('orders', orderId, {
    financial_status: 'paid',
    paid_at: nowIso(),
    payment_reference: reference || order.payment_reference,
    payment_provider: provider || order.payment_provider,
    updated_at: nowIso(),
  });
  addEvent(orderId, 'paid', 'Zahlung eingegangen', { reference }, userId);
  return getOrder(orderId);
}

export function setNote(orderId, note, userId = null) {
  update('orders', orderId, { note: str(note, { max: 4000 }), updated_at: nowIso() });
  addEvent(orderId, 'note', 'Interne Notiz aktualisiert', {}, userId);
  return getOrder(orderId);
}

export function archive(orderId, archived = true, userId = null) {
  update('orders', orderId, { status: archived ? 'archived' : 'open', updated_at: nowIso() });
  addEvent(orderId, 'status', archived ? 'Bestellung archiviert' : 'Bestellung wieder geöffnet', {}, userId);
  return getOrder(orderId);
}

// --- Versand ----------------------------------------------------------------

/**
 * Erzeugt eine Sendung für die übergebenen Positionen und aktualisiert daraus
 * den Versandstatus der Bestellung. `lines` = [{ line_id, quantity }];
 * ohne Angabe wird alles Offene versendet.
 */
export function fulfill(orderId, { lines = null, carrier = '', trackingNumber = '', trackingUrl = '', userId = null } = {}) {
  return transaction(() => {
    const order = getOrder(orderId);
    if (!order) throw notFound('Bestellung nicht gefunden');
    if (order.status === 'cancelled') throw badRequest('Stornierte Bestellungen können nicht versendet werden');

    const open = order.lines.filter((l) => l.requires_shipping && l.fulfilled_quantity < l.quantity);
    const selection = (lines && lines.length > 0
      ? lines
          .map((entry) => {
            const line = order.lines.find((l) => l.id === int(entry.line_id));
            if (!line) return null;
            const remaining = line.quantity - line.fulfilled_quantity;
            const quantity = Math.min(remaining, int(entry.quantity, { min: 0, fallback: remaining }));
            return quantity > 0 ? { line, quantity } : null;
          })
          .filter(Boolean)
      : open.map((line) => ({ line, quantity: line.quantity - line.fulfilled_quantity })));

    if (selection.length === 0) throw badRequest('Keine offenen Positionen zum Versenden');

    for (const { line, quantity } of selection) {
      run('UPDATE order_lines SET fulfilled_quantity = fulfilled_quantity + ? WHERE id = ?', [
        quantity,
        line.id,
      ]);
    }

    insert('fulfillments', {
      order_id: orderId,
      carrier: str(carrier, { max: 80 }),
      tracking_number: str(trackingNumber, { max: 120 }),
      tracking_url: str(trackingUrl, { max: 500 }),
      lines_json: JSON.stringify(selection.map((s) => ({ line_id: s.line.id, quantity: s.quantity }))),
      created_at: nowIso(),
    });

    update('orders', orderId, { fulfillment_status: computeFulfillmentStatus(orderId), updated_at: nowIso() });
    addEvent(
      orderId,
      'fulfilled',
      trackingNumber ? `Versendet (${carrier} ${trackingNumber})` : 'Versendet',
      { carrier, trackingNumber },
      userId,
    );
    return getOrder(orderId);
  });
}

function computeFulfillmentStatus(orderId) {
  const rows = all(
    'SELECT quantity, fulfilled_quantity, requires_shipping FROM order_lines WHERE order_id = ?',
    [orderId],
  ).filter((l) => l.requires_shipping);
  if (rows.length === 0) return 'fulfilled';
  const done = rows.every((l) => l.fulfilled_quantity >= l.quantity);
  const some = rows.some((l) => l.fulfilled_quantity > 0);
  return done ? 'fulfilled' : some ? 'partial' : 'unfulfilled';
}

// --- Erstattung & Storno ----------------------------------------------------

export function refund(orderId, { amount, reason = '', restock = false, reference = '', userId = null }) {
  return transaction(() => {
    const order = getOrder(orderId);
    if (!order) throw notFound('Bestellung nicht gefunden');

    const refundable = order.total - order.refunded_total;
    const value = int(amount, { min: 1, fallback: 0 });
    if (value <= 0) throw badRequest('Betrag muss größer als 0 sein');
    if (value > refundable) {
      throw badRequest(`Höchstens ${(refundable / 100).toFixed(2)} erstattbar`, { refundable });
    }

    insert('refunds', {
      order_id: orderId,
      amount: value,
      reason: str(reason, { max: 500 }),
      restock: restock ? 1 : 0,
      reference: str(reference, { max: 200 }),
      user_id: userId,
      created_at: nowIso(),
    });

    const refunded = order.refunded_total + value;
    update('orders', orderId, {
      refunded_total: refunded,
      financial_status: refunded >= order.total ? 'refunded' : 'partially_refunded',
      updated_at: nowIso(),
    });
    addEvent(orderId, 'refund', `Erstattung über ${(value / 100).toFixed(2)} ${order.currency}`, { amount: value, reason }, userId);
    return getOrder(orderId);
  });
}

export function cancel(orderId, { reason = '', userId = null } = {}) {
  const order = getOrder(orderId);
  if (!order) throw notFound('Bestellung nicht gefunden');
  if (order.status === 'cancelled') return order;

  update('orders', orderId, {
    status: 'cancelled',
    cancelled_at: nowIso(),
    financial_status: order.financial_status === 'paid' ? order.financial_status : 'voided',
    updated_at: nowIso(),
  });
  addEvent(orderId, 'cancelled', reason ? `Storniert: ${reason}` : 'Storniert', { reason }, userId);
  return getOrder(orderId);
}

// --- Kennzahlen -------------------------------------------------------------

/** Zahlen für die Startseite des Backends. */
export function stats({ days = 30 } = {}) {
  const since = new Date(Date.now() - days * 86400_000).toISOString();
  const paidClause = "status != 'cancelled'";

  const totals = get(
    `SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue,
            COALESCE(SUM(total - refunded_total),0) AS net_revenue
       FROM orders WHERE created_at >= ? AND ${paidClause}`,
    [since],
  );

  const previous = get(
    `SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
       FROM orders WHERE created_at >= ? AND created_at < ? AND ${paidClause}`,
    [new Date(Date.now() - days * 2 * 86400_000).toISOString(), since],
  );

  return {
    days,
    orders: totals?.orders ?? 0,
    revenue: totals?.revenue ?? 0,
    net_revenue: totals?.net_revenue ?? 0,
    average_order_value: totals?.orders ? Math.round(totals.revenue / totals.orders) : 0,
    previous_orders: previous?.orders ?? 0,
    previous_revenue: previous?.revenue ?? 0,
    open_orders: get(`SELECT COUNT(*) AS n FROM orders WHERE status = 'open'`)?.n ?? 0,
    unfulfilled: get(
      `SELECT COUNT(*) AS n FROM orders WHERE status = 'open' AND fulfillment_status != 'fulfilled'`,
    )?.n ?? 0,
    unpaid: get(
      `SELECT COUNT(*) AS n FROM orders WHERE status = 'open' AND financial_status = 'pending'`,
    )?.n ?? 0,
    daily: all(
      `SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue
         FROM orders WHERE created_at >= ? AND ${paidClause}
        GROUP BY day ORDER BY day`,
      [since],
    ),
    top_products: all(
      `SELECT l.product_id, l.title, SUM(l.quantity) AS quantity, SUM(l.total) AS revenue
         FROM order_lines l JOIN orders o ON o.id = l.order_id
        WHERE o.created_at >= ? AND o.status != 'cancelled'
        GROUP BY l.product_id, l.title ORDER BY revenue DESC LIMIT 8`,
      [since],
    ),
  };
}
