/*
 * Preisberechnung.
 *
 * Diese Tests halten fest, was Kunde und Betreiber auf dem Beleg sehen: dass
 * Rabatte centgenau aufgeteilt werden, Versandkostenfreiheit greift und die
 * ausgewiesene Steuer zur Summe passt.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { freshShop, makeProduct, variantOf } from './helpers.js';
import { priceCart } from '../src/services/pricing.js';
import * as discounts from '../src/models/discounts.js';

test('einfacher Warenkorb: Summe, Versand und enthaltene Steuer', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '10,00' }));

  const result = priceCart([{ variant, quantity: 3 }], { country: 'DE' });

  assert.equal(result.subtotal, 3000);
  assert.equal(result.shipping_total, 490);
  assert.equal(result.total, 3490);
  // 34,90 € brutto bei 19 % enthalten 5,57 €.
  assert.equal(result.tax_total, 557);
  assert.equal(result.item_count, 3);
});

test('Prozentrabatt wird centgenau auf die Positionen verteilt', () => {
  freshShop();
  const a = variantOf(makeProduct({ title: 'A', price: '149,90' }));
  const b = variantOf(makeProduct({ title: 'B', price: '99,90' }));
  discounts.createDiscount({ code: 'ZEHN', type: 'percentage', value: '10' });

  const result = priceCart([{ variant: a, quantity: 2 }, { variant: b, quantity: 1 }], {
    country: 'DE',
    discountCode: 'ZEHN',
  });

  assert.equal(result.subtotal, 39970);
  assert.equal(result.discount_total, 3997);
  const verteilt = result.lines.reduce((sum, line) => sum + line.discount, 0);
  assert.equal(verteilt, result.discount_total, 'Positionsrabatte ergeben den Gesamtrabatt');
  const positionen = result.lines.reduce((sum, line) => sum + line.total, 0);
  assert.equal(positionen, result.subtotal - result.discount_total);
});

test('fester Rabatt übersteigt nie den Warenwert', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '10,00' }));
  discounts.createDiscount({ code: 'FUENFZIG', type: 'fixed', value: '50,00' });

  const result = priceCart([{ variant, quantity: 1 }], { country: 'DE', discountCode: 'FUENFZIG' });

  assert.equal(result.discount_total, 1000, 'höchstens der Warenwert');
  assert.equal(result.subtotal - result.discount_total, 0);
});

test('Mindestbestellwert eines Rabatts wird geprüft', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '10,00' }));
  discounts.createDiscount({ code: 'AB50', type: 'percentage', value: '10', min_subtotal: '50,00' });

  const zuKlein = priceCart([{ variant, quantity: 1 }], { country: 'DE', discountCode: 'AB50' });
  assert.equal(zuKlein.discount_total, 0);
  assert.match(zuKlein.discount_error, /Mindestbestellwert/);

  const groß = priceCart([{ variant, quantity: 6 }], { country: 'DE', discountCode: 'AB50' });
  assert.equal(groß.discount_total, 600);
  assert.equal(groß.discount_error, '');
});

test('Gratisversand-Rabatt setzt die Versandkosten auf null', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '10,00' }));
  discounts.createDiscount({ code: 'VERSANDFREI', type: 'free_shipping' });

  const result = priceCart([{ variant, quantity: 1 }], { country: 'DE', discountCode: 'VERSANDFREI' });

  assert.equal(result.shipping_total, 0);
  assert.equal(result.discount_total, 0, 'Gratisversand ist kein Warenrabatt');
  assert.equal(result.total, 1000);
});

test('Versandkostenfreiheit ab Schwellenwert', () => {
  freshShop({ freeOver: '50,00' });
  const variant = variantOf(makeProduct({ price: '10,00' }));

  assert.equal(priceCart([{ variant, quantity: 4 }], { country: 'DE' }).shipping_total, 490);
  assert.equal(priceCart([{ variant, quantity: 5 }], { country: 'DE' }).shipping_total, 0);
});

test('Rabatt kann die Versandkostenfreiheit wieder aufheben', () => {
  freshShop({ freeOver: '50,00' });
  const variant = variantOf(makeProduct({ price: '10,00' }));
  discounts.createDiscount({ code: 'ZWANZIG', type: 'percentage', value: '20' });

  // 5 × 10 € = 50 €, minus 20 % = 40 € – unter der Schwelle.
  const result = priceCart([{ variant, quantity: 5 }], { country: 'DE', discountCode: 'ZWANZIG' });
  assert.equal(result.shipping_total, 490, 'Schwelle gilt für den rabattierten Wert');
});

test('unbekanntes Land ohne Auffangzone hat keine Versandart', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '10,00' }));

  const result = priceCart([{ variant, quantity: 1 }], { country: 'US' });
  assert.equal(result.shipping_rates.length, 0);
  assert.equal(result.shipping_total, 0);
});

test('ungültiger Rabattcode liefert eine Begründung statt eines Fehlers', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '10,00' }));

  const result = priceCart([{ variant, quantity: 1 }], { country: 'DE', discountCode: 'GIBTSNICHT' });
  assert.equal(result.discount_total, 0);
  assert.match(result.discount_error, /ungültig/);
});

test('Gesamtsumme bleibt konsistent', () => {
  freshShop();
  const variant = variantOf(makeProduct({ price: '33,33' }));
  discounts.createDiscount({ code: 'SIEBEN', type: 'percentage', value: '7' });

  const result = priceCart([{ variant, quantity: 7 }], { country: 'DE', discountCode: 'SIEBEN' });
  assert.equal(result.total, result.subtotal - result.discount_total + result.shipping_total);
  const steuerSumme = result.tax_lines.reduce((sum, line) => sum + line.amount, 0);
  assert.equal(steuerSumme, result.tax_total);
  assert.ok(result.tax_total < result.total, 'enthaltene Steuer ist kleiner als die Summe');
});
