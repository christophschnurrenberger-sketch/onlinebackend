/*
 * Warenkorb.
 *
 * Der Warenkorb speichert nur Variante und Menge – niemals Preise. Preise
 * kommen bei jedem Aufruf frisch aus der Preis-Engine, damit eine
 * Preisänderung im Backend nicht durch alte Warenkörbe unterlaufen wird.
 */

import { randomBytes } from 'node:crypto';
import { all, get, run, insert, update, nowIso } from '../db/index.js';
import { getVariantWithProduct } from '../models/products.js';
import { priceCart } from './pricing.js';
import { availableStock } from './inventory.js';
import { int } from '../lib/validate.js';
import { badRequest, notFound } from '../lib/http.js';

export const MAX_LINE_QUANTITY = 999;

export function createCart() {
  const token = randomBytes(24).toString('base64url');
  const now = nowIso();
  insert('carts', { token, created_at: now, updated_at: now });
  return getCartByToken(token);
}

export function getCartByToken(token, { create = false } = {}) {
  const cart = get('SELECT * FROM carts WHERE token = ?', [token]);
  if (!cart) return create ? createCart() : null;
  return cart;
}

export const cartLines = (cartId) =>
  all('SELECT * FROM cart_lines WHERE cart_id = ? ORDER BY id', [cartId]);

/** Warenkorb inklusive Preisen – das, was Storefront und API ausliefern. */
export function summarize(cart) {
  const lines = cartLines(cart.id)
    .map((line) => ({ line, variant: getVariantWithProduct(line.variant_id) }))
    // Verschwundene oder deaktivierte Produkte fallen still heraus, statt den
    // Warenkorb unbenutzbar zu machen.
    .filter((entry) => entry.variant && entry.variant.product_status === 'active')
    .map((entry) => ({
      variant: entry.variant,
      quantity: entry.line.quantity,
      available: availableStock(entry.variant),
    }));

  const priced = priceCart(
    lines.map((l) => ({ variant: l.variant, quantity: l.quantity })),
    {
      country: cart.country,
      discountCode: cart.discount_code,
      shippingRateId: cart.shipping_rate_id,
      customerId: cart.customer_id,
      email: cart.email,
    },
  );

  // Bestandswarnungen an die Positionen hängen: der Kunde soll vor der Kasse
  // sehen, was klemmt, nicht erst beim Bezahlversuch.
  priced.lines.forEach((line, index) => {
    const source = lines[index];
    line.available = source?.available ?? null;
    line.over_stock = source?.available !== null && source?.available !== undefined
      ? line.quantity > source.available
      : false;
  });

  return {
    token: cart.token,
    note: cart.note,
    email: cart.email,
    ...priced,
    has_stock_issue: priced.lines.some((line) => line.over_stock),
  };
}

export function addLine(cart, variantId, quantity = 1) {
  const variant = getVariantWithProduct(variantId);
  if (!variant) throw notFound('Artikel nicht gefunden');
  if (variant.product_status !== 'active') throw badRequest('Dieser Artikel ist nicht verfügbar');

  const qty = int(quantity, { min: 1, max: MAX_LINE_QUANTITY, fallback: 1 });
  const existing = get('SELECT * FROM cart_lines WHERE cart_id = ? AND variant_id = ?', [
    cart.id,
    variantId,
  ]);
  const wanted = Math.min(MAX_LINE_QUANTITY, (existing?.quantity || 0) + qty);

  const stock = availableStock(variant);
  if (stock !== null && wanted > stock) {
    if (stock <= 0) throw badRequest('Dieser Artikel ist ausverkauft');
    throw badRequest(`Nur noch ${stock} Stück verfügbar`, { available: stock });
  }

  if (existing) run('UPDATE cart_lines SET quantity = ? WHERE id = ?', [wanted, existing.id]);
  else insert('cart_lines', { cart_id: cart.id, variant_id: variantId, quantity: wanted });

  touch(cart.id);
  return summarize(cart);
}

export function setLineQuantity(cart, variantId, quantity) {
  const qty = int(quantity, { min: 0, max: MAX_LINE_QUANTITY, fallback: 0 });
  if (qty === 0) return removeLine(cart, variantId);

  const variant = getVariantWithProduct(variantId);
  if (!variant) throw notFound('Artikel nicht gefunden');

  const stock = availableStock(variant);
  if (stock !== null && qty > stock) {
    throw badRequest(`Nur noch ${stock} Stück verfügbar`, { available: stock });
  }

  const changed = run('UPDATE cart_lines SET quantity = ? WHERE cart_id = ? AND variant_id = ?', [
    qty,
    cart.id,
    variantId,
  ]).changes;
  if (changed === 0) return addLine(cart, variantId, qty);

  touch(cart.id);
  return summarize(cart);
}

export function removeLine(cart, variantId) {
  run('DELETE FROM cart_lines WHERE cart_id = ? AND variant_id = ?', [cart.id, variantId]);
  touch(cart.id);
  return summarize(cart);
}

export function clear(cart) {
  run('DELETE FROM cart_lines WHERE cart_id = ?', [cart.id]);
  update('carts', cart.id, { discount_code: '', updated_at: nowIso() });
  return summarize(getCartByToken(cart.token));
}

/** Setzt Land, Versandart, Rabattcode oder Notiz und liefert die neue Summe. */
export function updateCart(cart, patch = {}) {
  const data = { updated_at: nowIso() };
  if (patch.country !== undefined) {
    data.country = String(patch.country).toUpperCase().slice(0, 2) || 'DE';
    // Ein Länderwechsel kann die gewählte Versandart ungültig machen.
    data.shipping_rate_id = null;
  }
  if (patch.discount_code !== undefined) data.discount_code = String(patch.discount_code).trim().slice(0, 60);
  if (patch.shipping_rate_id !== undefined) {
    data.shipping_rate_id = int(patch.shipping_rate_id, { fallback: 0 }) || null;
  }
  if (patch.email !== undefined) data.email = String(patch.email).trim().slice(0, 200);
  if (patch.note !== undefined) data.note = String(patch.note).slice(0, 1000);

  update('carts', cart.id, data);
  return summarize(getCartByToken(cart.token));
}

const touch = (cartId) => update('carts', cartId, { updated_at: nowIso() });

/** Räumt Warenkörbe auf, die seit `days` Tagen niemand angefasst hat. */
export function purgeStaleCarts(days = 30) {
  const cutoff = new Date(Date.now() - days * 86400_000).toISOString();
  return run('DELETE FROM carts WHERE updated_at < ?', [cutoff]).changes;
}
