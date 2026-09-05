/*
 * Eingabeprüfung.
 *
 * Alles, was aus dem Browser kommt, geht hier durch: das Backend soll nicht
 * darauf vertrauen, dass das Admin-JavaScript korrekt gearbeitet hat.
 */

import { badRequest } from './http.js';

export function str(value, { max = 1000, trim = true, fallback = '' } = {}) {
  if (value === null || value === undefined) return fallback;
  let text = String(value);
  if (trim) text = text.trim();
  return text.slice(0, max);
}

export function int(value, { min = -Infinity, max = Infinity, fallback = 0 } = {}) {
  const n = Number.parseInt(value, 10);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(max, Math.max(min, n));
}

export function bool(value, fallback = false) {
  if (value === null || value === undefined || value === '') return fallback;
  if (typeof value === 'boolean') return value;
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

export function oneOf(value, allowed, fallback = allowed[0]) {
  return allowed.includes(value) ? value : fallback;
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
export const isEmail = (value) => EMAIL.test(String(value || '').trim());

export function requireFields(data, fields) {
  const missing = fields.filter((f) => {
    const value = data?.[f];
    return value === undefined || value === null || String(value).trim() === '';
  });
  if (missing.length > 0) {
    throw badRequest(`Pflichtfelder fehlen: ${missing.join(', ')}`, { fields: missing });
  }
}

/** Adressprüfung für Checkout und Bestellbearbeitung. */
export function validateAddress(input, { requireName = true } = {}) {
  const address = {
    first_name: str(input?.first_name, { max: 80 }),
    last_name: str(input?.last_name, { max: 80 }),
    company: str(input?.company, { max: 120 }),
    address1: str(input?.address1, { max: 160 }),
    address2: str(input?.address2, { max: 160 }),
    zip: str(input?.zip, { max: 20 }),
    city: str(input?.city, { max: 100 }),
    province: str(input?.province, { max: 100 }),
    country: str(input?.country, { max: 2, fallback: 'DE' }).toUpperCase() || 'DE',
    phone: str(input?.phone, { max: 40 }),
  };

  const errors = [];
  if (requireName && !address.first_name) errors.push('first_name');
  if (requireName && !address.last_name) errors.push('last_name');
  if (!address.address1) errors.push('address1');
  if (!address.zip) errors.push('zip');
  if (!address.city) errors.push('city');
  if (!/^[A-Z]{2}$/.test(address.country)) errors.push('country');

  if (errors.length > 0) {
    throw badRequest('Adresse unvollständig', { fields: errors });
  }
  return address;
}
