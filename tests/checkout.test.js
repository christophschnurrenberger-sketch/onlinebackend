/*
 * Warenkorb, Bestand und Bestellabschluss.
 *
 * Der Bestand ist die Stelle, an der ein Shop echten Schaden anrichten kann:
 * überverkaufte Ware muss storniert werden, verschwundene Ware fehlt im Lager.
 * Diese Tests halten beide Richtungen fest.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { freshShop, makeProduct } from './helpers.js';
import * as cart from '../src/services/cart.js';
import * as checkout from '../src/services/checkout.js';
import * as orders from '../src/models/orders.js';
import * as products from '../src/models/products.js';
import * as inventory from '../src/services/inventory.js';
import { getGroup, setGroup } from '../src/models/settings.js';

const ADDRESS = {
  first_name: 'Anna', last_name: 'Beispiel', address1: 'Hauptstraße 1',
  zip: '10115', city: 'Berlin', country: 'DE',
};

const orderInput = (overrides = {}) => ({
  email: 'kunde@example.com',
  payment_provider: 'mock',
  accept_terms: true,
  shipping_address: ADDRESS,
  ...overrides,
});

test('Artikel in den Warenkorb legen und Menge ändern', () => {
  freshShop();
  const product = makeProduct({ price: '25,00', stock: 10 });
  const session = cart.createCart();

  let summary = cart.addLine(session, product.variants[0].id, 2);
  assert.equal(summary.item_count, 2);
  assert.equal(summary.subtotal, 5000);

  summary = cart.addLine(session, product.variants[0].id, 1);
  assert.equal(summary.item_count, 3, 'gleiche Variante wird zusammengefasst');

  summary = cart.setLineQuantity(session, product.variants[0].id, 1);
  assert.equal(summary.item_count, 1);

  summary = cart.setLineQuantity(session, product.variants[0].id, 0);
  assert.equal(summary.lines.length, 0, 'Menge 0 entfernt die Position');
});

test('Warenkorb verhindert das Überschreiten des Bestands', () => {
  freshShop();
  const product = makeProduct({ stock: 3 });
  const session = cart.createCart();

  assert.throws(() => cart.addLine(session, product.variants[0].id, 4), /Nur noch 3/);
  cart.addLine(session, product.variants[0].id, 3);
  assert.throws(() => cart.addLine(session, product.variants[0].id, 1), /Nur noch 3/);
});

test('ausverkaufte Artikel lassen sich nicht bestellen', () => {
  freshShop();
  const product = makeProduct({ stock: 0 });
  const session = cart.createCart();
  assert.throws(() => cart.addLine(session, product.variants[0].id, 1), /ausverkauft/i);
});

test('Artikel ohne Bestandsführung sind unbegrenzt verfügbar', () => {
  freshShop();
  const product = products.createProduct({
    title: 'Gutschein', status: 'active',
    variants: [{ price: '50,00', track_inventory: false, requires_shipping: false }],
  });
  const session = cart.createCart();

  const summary = cart.addLine(session, product.variants[0].id, 99);
  assert.equal(summary.item_count, 99);
  assert.equal(summary.requires_shipping, false);
  assert.equal(summary.shipping_total, 0);
});

test('Bestellabschluss legt die Bestellung an und bucht den Bestand aus', async () => {
  freshShop();
  const product = makeProduct({ price: '40,00', stock: 10 });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 2);

  const result = await checkout.begin(session, orderInput());

  assert.equal(result.action, 'complete');
  assert.equal(result.order.financial_status, 'paid', 'Testzahlung bezahlt sofort');
  assert.equal(result.order.total, 8490, '80 € Ware + 4,90 € Versand');
  assert.equal(result.order.subtotal, 8000);
  assert.equal(result.order.shipping_total, 490);
  assert.equal(result.order.lines.length, 1);
  assert.equal(products.getVariant(product.variants[0].id).inventory_quantity, 8);

  const nachher = cart.summarize(cart.getCartByToken(session.token));
  assert.equal(nachher.lines.length, 0, 'Warenkorb ist nach der Bestellung leer');
});

test('Bestandsbewegung ist der Bestellung zugeordnet', async () => {
  freshShop();
  const product = makeProduct({ stock: 5 });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 2);

  const { order } = await checkout.begin(session, orderInput());
  const [move] = inventory.movesForVariant(product.variants[0].id);

  assert.equal(move.delta, -2);
  assert.equal(move.reason, 'sale');
  assert.equal(move.order_number, order.number);
});

test('Abbruch der Zahlung gibt den Bestand wieder frei', async () => {
  freshShop();
  const product = makeProduct({ stock: 5 });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 3);

  const { order } = await checkout.begin(session, orderInput({ payment_provider: 'invoice' }));
  assert.equal(products.getVariant(product.variants[0].id).inventory_quantity, 2);

  const storniert = checkout.fail(order.id, 'Test');
  assert.equal(storniert.status, 'cancelled');
  assert.equal(products.getVariant(product.variants[0].id).inventory_quantity, 5);
});

test('Checkout weist unvollständige Eingaben zurück', async () => {
  freshShop();
  const product = makeProduct();
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 1);

  await assert.rejects(() => checkout.begin(session, orderInput({ email: 'keine-mail' })), /E-Mail/);
  await assert.rejects(
    () => checkout.begin(session, orderInput({ shipping_address: { first_name: 'Anna' } })),
    /unvollständig/,
  );
  await assert.rejects(() => checkout.begin(session, orderInput({ accept_terms: false })), /AGB/);
  await assert.rejects(
    () => checkout.begin(session, orderInput({ payment_provider: 'gibtsnicht' })),
    /Zahlart/,
  );
});

test('leerer Warenkorb kann nicht bestellt werden', async () => {
  freshShop();
  const session = cart.createCart();
  await assert.rejects(() => checkout.begin(session, orderInput()), /leer/);
});

test('Mindestbestellwert wird durchgesetzt', async () => {
  freshShop();
  setGroup('checkout', { min_order_total: 5000 });
  const product = makeProduct({ price: '10,00' });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 1);

  await assert.rejects(() => checkout.begin(session, orderInput()), /Mindestbestellwert/);
});

test('Rechnungskauf lässt die Bestellung offen, bis der Betreiber bucht', async () => {
  freshShop();
  const product = makeProduct({ price: '20,00' });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 1);

  const { order } = await checkout.begin(session, orderInput({ payment_provider: 'invoice' }));
  assert.equal(order.financial_status, 'pending');

  const bezahlt = orders.markPaid(order.id, { reference: 'Überweisung 12.03.' });
  assert.equal(bezahlt.financial_status, 'paid');
  assert.ok(bezahlt.paid_at);
  assert.ok(bezahlt.events.some((event) => event.type === 'paid'));
});

test('Versand, Erstattung und Storno verändern die Bestellung erwartungsgemäß', async () => {
  freshShop();
  const product = makeProduct({ price: '30,00', stock: 10 });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 2);
  const { order } = await checkout.begin(session, orderInput());

  const versendet = orders.fulfill(order.id, { carrier: 'DHL', trackingNumber: '123' });
  assert.equal(versendet.fulfillment_status, 'fulfilled');
  assert.equal(versendet.fulfillments.length, 1);
  assert.throws(() => orders.fulfill(order.id, {}), /Keine offenen Positionen/);

  const teilErstattet = orders.refund(order.id, { amount: 1000, reason: 'Kulanz' });
  assert.equal(teilErstattet.financial_status, 'partially_refunded');
  assert.equal(teilErstattet.refunded_total, 1000);
  assert.throws(() => orders.refund(order.id, { amount: 999999 }), /erstattbar/);

  const storniert = orders.cancel(order.id, { reason: 'Kundenwunsch' });
  assert.equal(storniert.status, 'cancelled');
});

test('Teilversand setzt den Status auf teilweise versendet', async () => {
  freshShop();
  const product = makeProduct({ price: '15,00', stock: 20 });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 5);
  const { order } = await checkout.begin(session, orderInput());

  const teil = orders.fulfill(order.id, {
    lines: [{ line_id: order.lines[0].id, quantity: 2 }],
    carrier: 'DHL',
  });
  assert.equal(teil.fulfillment_status, 'partial');
  assert.equal(teil.lines[0].fulfilled_quantity, 2);

  const rest = orders.fulfill(order.id, {});
  assert.equal(rest.fulfillment_status, 'fulfilled');
});

test('Bestellungen sind fortlaufend nummeriert', async () => {
  freshShop();
  const product = makeProduct({ stock: 50 });
  const nummern = [];

  for (let i = 0; i < 3; i += 1) {
    const session = cart.createCart();
    cart.addLine(session, product.variants[0].id, 1);
    const { order } = await checkout.begin(session, orderInput());
    nummern.push(order.number);
  }

  assert.deepEqual(nummern, [nummern[0], nummern[0] + 1, nummern[0] + 2]);
  assert.ok(nummern[0] >= getGroup('checkout').order_number_start);
});

test('Kunde entsteht aus der Bestellung und sammelt Umsatz', async () => {
  freshShop();
  const product = makeProduct({ price: '25,00', stock: 20 });

  for (let i = 0; i < 2; i += 1) {
    const session = cart.createCart();
    cart.addLine(session, product.variants[0].id, 1);
    await checkout.begin(session, orderInput());
  }

  const customers = await import('../src/models/customers.js');
  const kunde = customers.findByEmail('kunde@example.com');
  assert.ok(kunde, 'Kunde wurde angelegt');
  assert.equal(kunde.orders_count, 2);
  assert.equal(kunde.first_name, 'Anna');
});

test('Bestellung ist ein Dokument: spätere Preisänderungen wirken nicht zurück', async () => {
  freshShop();
  const product = makeProduct({ price: '20,00', stock: 10 });
  const session = cart.createCart();
  cart.addLine(session, product.variants[0].id, 1);
  const { order } = await checkout.begin(session, orderInput());

  products.updateProduct(product.id, {
    title: 'Ganz anderer Name',
    variants: [{ id: product.variants[0].id, price: '99,00' }],
  });

  const gespeichert = orders.getOrder(order.id);
  assert.equal(gespeichert.lines[0].price, 2000, 'Preis der Bestellung bleibt');
  assert.equal(gespeichert.lines[0].title, 'Testartikel', 'Titel der Bestellung bleibt');
  assert.equal(gespeichert.total, order.total);
});
