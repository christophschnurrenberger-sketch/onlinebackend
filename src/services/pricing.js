/*
 * Preisberechnung.
 *
 * Diese Datei ist die einzige Wahrheit über Beträge: Warenkorb-Vorschau,
 * Checkout und die gespeicherte Bestellung laufen alle hier durch. Wären es
 * zwei Implementierungen, würden sie irgendwann um einen Cent auseinander-
 * laufen – und genau das sieht der Kunde.
 *
 * Modell: Bruttopreise (EU-B2C). Die ausgewiesene Steuer ist der im Preis
 * enthaltene Anteil, kein Aufschlag.
 */

import { taxFromGross, distribute } from '../lib/money.js';
import * as shippingModel from '../models/shipping.js';
import * as discountModel from '../models/discounts.js';
import { getGroup } from '../models/settings.js';
import { all } from '../db/index.js';

/**
 * @param lines  [{ variant, quantity }] – variant kommt aus getVariantWithProduct
 * @param options { country, discountCode, shippingRateId, customerId, email }
 */
export function priceCart(lines, options = {}) {
  const checkout = getGroup('checkout');
  const store = getGroup('store');
  const currency = store.currency || 'EUR';
  const country = String(options.country || store.country || 'DE').toUpperCase();

  const priced = lines
    .filter((line) => line.variant && line.quantity > 0)
    .map((line) => {
      const unit = line.variant.price;
      const quantity = line.quantity;
      return {
        variant_id: line.variant.id,
        product_id: line.variant.product_id,
        title: line.variant.product_title ?? line.variant.title,
        variant_title: line.variant.title,
        sku: line.variant.sku || '',
        image_url: line.variant.display_image || line.variant.image_url || '',
        handle: line.variant.product_handle || '',
        quantity,
        price: unit,
        compare_at_price: line.variant.compare_at_price ?? null,
        line_subtotal: unit * quantity,
        requires_shipping: Boolean(line.variant.requires_shipping),
        taxable: Boolean(line.variant.taxable),
        tax_rate_id: line.variant.tax_rate_id ?? null,
        weight_grams: (line.variant.weight_grams || 0) * quantity,
        discount: 0,
        total: unit * quantity,
        tax_rate_bp: 0,
        tax_amount: 0,
      };
    });

  const subtotal = priced.reduce((sum, line) => sum + line.line_subtotal, 0);

  // --- Rabatt --------------------------------------------------------------
  const discountResult = applyDiscount(priced, subtotal, options);
  const discountTotal = discountResult.amount;

  // --- Versand -------------------------------------------------------------
  const requiresShipping = priced.some((line) => line.requires_shipping);
  const shippingBase = subtotal - discountTotal;
  const availableRates = shippingModel.ratesFor(country, shippingBase, requiresShipping);

  let selectedRate =
    availableRates.find((rate) => rate.id === Number(options.shippingRateId)) || availableRates[0] || null;
  let shippingTotal = selectedRate ? selectedRate.effective_price : 0;
  if (discountResult.freeShipping) shippingTotal = 0;

  // --- Steuer --------------------------------------------------------------
  const taxRates = Object.fromEntries(shippingModel.listTaxRates().map((r) => [r.id, r]));
  const defaultBp = shippingModel.defaultTaxRate()?.rate_bp ?? checkout.default_tax_bp ?? 0;

  const taxBreakdown = new Map();
  for (const line of priced) {
    const bp = line.taxable ? taxRates[line.tax_rate_id]?.rate_bp ?? defaultBp : 0;
    line.tax_rate_bp = bp;
    line.tax_amount = taxFromGross(line.total, bp);
    if (bp > 0) {
      const entry = taxBreakdown.get(bp) || { rate_bp: bp, base: 0, amount: 0 };
      entry.base += line.total;
      entry.amount += line.tax_amount;
      taxBreakdown.set(bp, entry);
    }
  }

  // Versandkosten werden mit dem höchsten im Korb vorkommenden Satz besteuert –
  // die in Deutschland übliche Vereinfachung für gemischte Warenkörbe.
  const shippingBp = priced.length > 0 ? Math.max(...priced.map((l) => l.tax_rate_bp)) : defaultBp;
  const shippingTax = taxFromGross(shippingTotal, shippingBp);
  if (shippingTax > 0) {
    const entry = taxBreakdown.get(shippingBp) || { rate_bp: shippingBp, base: 0, amount: 0 };
    entry.base += shippingTotal;
    entry.amount += shippingTax;
    taxBreakdown.set(shippingBp, entry);
  }

  const taxTotal = [...taxBreakdown.values()].reduce((sum, entry) => sum + entry.amount, 0);
  const total = subtotal - discountTotal + shippingTotal;

  return {
    currency,
    country,
    lines: priced,
    item_count: priced.reduce((sum, line) => sum + line.quantity, 0),
    subtotal,
    discount_total: discountTotal,
    discount_code: discountResult.code,
    discount_title: discountResult.title,
    discount_error: discountResult.error,
    shipping_total: shippingTotal,
    shipping_rate: selectedRate,
    shipping_rates: availableRates,
    requires_shipping: requiresShipping,
    total_weight_grams: priced.reduce((sum, line) => sum + line.weight_grams, 0),
    tax_total: taxTotal,
    tax_lines: [...taxBreakdown.values()].sort((a, b) => b.rate_bp - a.rate_bp),
    prices_include_tax: checkout.prices_include_tax !== false,
    total,
    min_order_total: checkout.min_order_total || 0,
    below_minimum: (checkout.min_order_total || 0) > 0 && total < checkout.min_order_total,
  };
}

