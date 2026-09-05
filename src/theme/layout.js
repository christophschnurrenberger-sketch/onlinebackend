/*
 * Layout der Storefront.
 *
 * Der Rahmen jeder Seite: <head> mit SEO- und Open-Graph-Angaben, Kopf mit
 * Navigation und Warenkorb, Fuß mit Rechtslinks. Die Theme-Einstellungen
 * werden als CSS-Custom-Properties in den <head> geschrieben – deshalb ändert
 * ein Farbwechsel im Backend das ganze Frontend, ohne dass eine Datei neu
 * gebaut werden muss.
 */

import { html, raw, escapeHtml } from '../lib/html.js';
import { formatMoney } from '../lib/money.js';

/** Theme-Einstellungen -> CSS-Variablen. */
export function themeVariables(theme) {
  const map = {
    '--bg': theme.color_bg,
    '--surface': theme.color_surface,
    '--text': theme.color_text,
    '--muted': theme.color_muted,
    '--border': theme.color_border,
    '--primary': theme.color_primary,
    '--primary-text': theme.color_primary_text,
    '--accent': theme.color_accent,
    '--sale': theme.color_sale,
    '--radius': theme.radius,
    '--container': theme.container_width,
    '--font-heading': theme.font_heading,
    '--font-body': theme.font_body,
    '--grid-columns': theme.product_grid_columns,
  };
  return Object.entries(map)
    .filter(([, value]) => value !== undefined && value !== null && value !== '')
    // Semikolons und geschweifte Klammern in Farbwerten könnten sonst aus der
    // Regel ausbrechen und beliebiges CSS einschleusen.
    .map(([key, value]) => `  ${key}: ${String(value).replace(/[;{}<>]/g, '')};`)
    .join('\n');
}

function menuItems(items, depth = 0) {
  if (!items || items.length === 0) return raw('');
  return html`${items.map(
    (item) => html`<li class="nav-item">
      <a href="${item.url}">${item.label}</a>
      ${item.children && item.children.length > 0 && depth === 0
        ? html`<ul class="submenu">${menuItems(item.children, depth + 1)}</ul>`
        : ''}
    </li>`,
  )}`;
}

function header(snapshot, { cartCount = 0 } = {}) {
  const { store, theme, menus } = snapshot;
  return html`
    ${theme.announcement_active && theme.announcement
      ? html`<div class="announcement">${theme.announcement}</div>`
      : ''}
    <header class="site-header">
      <div class="container header-inner">
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Menü">☰</button>
        <a class="brand" href="/">
          ${store.logo_url
            ? html`<img src="${store.logo_url}" alt="${store.name}" />`
            : html`${store.name}`}
        </a>
        <nav>
          <ul class="main-nav" id="main-nav" style="list-style:none;margin:0;padding:0">
            ${menuItems(menus.main || [])}
          </ul>
        </nav>
        <div class="header-actions">
          <form class="search-form" action="/search" role="search">
            <label class="visually-hidden" for="q">Suche</label>
            <input type="search" id="q" name="q" placeholder="Suchen…" />
          </form>
          <a class="cart-link" href="/cart">
            Warenkorb
            <span class="cart-count" data-cart-count>${cartCount}</span>
          </a>
        </div>
      </div>
    </header>
  `;
}

function footer(snapshot) {
  const { store, theme, menus, legal } = snapshot;
  const year = new Date().getFullYear();
  const legalLinks = [
    ['imprint_page', 'Impressum'],
    ['privacy_page', 'Datenschutz'],
    ['terms_page', 'AGB'],
    ['withdrawal_page', 'Widerruf'],
    ['shipping_page', 'Versand & Zahlung'],
  ].filter(([key]) => legal[key]);

  const socials = Object.entries(store.social || {}).filter(([, url]) => url);

  return html`
    <footer class="site-footer">
      <div class="container">
        <div class="footer-grid">
          <div>
            <h4>${store.name}</h4>
            <p style="color:var(--muted);font-size:0.9375rem">${theme.footer_text || store.description}</p>
            ${socials.length > 0
              ? html`<ul>${socials.map(([name, url]) => html`<li><a href="${url}" rel="noopener">${name}</a></li>`)}</ul>`
              : ''}
          </div>
          ${(menus.footer || []).length > 0
            ? html`<div>
                <h4>Shop</h4>
                <ul>${(menus.footer || []).map((item) => html`<li><a href="${item.url}">${item.label}</a></li>`)}</ul>
              </div>`
            : ''}
          ${legalLinks.length > 0
            ? html`<div>
                <h4>Rechtliches</h4>
                <ul>${legalLinks.map(([key, label]) => html`<li><a href="/pages/${legal[key]}">${label}</a></li>`)}</ul>
              </div>`
            : ''}
          <div>
            <h4>Kontakt</h4>
            <ul>
              ${store.email ? html`<li><a href="mailto:${store.email}">${store.email}</a></li>` : ''}
              ${store.phone ? html`<li>${store.phone}</li>` : ''}
              ${store.address?.city
                ? html`<li>${store.address.address1}, ${store.address.zip} ${store.address.city}</li>`
                : ''}
            </ul>
          </div>
        </div>
        <div class="footer-bottom">
          <span>© ${year} ${store.address?.company || store.name}</span>
          <span>Alle Preise inkl. gesetzlicher MwSt. zzgl. Versandkosten.</span>
        </div>
      </div>
    </footer>
  `;
}

