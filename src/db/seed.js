/*
 * Startdatenbestand.
 *
 * Legt an, was ein Shop mindestens braucht, um sofort benutzbar zu sein:
 * einen Zugang, Steuersätze, Versandzonen, Rechtsseiten, Navigation – und
 * ein paar Beispielartikel, damit die Storefront nicht leer wirkt.
 *
 * Idempotent: mehrfaches Ausführen ergänzt nur Fehlendes.
 */

import { getDb, migrate, get, insert, nowIso } from './index.js';
import * as auth from '../lib/auth.js';
import * as productModel from '../models/products.js';
import * as collectionModel from '../models/collections.js';
import * as contentModel from '../models/content.js';
import * as shippingModel from '../models/shipping.js';
import * as discountModel from '../models/discounts.js';
import * as settingsModel from '../models/settings.js';
import * as publish from '../services/publish.js';
import config from '../config.js';

migrate(getDb());

const created = [];
const note = (what) => created.push(what);

// --- Zugang -----------------------------------------------------------------

if (!get('SELECT id FROM users LIMIT 1')) {
  auth.createUser({
    email: config.admin.email,
    password: config.admin.password,
    name: 'Inhaber',
    role: 'owner',
  });
  note(`Zugang ${config.admin.email} (Passwort: ${config.admin.password})`);
}

// --- Steuern ----------------------------------------------------------------

if (shippingModel.listTaxRates().length === 0) {
  shippingModel.createTaxRate({ name: 'Standard (19 %)', rate: '19', is_default: true });
  shippingModel.createTaxRate({ name: 'Ermäßigt (7 %)', rate: '7' });
  note('Steuersätze 19 % / 7 %');
}

// --- Versand ----------------------------------------------------------------

if (shippingModel.listZones().length === 0) {
  shippingModel.createZone({
    name: 'Deutschland',
    countries: ['DE'],
    rates: [
      { name: 'Standardversand', price: '4,90', delivery_time: '2–3 Werktage', free_over: '75,00' },
      { name: 'Expressversand', price: '11,90', delivery_time: 'Nächster Werktag' },
    ],
  });
  shippingModel.createZone({
    name: 'Österreich & Schweiz',
    countries: ['AT', 'CH'],
    rates: [{ name: 'Standardversand', price: '9,90', delivery_time: '3–5 Werktage', free_over: '120,00' }],
  });
  shippingModel.createZone({
    name: 'EU',
    countries: ['NL', 'BE', 'LU', 'FR', 'IT', 'ES', 'PL', 'DK', 'CZ', 'SE'],
    rates: [{ name: 'EU-Versand', price: '14,90', delivery_time: '4–7 Werktage' }],
  });
  note('Versandzonen Deutschland, AT/CH, EU');
}

// --- Rechtsseiten -----------------------------------------------------------

