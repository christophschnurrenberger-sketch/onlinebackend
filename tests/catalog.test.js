/* Artikel, Varianten, Kategorien und Rabattregeln. */

import test from 'node:test';
import assert from 'node:assert/strict';
import { freshShop, makeProduct } from './helpers.js';
import * as products from '../src/models/products.js';
import * as collections from '../src/models/collections.js';
import * as discounts from '../src/models/discounts.js';
import * as content from '../src/models/content.js';

test('Handles sind eindeutig', () => {
  freshShop();
  const a = makeProduct({ title: 'Blaues Hemd' });
  const b = makeProduct({ title: 'Blaues Hemd' });

  assert.equal(a.handle, 'blaues-hemd');
  assert.equal(b.handle, 'blaues-hemd-2');
});

test('Beschreibungen werden beim Speichern bereinigt', () => {
  freshShop();
  const product = products.createProduct({
    title: 'Test',
    body_html: '<p>Gut</p><script>böse()</script><a href="javascript:x">Link</a>',
    variants: [{ price: '1,00' }],
  });

  assert.equal(product.body_html.includes('<script>'), false);
  assert.equal(product.body_html.includes('javascript:'), false);
  assert.ok(product.body_html.includes('<p>Gut</p>'));
});

test('Variantenmatrix aus Optionen', () => {
  const matrix = products.buildVariantMatrix(
    [{ name: 'Größe', values: ['S', 'M'] }, { name: 'Farbe', values: ['Rot', 'Blau'] }],
    { price: '10,00' },
  );

  assert.equal(matrix.length, 4);
  assert.deepEqual(matrix.map((v) => v.title), ['S / Rot', 'S / Blau', 'M / Rot', 'M / Blau']);
  assert.equal(matrix[0].option1, 'S');
  assert.equal(matrix[0].option2, 'Rot');
});

test('Varianten behalten ihre ID beim Bearbeiten', () => {
  freshShop();
  const product = products.createProduct({
    title: 'Hemd',
    variants: [{ title: 'S', price: '10,00' }, { title: 'M', price: '10,00' }],
  });
  const [ersteId, zweiteId] = product.variants.map((v) => v.id);

  const aktualisiert = products.updateProduct(product.id, {
    title: 'Hemd',
    variants: [
      { id: ersteId, title: 'S', price: '12,00' },
      { id: zweiteId, title: 'M', price: '10,00' },
      { title: 'L', price: '10,00' },
    ],
  });

  assert.equal(aktualisiert.variants.length, 3);
  assert.equal(aktualisiert.variants[0].id, ersteId, 'bestehende ID bleibt');
  assert.equal(aktualisiert.variants[0].price, 1200);
});

test('entfernte Varianten verschwinden', () => {
  freshShop();
  const product = products.createProduct({
    title: 'Hemd',
    variants: [{ title: 'S', price: '10,00' }, { title: 'M', price: '10,00' }],
  });

  const aktualisiert = products.updateProduct(product.id, {
    title: 'Hemd',
    variants: [{ id: product.variants[0].id, title: 'S', price: '10,00' }],
  });
  assert.equal(aktualisiert.variants.length, 1);
});

test('Duplikate landen immer als Entwurf', () => {
  freshShop();
  const original = makeProduct({ title: 'Original' });
  const kopie = products.duplicateProduct(original.id);

  assert.equal(kopie.status, 'draft');
  assert.match(kopie.title, /Kopie/);
  assert.notEqual(kopie.handle, original.handle);
});

test('Statuswechsel setzt das Veröffentlichungsdatum', () => {
  freshShop();
  const product = products.createProduct({
    title: 'Test', status: 'draft', variants: [{ price: '1,00' }],
  });
  assert.equal(product.published_at, null);

  products.setStatus(product.id, 'active');
  assert.ok(products.getProduct(product.id).published_at);

  products.setStatus(product.id, 'draft');
  assert.equal(products.getProduct(product.id).published_at, null);
});

test('Artikelsuche findet Titel und Artikelnummer', () => {
  freshShop();
  makeProduct({ title: 'Leinenhemd' });
  makeProduct({ title: 'Wollpullover' });

  assert.equal(products.listProducts({ search: 'leinen' }).total, 1);
  assert.equal(products.listProducts({ search: 'TEST-1' }).total, 2, 'beide haben diese SKU');
  assert.equal(products.listProducts({ search: 'gibtsnicht' }).total, 0);
});

