/*
 * Kunden und ihre Adressen.
 *
 * Die Kennzahlen orders_count/total_spent werden bei jeder Bestellung
 * fortgeschrieben statt bei jedem Listenaufruf berechnet – die Kundenliste ist
 * die Seite, die im Backend am häufigsten geöffnet wird.
 */

import { all, get, run, insert, update, remove, nowIso } from '../db/index.js';
import { str, int, bool, isEmail } from '../lib/validate.js';
import { validateAddress } from '../lib/validate.js';
import { notFound, badRequest } from '../lib/http.js';

export function listCustomers({ search = '', limit = 50, offset = 0, sort = 'created-desc' } = {}) {
  const where = [];
  const params = [];
  if (search) {
    where.push('(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR company LIKE ?)');
    const like = `%${search}%`;
    params.push(like, like, like, like);
  }
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const orderBy = {
    'created-desc': 'created_at DESC',
    'spent-desc': 'total_spent DESC',
    'orders-desc': 'orders_count DESC',
    'name-asc': 'last_name COLLATE NOCASE ASC, first_name COLLATE NOCASE ASC',
  }[sort] || 'created_at DESC';

  return {
    items: all(`SELECT * FROM customers ${clause} ORDER BY ${orderBy} LIMIT ? OFFSET ?`, [
      ...params,
      int(limit, { min: 1, max: 250, fallback: 50 }),
      int(offset, { min: 0, fallback: 0 }),
    ]),
    total: get(`SELECT COUNT(*) AS n FROM customers ${clause}`, params)?.n ?? 0,
  };
}

export function getCustomer(id) {
  const customer = get('SELECT * FROM customers WHERE id = ?', [id]);
  if (!customer) return null;
  return {
    ...customer,
    addresses: all('SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id', [id]),
    orders: all(
      `SELECT id, number, created_at, total, financial_status, fulfillment_status, currency
         FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 50`,
      [id],
    ),
  };
}

export const findByEmail = (email) =>
  get('SELECT * FROM customers WHERE email = ?', [String(email || '').trim().toLowerCase()]);

function normalize(input, existing = null) {
  const email = str(input.email ?? existing?.email, { max: 200 }).toLowerCase();
  if (!isEmail(email)) throw badRequest('Gültige E-Mail-Adresse erforderlich', { fields: ['email'] });
  return {
    email,
    first_name: str(input.first_name ?? existing?.first_name, { max: 80 }),
    last_name: str(input.last_name ?? existing?.last_name, { max: 80 }),
    phone: str(input.phone ?? existing?.phone, { max: 40 }),
    company: str(input.company ?? existing?.company, { max: 120 }),
    accepts_marketing: bool(input.accepts_marketing ?? existing?.accepts_marketing, false) ? 1 : 0,
    note: str(input.note ?? existing?.note, { max: 2000 }),
    tags: Array.isArray(input.tags)
      ? input.tags.map((t) => str(t, { max: 50 })).filter(Boolean).join(', ')
      : str(input.tags ?? existing?.tags, { max: 500 }),
    updated_at: nowIso(),
  };
}

export function createCustomer(input = {}) {
  const data = normalize(input);
  const existing = findByEmail(data.email);
  if (existing) throw badRequest('Diese E-Mail-Adresse ist bereits vergeben', { fields: ['email'] });
  const id = insert('customers', { ...data, created_at: nowIso() });
  if (input.address) addAddress(id, input.address);
  return getCustomer(id);
}

export function updateCustomer(id, input = {}) {
  const existing = get('SELECT * FROM customers WHERE id = ?', [id]);
  if (!existing) throw notFound('Kunde nicht gefunden');
  const data = normalize(input, existing);
  const clash = findByEmail(data.email);
  if (clash && clash.id !== id) {
    throw badRequest('Diese E-Mail-Adresse ist bereits vergeben', { fields: ['email'] });
  }
  update('customers', id, data);
  return getCustomer(id);
}

export const deleteCustomer = (id) => remove('customers', id);

/** Findet oder erstellt den Kunden zu einer Bestellung (Gast-Checkout). */
export function upsertFromOrder({ email, first_name, last_name, phone, company, accepts_marketing }) {
  const normalized = String(email || '').trim().toLowerCase();
  if (!isEmail(normalized)) return null;

  const existing = findByEmail(normalized);
  if (existing) {
    // Vorhandene Kundendaten nur ergänzen, nie mit Leerwerten überschreiben.
    const patch = { updated_at: nowIso() };
    if (!existing.first_name && first_name) patch.first_name = str(first_name, { max: 80 });
    if (!existing.last_name && last_name) patch.last_name = str(last_name, { max: 80 });
    if (!existing.phone && phone) patch.phone = str(phone, { max: 40 });
    if (!existing.company && company) patch.company = str(company, { max: 120 });
    if (accepts_marketing) patch.accepts_marketing = 1;
    update('customers', existing.id, patch);
    return existing.id;
  }

  return insert('customers', {
    email: normalized,
    first_name: str(first_name, { max: 80 }),
    last_name: str(last_name, { max: 80 }),
    phone: str(phone, { max: 40 }),
    company: str(company, { max: 120 }),
    accepts_marketing: accepts_marketing ? 1 : 0,
    created_at: nowIso(),
    updated_at: nowIso(),
  });
}

export function recordOrder(customerId, total) {
  if (!customerId) return;
  run(
    `UPDATE customers
        SET orders_count = orders_count + 1,
            total_spent = total_spent + ?,
            updated_at = ?
      WHERE id = ?`,
    [total, nowIso(), customerId],
  );
}

export function addAddress(customerId, input) {
  const address = validateAddress(input, { requireName: false });
  if (input.is_default) {
    run('UPDATE addresses SET is_default = 0 WHERE customer_id = ?', [customerId]);
  }
  return insert('addresses', {
    ...address,
    customer_id: customerId,
    is_default: input.is_default ? 1 : 0,
  });
}

export function deleteAddress(customerId, addressId) {
  return run('DELETE FROM addresses WHERE id = ? AND customer_id = ?', [addressId, customerId]).changes;
}