/**
 * Rendert eine komplette Seite.
 * `body` ist bereits gerendertes HTML (RawHtml aus dem html-Template).
 */
export function page(snapshot, body, options = {}) {
  const {
    title = '',
    description = '',
    canonical = '',
    image = '',
    cartCount = 0,
    noindex = false,
    jsonLd = null,
    bodyClass = '',
    preview = false,
  } = options;

  const { store, theme } = snapshot;
  const fullTitle = title ? `${title} – ${store.name}` : `${store.name} – ${store.tagline}`;
  const metaDescription = description || store.description;

  return `<!doctype html>
<html lang="${(store.locale || 'de-DE').slice(0, 2)}" data-currency="${escapeHtml(store.currency || 'EUR')}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escapeHtml(fullTitle)}</title>
<meta name="description" content="${escapeHtml(metaDescription)}">
${noindex ? '<meta name="robots" content="noindex,nofollow">' : ''}
${canonical ? `<link rel="canonical" href="${escapeHtml(canonical)}">` : ''}
<meta property="og:type" content="website">
<meta property="og:site_name" content="${escapeHtml(store.name)}">
<meta property="og:title" content="${escapeHtml(fullTitle)}">
<meta property="og:description" content="${escapeHtml(metaDescription)}">
${image ? `<meta property="og:image" content="${escapeHtml(image)}">` : ''}
<meta name="twitter:card" content="summary_large_image">
${store.favicon_url ? `<link rel="icon" href="${escapeHtml(store.favicon_url)}">` : ''}
<link rel="stylesheet" href="/theme/base.css">
<style>
:root {
${themeVariables(theme)}
}
${theme.custom_css ? String(theme.custom_css).replace(/<\/?(script|style)/gi, '') : ''}
</style>
${jsonLd ? `<script type="application/ld+json">${JSON.stringify(jsonLd).replace(/</g, '\\u003c')}</script>` : ''}
</head>
<body class="${escapeHtml(bodyClass)}">
${preview ? '<div class="announcement" style="background:#8a5a00">Vorschau – dieser Stand ist noch nicht veröffentlicht.</div>' : ''}
${header(snapshot, { cartCount })}
<main id="main">
${body}
</main>
${footer(snapshot)}
<script src="/theme/storefront.js" defer></script>
</body>
</html>`;
}

/** Preisanzeige inkl. Streichpreis und "ab"-Präfix bei Preisspannen. */
export function priceHtml(product, currency, { showCompare = true } = {}) {
  const min = product.min_price ?? 0;
  const max = product.max_price ?? min;
  const compare = product.max_compare_at || 0;
  const onSale = showCompare && compare > max;

  const current = min === max
    ? formatMoney(min, currency)
    : `ab ${formatMoney(min, currency)}`;

  return html`<div class="price">
    <span class="current ${onSale ? 'on-sale' : ''}">${current}</span>
    ${onSale ? html`<span class="compare">${formatMoney(compare, currency)}</span>` : ''}
  </div>`;
}

/** Eine Kachel im Produktraster. */
export function productCard(product, { currency, theme, soldOut = false }) {
  const image = product.images?.[0];
  const onSale = (product.max_compare_at || 0) > (product.max_price || 0);

  return html`<article class="product-card">
    <a href="/products/${product.handle}">
      <div class="media">
        ${image
          ? html`<img src="${image.url}" alt="${image.alt || product.title}" loading="lazy" />`
          : html`<div class="placeholder">Kein Bild</div>`}
        ${soldOut
          ? html`<span class="badge sold-out">Ausverkauft</span>`
          : onSale && theme.show_compare_at_price
            ? html`<span class="badge">Sale</span>`
            : ''}
      </div>
      ${theme.show_vendor && product.vendor ? html`<p class="vendor">${product.vendor}</p>` : ''}
      <h3>${product.title}</h3>
      ${priceHtml(product, currency, { showCompare: theme.show_compare_at_price })}
    </a>
  </article>`;
}