test('automatische Kategorien werten Regeln aus', () => {
  freshShop();
  makeProduct({ title: 'Neu A', tags: ['neu'], price: '10,00' });
  makeProduct({ title: 'Neu B', tags: ['neu', 'sale'], price: '90,00' });
  makeProduct({ title: 'Alt', tags: ['alt'], price: '50,00' });

  const nachTag = collections.createCollection({
    title: 'Neuheiten', rule_type: 'auto',
    rules: [{ field: 'tag', operator: 'equals', value: 'neu' }],
  });
  assert.equal(collections.collectionProducts(nachTag).length, 2);

  const teuer = collections.createCollection({
    title: 'Teuer', rule_type: 'auto',
    rules: [{ field: 'price', operator: 'greater_than', value: '20,00' }],
  });
  assert.equal(collections.collectionProducts(teuer).length, 2);

  const beides = collections.createCollection({
    title: 'Neu und teuer', rule_type: 'auto', rules_match: 'all',
    rules: [
      { field: 'tag', operator: 'equals', value: 'neu' },
      { field: 'price', operator: 'greater_than', value: '20,00' },
    ],
  });
  assert.deepEqual(collections.collectionProducts(beides).map((p) => p.title), ['Neu B']);
});

test('automatische Kategorie ohne Regeln bleibt leer', () => {
  freshShop();
  makeProduct();
  const leer = collections.createCollection({ title: 'Ohne Regeln', rule_type: 'auto', rules: [] });
  assert.equal(collections.collectionProducts(leer).length, 0);
});

test('manuelle Kategorien behalten die Reihenfolge', () => {
  freshShop();
  const a = makeProduct({ title: 'A' });
  const b = makeProduct({ title: 'B' });
  const c = makeProduct({ title: 'C' });

  const kategorie = collections.createCollection({ title: 'Auswahl', sort_order: 'manual' });
  collections.setProducts(kategorie.id, [c.id, a.id, b.id]);

  const titel = collections.collectionProducts(collections.getCollection(kategorie.id)).map((p) => p.title);
  assert.deepEqual(titel, ['C', 'A', 'B']);
});

test('Rabattprüfung deckt Laufzeit, Limit und Aktivstatus ab', () => {
  freshShop();
  const gestern = new Date(Date.now() - 86400_000).toISOString();
  const morgen = new Date(Date.now() + 86400_000).toISOString();

  discounts.createDiscount({ code: 'ABGELAUFEN', type: 'percentage', value: '10', ends_at: gestern });
  discounts.createDiscount({ code: 'ZUFRUEH', type: 'percentage', value: '10', starts_at: morgen });
  discounts.createDiscount({ code: 'INAKTIV', type: 'percentage', value: '10', active: false });
  const limitiert = discounts.createDiscount({ code: 'LIMIT', type: 'percentage', value: '10', usage_limit: 1 });
  discounts.createDiscount({ code: 'GUELTIG', type: 'percentage', value: '10' });

  assert.match(discounts.validateForCart('ABGELAUFEN', { subtotal: 5000 }).reason, /abgelaufen/);
  assert.match(discounts.validateForCart('ZUFRUEH', { subtotal: 5000 }).reason, /noch nicht gültig/);
  assert.match(discounts.validateForCart('INAKTIV', { subtotal: 5000 }).reason, /nicht mehr aktiv/);
  assert.equal(discounts.validateForCart('GUELTIG', { subtotal: 5000 }).ok, true);
  assert.equal(discounts.validateForCart('gueltig', { subtotal: 5000 }).ok, true, 'Groß-/Kleinschreibung egal');

  discounts.incrementUsage(limitiert.id);
  assert.match(discounts.validateForCart('LIMIT', { subtotal: 5000 }).reason, /vollständig eingelöst/);
});

test('Rabattcodes sind eindeutig', () => {
  freshShop();
  discounts.createDiscount({ code: 'EINMAL', type: 'percentage', value: '10' });
  assert.throws(() => discounts.createDiscount({ code: 'einmal', type: 'fixed', value: '5,00' }), /existiert bereits/);
});

test('Seiten und Beiträge verhalten sich wie erwartet', () => {
  freshShop();
  const seite = content.pages.create({ title: 'Über uns', body_html: '<p>Text</p>', published: true });
  assert.equal(seite.handle, 'ueber-uns');
  assert.ok(seite.seo_description.length > 0, 'SEO-Beschreibung wird abgeleitet');

  const beitrag = content.posts.create({ title: 'Erster Beitrag', body_html: '<p>Inhalt</p>', published: true });
  assert.ok(beitrag.published_at, 'veröffentlichte Beiträge bekommen ein Datum');

  const entwurf = content.posts.create({ title: 'Entwurf', published: false });
  assert.equal(entwurf.published_at, null);
});

test('Menüs bilden zwei Ebenen ab', () => {
  freshShop();
  const menuId = content.ensureMenu('main', 'Hauptmenü');
  content.setMenuItems(menuId, [
    { label: 'Shop', url: '/collections/alle', children: [{ label: 'Neu', url: '/collections/neu' }] },
    { label: 'Journal', url: '/blog' },
  ]);

  const menu = content.getMenuByHandle('main');
  assert.equal(menu.items.length, 2);
  assert.equal(menu.items[0].children.length, 1);
  assert.equal(menu.items[0].children[0].label, 'Neu');
});
