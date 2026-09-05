/*
 * Versandzonen, Versandarten und Steuersätze.
 *
 * Eine Zone bündelt Länder, ihre Tarife gelten für alle davon. Eine Zone ohne
 * Länderliste ist die Auffangzone ("Rest der Welt") – so bleibt der Shop auch
 * lieferfähig, wenn der Betreiber ein Land vergisst.
 */

import { all, get, insert, update, remove, parseJson, transaction } from '../db/index.js';
import { str, int, bool } from '../lib/validate.js';
import { parseMoney } from '../lib/money.js';
import { notFound } from '../lib/http.js';

// --- Zonen & Tarife ---------------------------------------------------------

export function listZones() {
  return all('SELECT * FROM shipping_zones ORDER BY position, id').map((zone) => ({
    ...zone,
    countries: parseJson(zone.countries_json, []),
    rates: all('SELECT * FROM shipping_rates WHERE zone_id = ? ORDER BY position, price', [zone.id]),
  }));
}

export function createZone(input = {}) {
  const id = insert('shipping_zones', {
    name: str(input.name, { max: 120, fallback: 'Zone' }) || 'Zone',
    countries_json: JSON.stringify(normalizeCountries(input.countries)),
    position: int(input.position, { min: 0, fallback: 0 }),
  });
  (Array.isArray(input.rates) ? input.rates : []).forEach((rate) => createRate(id, rate));
  return id;
}

export function updateZone(id, input = {}) {
  const existing = get('SELECT * FROM shipping_zones WHERE id = ?', [id]);
  if (!existing) throw notFound('Versandzone nicht gefunden');
  return transaction(() => {
    update('shipping_zones', id, {
      name: str(input.name ?? existing.name, { max: 120 }),
      countries_json: JSON.stringify(
        input.countries !== undefined
          ? normalizeCountries(input.countries)
          : parseJson(existing.countries_json, []),
      ),
      position: int(input.position ?? existing.position, { min: 0, fallback: 0 }),
    });
    if (Array.isArray(input.rates)) {
      remove('shipping_rates', id, 'zone_id');
      input.rates.forEach((rate) => createRate(id, rate));
    }
    return id;
  });
}

export const deleteZone = (id) => remove('shipping_zones', id);

function normalizeCountries(countries) {
  return (Array.isArray(countries) ? countries : String(countries || '').split(','))
    .map((c) => str(c, { max: 2 }).toUpperCase())
    .filter((c) => /^[A-Z]{2}$/.test(c))
    .slice(0, 250);
}

export function createRate(zoneId, input = {}) {
  const optionalMoney = (value) =>
    value === '' || value === null || value === undefined
      ? null
      : typeof value === 'number' ? Math.max(0, Math.round(value)) : parseMoney(value);

  return insert('shipping_rates', {
    zone_id: zoneId,
    name: str(input.name, { max: 120, fallback: 'Versand' }) || 'Versand',
    description: str(input.description, { max: 250 }),
    price: typeof input.price === 'number' ? Math.max(0, Math.round(input.price)) : parseMoney(input.price),
    min_subtotal: optionalMoney(input.min_subtotal),
    max_subtotal: optionalMoney(input.max_subtotal),
    free_over: optionalMoney(input.free_over),
    delivery_time: str(input.delivery_time, { max: 80 }),
    position: int(input.position, { min: 0, fallback: 0 }),
  });
}

export const getRate = (id) => get('SELECT * FROM shipping_rates WHERE id = ?', [id]);

/**
 * Die für ein Land und einen Warenwert wählbaren Versandarten, jeweils mit dem
 * effektiven Preis (Versandkostenfreiheit ist hier schon eingerechnet).
 */
export function ratesFor(country, subtotal, requiresShipping = true) {
  if (!requiresShipping) {
    return [{ id: 0, name: 'Kein Versand nötig', description: '', price: 0, delivery_time: '', effective_price: 0 }];
  }

  const zones = listZones();
  const code = String(country || 'DE').toUpperCase();
  const matching = zones.filter((z) => z.countries.includes(code));
  // Auffangzonen greifen nur, wenn kein Land explizit passt.
  const zone = matching[0] || zones.find((z) => z.countries.length === 0);
  if (!zone) return [];

  return zone.rates
    .filter((rate) => {
      if (rate.min_subtotal !== null && subtotal < rate.min_subtotal) return false;
      if (rate.max_subtotal !== null && subtotal > rate.max_subtotal) return false;
      return true;
    })
    .map((rate) => ({
      ...rate,
      effective_price: rate.free_over !== null && subtotal >= rate.free_over ? 0 : rate.price,
      free_applied: rate.free_over !== null && subtotal >= rate.free_over,
    }))
    .sort((a, b) => a.position - b.position || a.effective_price - b.effective_price);
}

export const zoneCountries = () => listZones().flatMap((z) => z.countries);

/** Alle belieferbaren Länder; leer bedeutet "keine Einschränkung". */
export function shippableCountries() {
  const zones = listZones();
  if (zones.some((z) => z.countries.length === 0)) return [];
  return [...new Set(zones.flatMap((z) => z.countries))].sort();
}

// --- Steuersätze ------------------------------------------------------------

export const listTaxRates = () => all('SELECT * FROM tax_rates ORDER BY is_default DESC, name');

export const getTaxRate = (id) => (id ? get('SELECT * FROM tax_rates WHERE id = ?', [id]) : null);

export const defaultTaxRate = () =>
  get('SELECT * FROM tax_rates WHERE is_default = 1') || get('SELECT * FROM tax_rates ORDER BY id LIMIT 1');

export function createTaxRate(input = {}) {
  // Eingabe kommt als Prozent ("19" oder "7,0"), gespeichert wird in Basispunkten.
  const percent = Number.parseFloat(String(input.rate ?? input.rate_bp ?? 0).replace(',', '.')) || 0;
  const bp = input.rate_bp !== undefined ? int(input.rate_bp, { min: 0, max: 10000 }) : Math.round(percent * 100);
  if (bool(input.is_default, false)) {
    all('SELECT id FROM tax_rates WHERE is_default = 1').forEach((r) =>
      update('tax_rates', r.id, { is_default: 0 }),
    );
  }
  return insert('tax_rates', {
    name: str(input.name, { max: 120, fallback: 'Steuersatz' }) || 'Steuersatz',
    rate_bp: bp,
    country: str(input.country, { max: 2, fallback: 'DE' }).toUpperCase() || 'DE',
    is_default: bool(input.is_default, false) ? 1 : 0,
  });
}

export function updateTaxRate(id, input = {}) {
  const existing = get('SELECT * FROM tax_rates WHERE id = ?', [id]);
  if (!existing) throw notFound('Steuersatz nicht gefunden');
  if (bool(input.is_default, false)) {
    all('SELECT id FROM tax_rates WHERE is_default = 1 AND id != ?', [id]).forEach((r) =>
      update('tax_rates', r.id, { is_default: 0 }),
    );
  }
  const percent = input.rate !== undefined
    ? Number.parseFloat(String(input.rate).replace(',', '.')) || 0
    : null;
  return update('tax_rates', id, {
    name: str(input.name ?? existing.name, { max: 120 }),
    rate_bp: percent !== null ? Math.round(percent * 100) : int(input.rate_bp ?? existing.rate_bp, { min: 0, max: 10000 }),
    country: str(input.country ?? existing.country, { max: 2 }).toUpperCase(),
    is_default: input.is_default === undefined ? existing.is_default : bool(input.is_default) ? 1 : 0,
  });
}

export const deleteTaxRate = (id) => remove('tax_rates', id);
