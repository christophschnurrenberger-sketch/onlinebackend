/*
 * Veröffentlichen.
 *
 * Der wichtigste Test dieses Projekts: Was im Backend als Entwurf liegt, darf
 * unter keinen Umständen im Shop erscheinen. Alles andere am Snapshot-System
 * ist Bequemlichkeit – das hier ist die Zusage an den Betreiber.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { freshShop, makeProduct } from './helpers.js';
import * as publish from '../src/services/publish.js';
import * as products from '../src/models/products.js';
import * as collections from '../src/models/collections.js';
import * as content from '../src/models/content.js';
import { setGroup } from '../src/models/settings.js';

const fresh = () => {
  freshShop();
  publish.invalidate();
};

test('vor der ersten Veröffentlichung ist der Shop leer', () => {
  fresh();
  makeProduct({ title: 'Fertiger Artikel' });

  assert.equal(publish.live(), null);
  assert.equal(publish.pendingChanges().never_published, true);
});

test('Veröffentlichen schaltet den aktuellen Stand live', () => {
  fresh();
  makeProduct({ title: 'Hemd' });

  const result = publish.publish({ note: 'Erste' });
  assert.equal(result.version, 1);
  assert.equal(result.stats.products, 1);

  const snapshot = publish.live();
  assert.equal(snapshot.products.length, 1);
  assert.equal(snapshot.products[0].title, 'Hemd');
  assert.equal(publish.pendingChanges().count, 0, 'direkt danach ist nichts offen');
});

test('Entwürfe erscheinen nie im veröffentlichten Shop', () => {
  fresh();
  makeProduct({ title: 'Sichtbar' });
  publish.publish();

  products.createProduct({
    title: 'Geheimer Entwurf',
    status: 'draft',
    variants: [{ price: '10,00' }],
  });

  const snapshot = publish.live();
  assert.equal(snapshot.products.length, 1);
  assert.equal(
    snapshot.products.some((p) => p.title === 'Geheimer Entwurf'),
    false,
    'der Entwurf ist nicht im Shop',
  );
});

test('Änderungen an aktiven Artikeln wirken erst nach dem Veröffentlichen', () => {
  fresh();
  const product = makeProduct({ title: 'Alter Titel' });
  publish.publish();

  products.updateProduct(product.id, { title: 'Neuer Titel' });

  assert.equal(publish.live().products[0].title, 'Alter Titel', 'Shop zeigt den alten Stand');
  assert.equal(publish.buildSnapshot().products[0].title, 'Neuer Titel', 'Vorschau zeigt den neuen');

  const pending = publish.pendingChanges();
  assert.equal(pending.count, 1);
  assert.equal(pending.changes[0].type, 'changed');
  assert.equal(pending.changes[0].kind, 'product');

  publish.publish();
  assert.equal(publish.live().products[0].title, 'Neuer Titel');
});

test('offene Änderungen benennen neue, geänderte und entfernte Einträge', () => {
  fresh();
  const bleibt = makeProduct({ title: 'Bleibt' });
  const verschwindet = makeProduct({ title: 'Verschwindet' });
  publish.publish();

  makeProduct({ title: 'Kommt dazu' });
  products.setStatus(verschwindet.id, 'draft');
  products.updateProduct(bleibt.id, { subtitle: 'geändert' });

  const typen = publish.pendingChanges().changes.map((c) => c.type).sort();
  assert.deepEqual(typen, ['added', 'changed', 'removed']);
});

test('Rollback schaltet eine frühere Version wieder live', () => {
  fresh();
  const product = makeProduct({ title: 'Version eins' });
  publish.publish({ note: 'v1' });

  products.updateProduct(product.id, { title: 'Version zwei' });
  publish.publish({ note: 'v2' });
  assert.equal(publish.live().products[0].title, 'Version zwei');

  publish.rollback(1);
  assert.equal(publish.live().products[0].title, 'Version eins');
  assert.equal(publish.liveVersion(), 1);

  const versionen = publish.listVersions();
  assert.equal(versionen.find((v) => v.live).version, 1);
  assert.equal(versionen.length, 2, 'die neuere Version bleibt erhalten');
});

test('unveröffentlichte Kategorien und Seiten bleiben draußen', () => {
  fresh();
  makeProduct({ title: 'Artikel', tags: ['neu'] });
  collections.createCollection({ title: 'Sichtbar', published: true, rule_type: 'auto',
    rules: [{ field: 'tag', operator: 'equals', value: 'neu' }] });
  collections.createCollection({ title: 'Versteckt', published: false });
  content.pages.create({ title: 'Öffentlich', published: true });
  content.pages.create({ title: 'Entwurf', published: false });

  publish.publish();
  const snapshot = publish.live();

  assert.deepEqual(snapshot.collections.map((c) => c.title), ['Sichtbar']);
  assert.deepEqual(snapshot.pages.map((p) => p.title), ['Öffentlich']);
});

test('automatische Kategorien werden beim Veröffentlichen aufgelöst', () => {
  fresh();
  makeProduct({ title: 'Neuheit', tags: ['neu'] });
  makeProduct({ title: 'Klassiker', tags: ['alt'] });
  const auto = collections.createCollection({
    title: 'Neuheiten', published: true, rule_type: 'auto',
    rules: [{ field: 'tag', operator: 'equals', value: 'neu' }],
  });

  publish.publish();
  const snapshot = publish.live();
  const kategorie = snapshot.collections.find((c) => c.id === auto.id);

  assert.equal(kategorie.product_ids.length, 1);
  const titel = snapshot.products.find((p) => p.id === kategorie.product_ids[0]).title;
  assert.equal(titel, 'Neuheit');
});

test('Kategorien verweisen nur auf veröffentlichte Artikel', () => {
  fresh();
  const sichtbar = makeProduct({ title: 'Aktiv' });
  const entwurf = products.createProduct({
    title: 'Entwurf', status: 'draft', variants: [{ price: '10,00' }],
  });
  const manuell = collections.createCollection({ title: 'Auswahl', published: true });
  collections.setProducts(manuell.id, [sichtbar.id, entwurf.id]);

  publish.publish();
  const kategorie = publish.live().collections[0];

  assert.deepEqual(kategorie.product_ids, [sichtbar.id], 'der Entwurf fehlt in der Kategorie');
});

test('Design- und Stammdatenänderungen tauchen als offene Änderung auf', () => {
  fresh();
  makeProduct();
  publish.publish();

  setGroup('theme', { color_primary: '#ff0000' });
  const pending = publish.pendingChanges();

  assert.equal(pending.count, 1);
  assert.equal(pending.changes[0].kind, 'theme');
  assert.notEqual(publish.live().theme.color_primary, '#ff0000');

  publish.publish();
  assert.equal(publish.live().theme.color_primary, '#ff0000');
});

test('Bestände laufen am Snapshot vorbei und wirken sofort', async () => {
  fresh();
  const product = makeProduct({ stock: 10 });
  publish.publish();

  const inventory = await import('../src/services/inventory.js');
  inventory.setQuantity(product.variants[0].id, 3);

  const snapshotVariante = publish.live().products[0].variants[0];
  assert.equal('inventory_quantity' in snapshotVariante, false,
    'der Snapshot trägt bewusst keine Bestände');
  assert.equal(products.getVariant(product.variants[0].id).inventory_quantity, 3);
});
