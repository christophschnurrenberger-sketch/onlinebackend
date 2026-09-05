/*
 * Einstellungen.
 *
 * Gruppen liegen als JSON-Blobs in der Tabelle `settings`. Gelesen wird immer
 * über `merge` mit den Defaults, damit ein neues Feld im Code sofort verfügbar
 * ist, ohne dass eine Migration die gespeicherten Blobs anfassen muss.
 */

import { get, all, run, parseJson, nowIso } from '../db/index.js';

export const DEFAULTS = {
  // Stammdaten des Shops – erscheinen im Frontend, in Rechnungen und im SEO-Kopf.
  store: {
    name: 'Mein Shop',
    tagline: 'Willkommen im Shop',
    description: 'Ein Shop, betrieben mit dem eigenen Shop-CMS.',
    email: 'shop@example.com',
    phone: '',
    currency: 'EUR',
    locale: 'de-DE',
    country: 'DE',
    timezone: 'Europe/Berlin',
    logo_url: '',
    favicon_url: '',
    social: { instagram: '', facebook: '', tiktok: '', youtube: '' },
    address: {
      company: 'Mein Shop GmbH',
      address1: 'Musterstraße 1',
      zip: '10115',
      city: 'Berlin',
      country: 'DE',
    },
    legal: {
      vat_id: '',
      register: '',
      managing_director: '',
    },
  },

  // Das "generische CSS": Alle Werte werden als CSS-Custom-Properties in die
  // Storefront gerendert. Ein späterer Theme-Umbau ändert diese Variablen,
  // nicht die Templates.
  theme: {
    preset: 'basis',
    color_bg: '#ffffff',
    color_surface: '#f7f7f8',
    color_text: '#16181d',
    color_muted: '#6b7280',
    color_border: '#e5e7eb',
    color_primary: '#16181d',
    color_primary_text: '#ffffff',
    color_accent: '#2f6f4f',
    color_sale: '#c0392b',
    font_heading: "'Helvetica Neue', Helvetica, Arial, sans-serif",
    font_body: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif",
    radius: '10px',
    container_width: '1200px',
    product_grid_columns: 4,
    show_vendor: false,
    show_compare_at_price: true,
    announcement: '',
    announcement_active: false,
    hero_title: 'Neue Kollektion',
    hero_subtitle: 'Handverlesen, sofort lieferbar.',
    hero_image_url: '',
    hero_cta_label: 'Jetzt entdecken',
    hero_cta_url: '/collections/alle',
    footer_text: '',
    custom_css: '',
  },

  // Kasse & Kundenkonto.
  checkout: {
    guest_checkout: true,
    require_phone: false,
    terms_required: true,
    order_number_prefix: '',
    order_number_start: 1000,
    prices_include_tax: true,
    default_tax_bp: 1900,
    thank_you_text: 'Danke für deine Bestellung! Du erhältst gleich eine Bestätigung per E-Mail.',
    min_order_total: 0,
  },

  // Zahlarten. `enabled` steuert, was im Checkout angeboten wird; die
  // Zugangsdaten selbst kommen aus der Umgebung, nicht aus der Datenbank.
  payments: {
    enabled: ['mock', 'invoice', 'prepayment'],
    mock: { label: 'Testzahlung', instructions: 'Nur für Tests – es wird kein Geld bewegt.' },
    stripe: { label: 'Kreditkarte', mode: 'checkout' },
    paypal: { label: 'PayPal' },
    invoice: { label: 'Kauf auf Rechnung', instructions: 'Zahlbar innerhalb von 14 Tagen nach Erhalt der Ware.' },
    prepayment: { label: 'Vorkasse / Überweisung', instructions: 'Bitte überweise den Betrag auf das in der Bestätigung genannte Konto.' },
    cod: { label: 'Nachnahme', surcharge: 0, instructions: 'Zahlung bei Lieferung, zzgl. Nachnahmegebühr.' },
  },

  // Rechtstexte, die im Footer und im Checkout verlinkt werden.
  legal: {
    imprint_page: 'impressum',
    privacy_page: 'datenschutz',
    terms_page: 'agb',
    withdrawal_page: 'widerruf',
    shipping_page: 'versand',
  },

  // Wird bei jeder Veröffentlichung mitgeschrieben.
  publishing: {
    last_published_at: null,
    last_version: 0,
    auto_publish_on_save: false,
  },
};

const GROUPS = Object.keys(DEFAULTS);

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/** Tiefes Merge: gespeicherte Werte gewinnen, unbekannte Felder aus Defaults bleiben. */
function merge(defaults, stored) {
  if (!isPlainObject(stored)) return structuredClone(defaults);
  const out = structuredClone(defaults);
  for (const [key, value] of Object.entries(stored)) {
    if (isPlainObject(value) && isPlainObject(out[key])) {
      out[key] = merge(out[key], value);
    } else if (value !== undefined) {
      out[key] = value;
    }
  }
  return out;
}

export function getGroup(group) {
  if (!GROUPS.includes(group)) return {};
  const row = get('SELECT value_json FROM settings WHERE key = ?', [group]);
  return merge(DEFAULTS[group], parseJson(row?.value_json, {}));
}

export function getAll() {
  const rows = all('SELECT key, value_json FROM settings');
  const stored = Object.fromEntries(rows.map((r) => [r.key, parseJson(r.value_json, {})]));
  const out = {};
  for (const group of GROUPS) out[group] = merge(DEFAULTS[group], stored[group]);
  return out;
}

export function setGroup(group, patch) {
  if (!GROUPS.includes(group)) throw new Error(`Unbekannte Einstellungsgruppe: ${group}`);
  const next = merge(getGroup(group), patch);
  run(
    `INSERT INTO settings (key, value_json, updated_at) VALUES (?, ?, ?)
     ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at`,
    [group, JSON.stringify(next), nowIso()],
  );
  return next;
}

/** Einzelnen Wert setzen, ohne den Rest der Gruppe zu laden ("theme.color_bg"). */
export function setValue(path, value) {
  const [group, ...rest] = path.split('.');
  if (rest.length === 0) return setGroup(group, value);
  const patch = {};
  let cursor = patch;
  rest.forEach((key, i) => {
    if (i === rest.length - 1) cursor[key] = value;
    else cursor = cursor[key] = {};
  });
  return setGroup(group, patch);
}
