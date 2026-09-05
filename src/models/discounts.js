/*
 * Rabattcodes.
 *
 * Drei Typen decken praktisch alles ab, was ein Shop braucht: Prozent, fester
 * Betrag, Gratisversand. Die Gültigkeitsprüfung liegt hier und nicht im
 * Checkout, damit Storefront-Vorschau und Bestellabschluss dieselbe Antwort
 * geben.
 */

import { all, get, run, insert, update, remove, parseJson, nowIso } from '../db/index.js';
import { str, int, bool, oneOf } from '../lib/validate.js';
import { parseMoney } from '../lib/money.js';
import { notFound, badRequest } from '../lib/http.js';

export const TYPES = ['percentage', 'fixed', 'free_shipping'];
export const APPLIES_TO = ['all', 'collection', 'product'];

export const listDiscounts = ({ search = '', active = null } = {}) => {
  const where = [];
  const params = [];
  if (search) {
    where.push('(code LIKE ? OR title LIKE ?)');
    params.push(`%${search}%`, `%${search}%`);
  }
  if (active !== null) {
    where.push('active = ?');
    params.push(active ? 1 : 0);
  }
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  return all(`SELECT * FROM discounts ${clause} ORDER BY created_at DESC`, params).map(decorate);
};

const decorate = (row) => ({ ...row, target_ids: parseJson(row.target_ids_json, []) });

export const getDiscount = (id) => {
  const row = get('SELECT * FROM discounts WHERE id = ?', [id]);
  return row ? decorate(row) : null;
};

export const findByCode = (code) => {
  const row = get('SELECT * FROM discounts WHERE code = ? COLLATE NOCASE', [
    String(code || '').trim(),
  ]);
  return row ? decorate(row) : null;
};

function normalize(input, existing = null) {
  const code = str(input.code ?? existing?.code, { max: 60 }).toUpperCase().replace(/\s+/g, '');
  if (!code) throw badRequest('Rabattcode ist erforderlich', { fields: ['code'] });

  const type = oneOf(input.type ?? existing?.type, TYPES, 'percentage');
  // Prozent liegt als Hundertstel-Prozent vor (1250 = 12,5 %), damit auch
  // krumme Sätze exakt gespeichert werden.
  let value = 0;
  if (type === 'percentage') {
    const raw = input.value ?? existing?.value ?? 0;
    value = typeof raw === 'number' && raw > 100
      ? Math.round(raw)
      : Math.round(Number.parseFloat(String(raw).replace(',', '.')) * 100) || 0;
    value = Math.min(10000, Math.max(0, value));
  } else if (type === 'fixed') {
    value = typeof input.value === 'number' ? Math.round(input.value) : parseMoney(input.value ?? existing?.value);
  }

  return {
    code,
    title: str(input.title ?? existing?.title, { max: 200 }),
    type,
    value,
    applies_to: oneOf(input.applies_to ?? existing?.applies_to, APPLIES_TO, 'all'),
    target_ids_json: JSON.stringify(
      (Array.isArray(input.target_ids) ? input.target_ids : parseJson(existing?.target_ids_json, []))
        .map((id) => int(id, { fallback: 0 }))
        .filter(Boolean)
        .slice(0, 500),
    ),
    min_subtotal:
      typeof input.min_subtotal === 'number'
        ? Math.max(0, Math.round(input.min_subtotal))
        : parseMoney(input.min_subtotal ?? existing?.min_subtotal),
    usage_limit:
      input.usage_limit === '' || input.usage_limit === null || input.usage_limit === undefined
        ? existing?.usage_limit ?? null
        : int(input.usage_limit, { min: 0, fallback: 0 }) || null,
    once_per_customer: bool(input.once_per_customer ?? existing?.once_per_customer, false) ? 1 : 0,
    starts_at: str(input.starts_at ?? existing?.starts_at, { max: 40 }) || null,
    ends_at: str(input.ends_at ?? existing?.ends_at, { max: 40 }) || null,
    active: bool(input.active ?? existing?.active, true) ? 1 : 0,
    updated_at: nowIso(),
  };
}

export function createDiscount(input = {}) {
  const data = normalize(input);
  if (findByCode(data.code)) throw badRequest('Dieser Code existiert bereits', { fields: ['code'] });
  const id = insert('discounts', { ...data, created_at: nowIso() });
  return getDiscount(id);
}

export function updateDiscount(id, input = {}) {
  const existing = get('SELECT * FROM discounts WHERE id = ?', [id]);
  if (!existing) throw notFound('Rabatt nicht gefunden');
  const data = normalize(input, existing);
  const clash = findByCode(data.code);
  if (clash && clash.id !== id) throw badRequest('Dieser Code existiert bereits', { fields: ['code'] });
  update('discounts', id, data);
  return getDiscount(id);
}

export const deleteDiscount = (id) => remove('discounts', id);

export const incrementUsage = (id) =>
  run('UPDATE discounts SET used_count = used_count + 1, updated_at = ? WHERE id = ?', [nowIso(), id]);

/**
 * Prüft, ob ein Code auf diesen Warenkorb angewendet werden darf.
 * Gibt { ok, discount, reason } zurück – der Grund ist für den Kunden lesbar.
 */
export function validateForCart(code, { subtotal = 0, customerId = null, email = '' } = {}) {
  const discount = findByCode(code);
  if (!discount) return { ok: false, reason: 'Dieser Rabattcode ist ungültig.' };
  if (!discount.active) return { ok: false, reason: 'Dieser Rabattcode ist nicht mehr aktiv.' };

  const now = Date.now();
  if (discount.starts_at && new Date(discount.starts_at).getTime() > now) {
    return { ok: false, reason: 'Dieser Rabattcode ist noch nicht gültig.' };
  }
  if (discount.ends_at && new Date(discount.ends_at).getTime() < now) {
    return { ok: false, reason: 'Dieser Rabattcode ist abgelaufen.' };
  }
  if (discount.usage_limit !== null && discount.used_count >= discount.usage_limit) {
    return { ok: false, reason: 'Dieser Rabattcode wurde bereits vollständig eingelöst.' };
  }
  if (discount.min_subtotal > 0 && subtotal < discount.min_subtotal) {
    return {
      ok: false,
      reason: 'Der Mindestbestellwert für diesen Rabattcode ist nicht erreicht.',
      minSubtotal: discount.min_subtotal,
    };
  }
  if (discount.once_per_customer) {
    const identifier = customerId
      ? { sql: 'customer_id = ?', param: customerId }
      : { sql: 'email = ?', param: String(email || '').toLowerCase() };
    if (identifier.param) {
      const used = get(
        `SELECT id FROM orders WHERE ${identifier.sql} AND discount_code = ? COLLATE NOCASE
           AND status != 'cancelled' LIMIT 1`,
        [identifier.param, discount.code],
      );
      if (used) return { ok: false, reason: 'Dieser Rabattcode wurde bereits eingelöst.' };
    }
  }

  return { ok: true, discount };
}