const PAGES = [
  {
    handle: 'impressum',
    title: 'Impressum',
    body_html: `<p><strong>Angaben gemäß § 5 TMG</strong></p>
<p>Mein Shop GmbH<br />Musterstraße 1<br />10115 Berlin</p>
<p>Vertreten durch: Max Mustermann</p>
<p>Kontakt: shop@example.com</p>
<p>Umsatzsteuer-ID gemäß § 27 a UStG: DE000000000</p>
<p><em>Bitte im Backend unter Inhalte → Seiten durch die eigenen Angaben ersetzen.</em></p>`,
  },
  {
    handle: 'datenschutz',
    title: 'Datenschutzerklärung',
    body_html: `<p>Wir verarbeiten personenbezogene Daten ausschließlich zur Abwicklung
deiner Bestellung sowie auf Grundlage der gesetzlichen Bestimmungen (DSGVO, TDDDG).</p>
<h2>Verantwortlicher</h2><p>Mein Shop GmbH, Musterstraße 1, 10115 Berlin</p>
<h2>Deine Rechte</h2><p>Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung,
Datenübertragbarkeit und Widerspruch.</p>
<p><em>Bitte im Backend durch eine geprüfte Datenschutzerklärung ersetzen.</em></p>`,
  },
  {
    handle: 'agb',
    title: 'Allgemeine Geschäftsbedingungen',
    body_html: `<h2>1. Geltungsbereich</h2><p>Für alle Bestellungen über unseren Online-Shop
gelten die nachfolgenden AGB.</p>
<h2>2. Vertragspartner</h2><p>Der Kaufvertrag kommt zustande mit der Mein Shop GmbH.</p>
<h2>3. Vertragsschluss</h2><p>Mit dem Klick auf „Zahlungspflichtig bestellen“ gibst du
ein verbindliches Angebot ab.</p>
<h2>4. Preise und Versandkosten</h2><p>Alle Preise verstehen sich inklusive gesetzlicher
Mehrwertsteuer zuzüglich Versandkosten.</p>
<p><em>Bitte im Backend durch geprüfte AGB ersetzen.</em></p>`,
  },
  {
    handle: 'widerruf',
    title: 'Widerrufsbelehrung',
    body_html: `<h2>Widerrufsrecht</h2><p>Du hast das Recht, binnen vierzehn Tagen ohne Angabe
von Gründen diesen Vertrag zu widerrufen.</p>
<h2>Folgen des Widerrufs</h2><p>Wenn du diesen Vertrag widerrufst, haben wir dir alle
Zahlungen unverzüglich und spätestens binnen vierzehn Tagen zurückzuzahlen.</p>
<p><em>Bitte im Backend durch eine geprüfte Belehrung ersetzen.</em></p>`,
  },
  {
    handle: 'versand',
    title: 'Versand & Zahlung',
    body_html: `<h2>Versandkosten</h2><ul>
<li>Deutschland: 4,90 € – ab 75 € versandkostenfrei</li>
<li>Österreich & Schweiz: 9,90 € – ab 120 € versandkostenfrei</li>
<li>EU: 14,90 €</li></ul>
<h2>Lieferzeit</h2><p>Innerhalb Deutschlands in der Regel 2–3 Werktage nach Zahlungseingang.</p>
<h2>Zahlarten</h2><p>Die im Checkout angebotenen Zahlarten hängen von den Einstellungen
im Backend ab.</p>`,
  },
];

for (const page of PAGES) {
  if (!contentModel.pages.getByHandle(page.handle)) {
    contentModel.pages.create({ ...page, published: true });
    note(`Seite „${page.title}“`);
  }
}

// --- Beispielkatalog --------------------------------------------------------