/**
 * Verteilt den Rabatt auf die Positionen. Die Aufteilung ist nötig, damit
 * Teil-Rückerstattungen und die Steuer je Satz korrekt bleiben – ein Rabatt,
 * der nur als Summe existiert, lässt sich später nicht mehr zuordnen.
 */
function applyDiscount(lines, subtotal, options) {
  const code = String(options.discountCode || '').trim();
  if (!code) return { amount: 0, code: '', title: '', error: '', freeShipping: false };

  const check = discountModel.validateForCart(code, {
    subtotal,
    customerId: options.customerId,
    email: options.email,
  });
  if (!check.ok) {
    return { amount: 0, code: '', title: '', error: check.reason, freeShipping: false };
  }

  const discount = check.discount;
  const eligible = eligibleLines(lines, discount);
  const eligibleSubtotal = eligible.reduce((sum, line) => sum + line.line_subtotal, 0);

  if (discount.type === 'free_shipping') {
    return { amount: 0, code: discount.code, title: discount.title, error: '', freeShipping: true };
  }
  if (eligibleSubtotal === 0) {
    return {
      amount: 0,
      code: '',
      title: '',
      error: 'Dieser Rabattcode gilt für keinen Artikel im Warenkorb.',
      freeShipping: false,
    };
  }

  const amount = discount.type === 'percentage'
    ? Math.round((eligibleSubtotal * discount.value) / 10000)
    : Math.min(discount.value, eligibleSubtotal);

  const shares = distribute(amount, eligible.map((line) => line.line_subtotal));
  eligible.forEach((line, index) => {
    line.discount = shares[index];
    line.total = line.line_subtotal - line.discount;
  });

  return { amount, code: discount.code, title: discount.title, error: '', freeShipping: false };
}

function eligibleLines(lines, discount) {
  if (discount.applies_to === 'all') return lines;
  const targets = new Set(discount.target_ids || []);
  if (targets.size === 0) return lines;

  if (discount.applies_to === 'product') {
    return lines.filter((line) => targets.has(line.product_id));
  }
  // applies_to === 'collection'
  const productIds = lines.map((line) => line.product_id).filter(Boolean);
  if (productIds.length === 0) return [];
  const placeholders = productIds.map(() => '?').join(',');
  const collectionPlaceholders = [...targets].map(() => '?').join(',');
  const rows = all(
    `SELECT DISTINCT product_id FROM collection_products
      WHERE product_id IN (${placeholders}) AND collection_id IN (${collectionPlaceholders})`,
    [...productIds, ...targets],
  );
  const allowed = new Set(rows.map((r) => r.product_id));
  return lines.filter((line) => allowed.has(line.product_id));
}
