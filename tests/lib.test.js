/* Slugs, HTML-Bereinigung und Routing. */

import test from 'node:test';
import assert from 'node:assert/strict';
import { slugify, uniqueSlug } from '../src/lib/slug.js';
import { sanitizeHtml, escapeHtml, html, raw, stripTags } from '../src/lib/html.js';
import { Router } from '../src/lib/http.js';
import { hashPassword, verifyPassword } from '../src/lib/auth.js';
import { validateAddress } from '../src/lib/validate.js';

test('slugify behandelt Umlaute und Sonderzeichen', () => {
  assert.equal(slugify('Größe & Länge'), 'groesse-laenge');
  assert.equal(slugify('  Weiß—Blau!  '), 'weiss-blau');
  assert.equal(slugify(''), 'eintrag');
  assert.equal(slugify('Ärmel'), 'aermel');
});

test('uniqueSlug hängt an, wenn belegt', () => {
  const taken = new Set(['hemd', 'hemd-2']);
  assert.equal(uniqueSlug('Hemd', (s) => taken.has(s)), 'hemd-3');
  assert.equal(uniqueSlug('Hose', (s) => taken.has(s)), 'hose');
});

test('sanitizeHtml entfernt Skripte und gefährliche Attribute', () => {
  assert.equal(sanitizeHtml('<p>ok</p><script>alert(1)</script>'), '<p>ok</p>');
  assert.equal(sanitizeHtml('<p onclick="böse()">Text</p>'), '<p>Text</p>');
  assert.equal(sanitizeHtml('<a href="javascript:alert(1)">Link</a>'), '<a>Link</a>');
  assert.equal(sanitizeHtml('<a href="/seite">Link</a>'), '<a href="/seite">Link</a>');
  assert.equal(sanitizeHtml('<img src="data:text/html,x" />'), '<img />');
  assert.equal(sanitizeHtml('<iframe src="https://fremd"></iframe>'), '');
  assert.ok(sanitizeHtml('<strong>fett</strong>').includes('<strong>'));
});

test('html-Template escaped interpolierte Werte', () => {
  const böse = '<script>alert(1)</script>';
  assert.equal(String(html`<p>${böse}</p>`), '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');
  assert.equal(String(html`<p>${raw('<b>ok</b>')}</p>`), '<p><b>ok</b></p>');
  assert.equal(escapeHtml(`"'&<>`), '&quot;&#39;&amp;&lt;&gt;');
});

test('stripTags kürzt auf Wunsch', () => {
  assert.equal(stripTags('<p>Hallo <b>Welt</b></p>'), 'Hallo Welt');
  assert.ok(stripTags('<p>' + 'a'.repeat(300) + '</p>', 50).length <= 50);
});

test('Router matcht Parameter und Wildcards', () => {
  const router = new Router();
  router.get('/products/:handle', () => 'produkt');
  router.get('/assets/*', () => 'datei');
  router.post('/products', () => 'anlegen');

  assert.equal(router.match('GET', '/products/hemd').params.handle, 'hemd');
  assert.equal(router.match('GET', '/assets/css/a.css').params.wildcard, 'css/a.css');
  assert.equal(router.match('GET', '/products'), null, 'GET /products ist nicht definiert');
  assert.ok(router.match('POST', '/products'), 'POST /products existiert');
  assert.ok(router.match('HEAD', '/products/hemd'), 'HEAD wird wie GET behandelt');
  assert.equal(router.match('GET', '/unbekannt'), null);
});

test('Router mountet Präfixe', () => {
  const inner = new Router();
  inner.get('/cart', () => 'warenkorb');
  const outer = new Router();
  outer.mount('/api', inner);

  assert.ok(outer.match('GET', '/api/cart'));
  assert.equal(outer.match('GET', '/cart'), null);
});

test('Passwörter werden gesalzen gehasht', () => {
  const hash = hashPassword('geheim123');
  assert.notEqual(hash, hashPassword('geheim123'), 'gleicher Klartext ergibt anderen Hash');
  assert.ok(verifyPassword('geheim123', hash));
  assert.equal(verifyPassword('falsch', hash), false);
  assert.equal(verifyPassword('geheim123', 'kaputt'), false);
});

test('validateAddress verlangt die Pflichtfelder', () => {
  const gültig = validateAddress({
    first_name: 'Anna', last_name: 'Beispiel', address1: 'Weg 1',
    zip: '10115', city: 'Berlin', country: 'de',
  });
  assert.equal(gültig.country, 'DE', 'Land wird großgeschrieben');

  assert.throws(() => validateAddress({ first_name: 'Anna' }), /unvollständig/);
});