const DEMO_PRODUCTS = [
  {
    title: 'Leinenhemd Sommer',
    subtitle: 'Luftig, waschbar, aus europäischem Leinen',
    product_type: 'Hemden',
    vendor: 'Hausmarke',
    tags: ['neu', 'sommer', 'leinen'],
    body_html: `<p>Ein Hemd aus 100 % europäischem Leinen – leicht, atmungsaktiv und mit jeder
Wäsche etwas weicher. Klassischer Kentkragen, verdeckte Knopfleiste.</p>
<ul><li>100 % Leinen (Masters of Linen)</li><li>Maschinenwäsche 30 °C</li>
<li>Regular Fit</li></ul>`,
    options: [
      { name: 'Größe', values: ['S', 'M', 'L', 'XL'] },
      { name: 'Farbe', values: ['Naturweiß', 'Salbei'] },
    ],
    price: '89,00',
    compare_at_price: '119,00',
    stock: 12,
  },
  {
    title: 'Wollpullover Merino',
    subtitle: 'Feinstrick aus Merinowolle, mulesing-frei',
    product_type: 'Pullover',
    vendor: 'Hausmarke',
    tags: ['neu', 'winter', 'wolle'],
    body_html: `<p>Feiner Merino-Strick, der wärmt ohne aufzutragen. Rundhalsausschnitt,
Bündchen an Ärmeln und Saum.</p><ul><li>100 % Merinowolle, 19,5 Mikron</li>
<li>Mulesing-frei</li><li>Handwäsche oder Wollprogramm</li></ul>`,
    options: [{ name: 'Größe', values: ['S', 'M', 'L', 'XL'] }],
    price: '149,00',
    stock: 8,
  },
  {
    title: 'Ledergürtel Vollrind',
    subtitle: 'Pflanzlich gegerbt, Messingschnalle',
    product_type: 'Accessoires',
    vendor: 'Sattlerei Nord',
    tags: ['leder', 'accessoires'],
    body_html: `<p>Aus einem Stück pflanzlich gegerbtem Vollrindleder geschnitten, mit
massiver Messingschnalle. Bekommt mit den Jahren eine eigene Patina.</p>`,
    options: [{ name: 'Länge', values: ['85 cm', '90 cm', '95 cm', '100 cm'] }],
    price: '69,00',
    stock: 20,
  },
  {
    title: 'Canvas-Tasche Weekender',
    subtitle: 'Wasserabweisend, mit Lederboden',
    product_type: 'Taschen',
    vendor: 'Hausmarke',
    tags: ['neu', 'taschen', 'reise'],
    body_html: `<p>Reisetasche aus schwerem gewachstem Canvas mit Lederboden und
abnehmbarem Schultergurt. 42 Liter – Handgepäckmaß.</p>`,
    price: '189,00',
    stock: 5,
  },
  {
    title: 'Emaille-Becher',
    subtitle: '350 ml, spülmaschinenfest',
    product_type: 'Küche',
    vendor: 'Manufaktur Süd',
    tags: ['küche', 'geschenk'],
    body_html: `<p>Klassischer Emaille-Becher mit Stahlkern und blauem Rand.
Für Lagerfeuer, Büro und alles dazwischen.</p>`,
    price: '18,00',
    stock: 40,
  },
  {
    title: 'Notizbuch A5 Leinen',
    subtitle: '192 Seiten, dotted, fadengebunden',
    product_type: 'Papeterie',
    vendor: 'Manufaktur Süd',
    tags: ['papeterie', 'geschenk'],
    body_html: `<p>Fadengebundenes Notizbuch mit Leineneinband, 100 g/m² Papier
und Lesebändchen. Liegt flach auf.</p>`,
    price: '24,00',
    stock: 30,
  },
];

if (!get('SELECT id FROM products LIMIT 1')) {
  for (const demo of DEMO_PRODUCTS) {
    const variants = demo.options
      ? productModel.buildVariantMatrix(demo.options, {
          price: demo.price,
          compare_at_price: demo.compare_at_price,
          inventory_quantity: demo.stock,
        })
      : [{ title: 'Standard', price: demo.price, inventory_quantity: demo.stock }];

    productModel.createProduct({
      title: demo.title,
      subtitle: demo.subtitle,
      body_html: demo.body_html,
      vendor: demo.vendor,
      product_type: demo.product_type,
      tags: demo.tags,
      status: 'active',
      options: demo.options || [],
      variants: variants.map((v, index) => ({
        ...v,
        sku: `${demo.title.slice(0, 3).toUpperCase()}-${String(index + 1).padStart(3, '0')}`,
      })),
    });
  }
  note(`${DEMO_PRODUCTS.length} Beispielartikel`);
}

// --- Kategorien -------------------------------------------------------------

if (collectionModel.listCollections().length === 0) {
  // "Alle Produkte" ist regelbasiert mit einer Bedingung, die jeder kaufbare
  // Artikel erfüllt – so nimmt die Kategorie neue Produkte automatisch auf.
  collectionModel.createCollection({
    title: 'Alle Produkte',
    handle: 'alle',
    published: true,
    rule_type: 'auto',
    rules: [{ field: 'price', operator: 'greater_than', value: '0' }],
    sort_order: 'title-asc',
  });

  collectionModel.createCollection({
    title: 'Neuheiten',
    handle: 'neuheiten',
    published: true,
    rule_type: 'auto',
    rules: [{ field: 'tag', operator: 'equals', value: 'neu' }],
    sort_order: 'created-desc',
    body_html: '<p>Zuletzt aufgenommen – solange der Vorrat reicht.</p>',
  });

  collectionModel.createCollection({
    title: 'Accessoires',
    handle: 'accessoires',
    published: true,
    rule_type: 'auto',
    rules: [{ field: 'product_type', operator: 'equals', value: 'Accessoires' }],
  });
  note('Kategorien Alle / Neuheiten / Accessoires');
}

// --- Navigation -------------------------------------------------------------

if (!contentModel.getMenuByHandle('main')) {
  const mainId = contentModel.ensureMenu('main', 'Hauptmenü');
  contentModel.setMenuItems(mainId, [
    { label: 'Shop', url: '/collections/alle', children: [
      { label: 'Neuheiten', url: '/collections/neuheiten' },
      { label: 'Accessoires', url: '/collections/accessoires' },
    ] },
    { label: 'Journal', url: '/blog' },
    { label: 'Versand & Zahlung', url: '/pages/versand' },
  ]);

  const footerId = contentModel.ensureMenu('footer', 'Fußzeile');
  contentModel.setMenuItems(footerId, [
    { label: 'Alle Produkte', url: '/collections/alle' },
    { label: 'Journal', url: '/blog' },
    { label: 'Kategorien', url: '/collections' },
  ]);
  note('Navigation');
}

// --- Beispielrabatt & Beitrag -----------------------------------------------

if (!discountModel.findByCode('WILLKOMMEN10')) {
  discountModel.createDiscount({
    code: 'WILLKOMMEN10',
    title: '10 % für Neukunden',
    type: 'percentage',
    value: '10',
    min_subtotal: '50,00',
    once_per_customer: true,
  });
  note('Rabattcode WILLKOMMEN10');
}

if (contentModel.posts.list().length === 0) {
  contentModel.posts.create({
    title: 'Willkommen im Shop',
    published: true,
    author: 'Redaktion',
    excerpt: 'Warum es diesen Shop gibt und was du hier findest.',
    body_html: `<p>Dieser Shop läuft auf einem eigenen CMS: Artikel, Kategorien, Inhalte und
Einstellungen werden im Backend gepflegt und mit einem Klick veröffentlicht.</p>
<p>Bis zum Veröffentlichen sieht niemand die Änderungen – danach alle.</p>`,
  });
  note('Beispielbeitrag');
}

// --- Shop-Stammdaten --------------------------------------------------------

if (!get("SELECT key FROM settings WHERE key = 'store'")) {
  settingsModel.setGroup('store', {
    name: 'Mein Shop',
    tagline: 'Ausgesuchte Dinge, die halten',
    description: 'Ein kleiner Laden für Kleidung, Taschen und Dinge für den Alltag.',
  });
  settingsModel.setGroup('theme', {
    hero_title: 'Ausgesuchte Dinge, die halten',
    hero_subtitle: 'Kleidung und Alltagsgegenstände aus Materialien, die besser werden statt schlechter.',
    hero_cta_label: 'Zum Sortiment',
    hero_cta_url: '/collections/alle',
    announcement: 'Versandkostenfrei ab 75 € innerhalb Deutschlands',
    announcement_active: true,
  });
  note('Shop-Stammdaten und Theme');
}

// --- Erste Veröffentlichung -------------------------------------------------

if (publish.listVersions(1).length === 0) {
  const result = publish.publish({ note: 'Erste Veröffentlichung (Seed)' });
  note(`Veröffentlichung v${result.version}: ${result.stats.products} Artikel, ${result.stats.collections} Kategorien`);
}

console.log('Startdatenbestand angelegt:');
for (const entry of created) console.log(`  · ${entry}`);
if (created.length === 0) console.log('  · nichts zu tun – alles schon vorhanden');
console.log(`\nBackend:  ${config.baseUrl}/admin`);
console.log(`Shop:     ${config.baseUrl}`);
