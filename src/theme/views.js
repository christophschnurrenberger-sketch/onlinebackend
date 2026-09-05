/*
 * Seiten der Storefront.
 *
 * Alle Seiten werden serverseitig gerendert: Katalogseiten sind damit ohne
 * JavaScript vollständig lesbar und für Suchmaschinen erfassbar. JavaScript
 * verbessert nur (Variantenauswahl, Mengen, In-den-Warenkorb) – ohne bleibt
 * der Shop bedienbar, weil dieselben Aktionen als Formulare existieren.
 */

import { html, raw } from '../lib/html.js';
import { formatMoney } from '../lib/money.js';
import { productCard, priceHtml } from './layout.js';

const currencyOf = (snapshot) => snapshot.store.currency || 'EUR';

// --- Startseite -------------------------------------------------------------

export function home(snapshot, { featured = [], soldOut = new Set() }) {
  const { theme } = snapshot;
  const currency = currencyOf(snapshot);
  const heroStyle = theme.hero_image_url
    ? `background-image:linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.35)),url('${String(theme.hero_image_url).replace(/['"\\]/g, '')}')`
    : '';

  return html`
    <section class="hero ${theme.hero_image_url ? 'has-image' : ''}" style="${heroStyle}">
      <div class="container hero-inner">
        <h1>${theme.hero_title}</h1>
        <p>${theme.hero_subtitle}</p>
        ${theme.hero_cta_label
          ? html`<a class="btn" href="${theme.hero_cta_url || '/collections'}">${theme.hero_cta_label}</a>`
          : ''}
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head">
          <h2>Neu im Shop</h2>
          <a href="/collections">Alle Kategorien</a>
        </div>
        ${featured.length > 0
          ? html`<div class="product-grid">
              ${featured.map((product) =>
                productCard(product, { currency, theme, soldOut: soldOut.has(product.id) }),
              )}
            </div>`
          : html`<div class="empty-state">
              <p>Noch keine Produkte veröffentlicht.</p>
              <p>Lege im Backend Artikel an und klicke auf „Veröffentlichen“.</p>
            </div>`}
      </div>
    </section>

    ${snapshot.collections.length > 0
      ? html`<section class="section" style="background:var(--surface)">
          <div class="container">
            <div class="section-head"><h2>Kategorien</h2></div>
            <div class="product-grid">
              ${snapshot.collections.slice(0, 8).map(
                (collection) => html`<article class="product-card">
                  <a href="/collections/${collection.handle}">
                    <div class="media">
                      ${collection.image_url
                        ? html`<img src="${collection.image_url}" alt="${collection.title}" loading="lazy" />`
                        : html`<div class="placeholder">${collection.title}</div>`}
                    </div>
                    <h3>${collection.title}</h3>
                    <p class="vendor">${collection.product_ids.length} Artikel</p>
                  </a>
                </article>`,
              )}
            </div>
          </div>
        </section>`
      : ''}
  `;
}

// --- Kategorien -------------------------------------------------------------

export function collectionIndex(snapshot) {
  return html`
    <div class="container">
      <div class="page-head"><h1>Kategorien</h1></div>
      <div class="product-grid" style="padding-bottom:var(--space-8)">
        ${snapshot.collections.map(
          (collection) => html`<article class="product-card">
            <a href="/collections/${collection.handle}">
              <div class="media">
                ${collection.image_url
                  ? html`<img src="${collection.image_url}" alt="${collection.title}" loading="lazy" />`
                  : html`<div class="placeholder">${collection.title}</div>`}
              </div>
              <h3>${collection.title}</h3>
              <p class="vendor">${collection.product_ids.length} Artikel</p>
            </a>
          </article>`,
        )}
      </div>
    </div>
  `;
}

export function collection(snapshot, { collection: coll, products, soldOut, sort, page: pageNumber, pageCount }) {
  const currency = currencyOf(snapshot);
  const sorts = [
    ['manual', 'Empfohlen'],
    ['title-asc', 'Name A–Z'],
    ['price-asc', 'Preis aufsteigend'],
    ['price-desc', 'Preis absteigend'],
    ['created-desc', 'Neueste zuerst'],
  ];

  return html`
    <div class="container">
      <nav class="breadcrumbs"><a href="/">Start</a> / <a href="/collections">Kategorien</a> / ${coll.title}</nav>
      <div class="page-head">
        <h1>${coll.title}</h1>
        ${coll.body_html ? html`<div class="rte">${raw(coll.body_html)}</div>` : ''}
      </div>

      <div class="toolbar">
        <span class="result-count">${products.length} von ${coll.product_ids.length} Artikeln</span>
        <form method="get" action="/collections/${coll.handle}">
          <label class="visually-hidden" for="sort">Sortierung</label>
          <select id="sort" name="sort" onchange="this.form.submit()">
            ${sorts.map(
              ([value, label]) =>
                html`<option value="${value}" ${value === sort ? raw('selected') : ''}>${label}</option>`,
            )}
          </select>
          <noscript><button class="btn btn-sm btn-secondary" type="submit">Sortieren</button></noscript>
        </form>
      </div>

      ${products.length > 0
        ? html`<div class="product-grid">
            ${products.map((product) =>
              productCard(product, { currency, theme: snapshot.theme, soldOut: soldOut.has(product.id) }),
            )}
          </div>`
        : html`<div class="empty-state"><p>In dieser Kategorie sind noch keine Artikel.</p></div>`}

      ${pageCount > 1 ? pagination(`/collections/${coll.handle}`, pageNumber, pageCount, { sort }) : ''}
    </div>
  `;
}

function pagination(basePath, current, pageCount, params = {}) {
  const link = (n) => {
    const query = new URLSearchParams({ ...params, page: String(n) });
    return `${basePath}?${query}`;
  };
  const numbers = [];
  for (let n = 1; n <= pageCount; n += 1) {
    if (n === 1 || n === pageCount || Math.abs(n - current) <= 2) numbers.push(n);
    else if (numbers[numbers.length - 1] !== '…') numbers.push('…');
  }

  return html`<nav class="pagination" aria-label="Seiten">
    ${current > 1 ? html`<a href="${link(current - 1)}" rel="prev">Zurück</a>` : ''}
    ${numbers.map((n) =>
      n === '…'
        ? html`<span>…</span>`
        : n === current
          ? html`<span aria-current="page">${n}</span>`
          : html`<a href="${link(n)}">${n}</a>`,
    )}
    ${current < pageCount ? html`<a href="${link(current + 1)}" rel="next">Weiter</a>` : ''}
  </nav>`;
}

// --- Produktdetail ----------------------------------------------------------

export function product(snapshot, { product: prod, stock, relatedProducts = [] }) {
  const currency = currencyOf(snapshot);
  const { theme } = snapshot;
  const images = prod.images.length > 0 ? prod.images : [{ url: '', alt: prod.title }];
  const firstAvailable = prod.variants.find((v) => (stock[v.id] ?? null) !== 0) || prod.variants[0];
  const allSoldOut = prod.variants.every((v) => stock[v.id] === 0);

  return html`
    <div class="container">
      <nav class="breadcrumbs"><a href="/">Start</a> / <a href="/collections">Kategorien</a> / ${prod.title}</nav>

      <div class="product-layout"
           data-product
           data-variants="${JSON.stringify(
             prod.variants.map((v) => ({
               id: v.id,
               title: v.title,
               price: v.price,
               compare_at_price: v.compare_at_price,
               options: [v.option1, v.option2, v.option3].filter(Boolean),
               image: v.image_url,
               available: stock[v.id] === null ? null : stock[v.id],
             })),
           )}">
        <div class="gallery">
          <div class="main-image">
            ${images[0].url
              ? html`<img src="${images[0].url}" alt="${images[0].alt || prod.title}" data-main-image />`
              : html`<div class="placeholder" style="display:grid;place-items:center;height:100%;color:var(--muted)">Kein Bild</div>`}
          </div>
          ${images.length > 1
            ? html`<div class="thumbs">
                ${images.map(
                  (image, index) => html`<button type="button" data-thumb="${image.url}" aria-current="${index === 0 ? 'true' : 'false'}">
                    <img src="${image.url}" alt="${image.alt || prod.title}" loading="lazy" />
                  </button>`,
                )}
              </div>`
            : ''}
        </div>

        <div class="product-info">
          ${theme.show_vendor && prod.vendor ? html`<p class="vendor">${prod.vendor}</p>` : ''}
          <h1>${prod.title}</h1>
          ${prod.subtitle ? html`<p style="color:var(--muted);font-size:1.0625rem">${prod.subtitle}</p>` : ''}

          <div class="price" data-price>
            <span class="current ${(firstAvailable?.compare_at_price || 0) > (firstAvailable?.price || 0) ? 'on-sale' : ''}">
              ${formatMoney(firstAvailable?.price || 0, currency)}
            </span>
            ${(firstAvailable?.compare_at_price || 0) > (firstAvailable?.price || 0)
              ? html`<span class="compare">${formatMoney(firstAvailable.compare_at_price, currency)}</span>`
              : ''}
          </div>
          <p class="tax-note">inkl. MwSt., zzgl. <a href="/pages/${snapshot.legal.shipping_page || 'versand'}">Versandkosten</a></p>

          <form method="post" action="/cart/add" data-add-form>
            <input type="hidden" name="variant_id" value="${firstAvailable?.id || ''}" data-variant-input />

            ${prod.options.map(
              (option, index) => html`<div class="option-group" data-option-group="${index + 1}">
                <div class="label">${option.name}</div>
                <div class="option-values">
                  ${option.values.map(
                    (value) => html`<button type="button" data-option-value="${value}" data-option-index="${index + 1}"
                      aria-pressed="${firstAvailable && [firstAvailable.option1, firstAvailable.option2, firstAvailable.option3][index] === value ? 'true' : 'false'}">
                      ${value}
                    </button>`,
                  )}
                </div>
                <noscript>
                  <select name="option${index + 1}">
                    ${option.values.map((value) => html`<option value="${value}">${value}</option>`)}
                  </select>
                </noscript>
              </div>`,
            )}

            <p class="stock-note ${allSoldOut ? 'out' : 'in'}" data-stock-note>
              ${allSoldOut ? 'Ausverkauft' : stockLabel(stock[firstAvailable?.id])}
            </p>

            <div class="add-to-cart-row">
              <div class="quantity">
                <button type="button" data-qty="-1" aria-label="Menge verringern">−</button>
                <input type="number" name="quantity" value="1" min="1" max="99" aria-label="Menge" />
                <button type="button" data-qty="1" aria-label="Menge erhöhen">+</button>
              </div>
              <button class="btn" type="submit" ${allSoldOut ? raw('disabled') : ''} data-add-button>
                ${allSoldOut ? 'Ausverkauft' : 'In den Warenkorb'}
              </button>
            </div>
            <div data-add-feedback></div>
          </form>

          ${prod.body_html ? html`<div class="rte">${raw(prod.body_html)}</div>` : ''}

          ${prod.variants.some((v) => v.sku)
            ? html`<p style="font-size:0.8125rem;color:var(--muted)" data-sku>
                Art.-Nr.: ${firstAvailable?.sku || prod.variants[0].sku}
              </p>`
            : ''}
        </div>
      </div>

      ${relatedProducts.length > 0
        ? html`<section class="section">
            <div class="section-head"><h2>Passt dazu</h2></div>
            <div class="product-grid">
              ${relatedProducts.map((related) =>
                productCard(related, { currency, theme, soldOut: false }),
              )}
            </div>
          </section>`
        : ''}
    </div>
  `;
}

function stockLabel(available) {
  if (available === null || available === undefined) return 'Sofort lieferbar';
  if (available <= 0) return 'Ausverkauft';
  if (available <= 5) return `Nur noch ${available} auf Lager`;
  return 'Auf Lager – sofort lieferbar';
}

// --- Warenkorb --------------------------------------------------------------

export function cart(snapshot, { cart: summary, message = '' }) {
  const currency = summary.currency || currencyOf(snapshot);

  if (summary.lines.length === 0) {
    return html`<div class="container">
      <div class="page-head"><h1>Warenkorb</h1></div>
      <div class="empty-state" style="padding-bottom:var(--space-8)">
        <p>Dein Warenkorb ist leer.</p>
        <a class="btn" href="/collections">Weiter einkaufen</a>
      </div>
    </div>`;
  }

  return html`<div class="container">
    <div class="page-head"><h1>Warenkorb</h1></div>
    ${message ? html`<div class="notice info">${message}</div>` : ''}
    ${summary.discount_error ? html`<div class="notice error">${summary.discount_error}</div>` : ''}

    <div class="cart-layout">
      <div>
        ${summary.lines.map(
          (line) => html`<div class="line-item">
            <div class="thumb">
              ${line.image_url ? html`<img src="${line.image_url}" alt="${line.title}" />` : ''}
            </div>
            <div>
              <a href="/products/${line.handle}"><strong>${line.title}</strong></a>
              ${line.variant_title && line.variant_title !== 'Standard'
                ? html`<div class="variant">${line.variant_title}</div>`
                : ''}
              ${line.over_stock
                ? html`<div class="variant" style="color:var(--sale)">Nur noch ${line.available} verfügbar</div>`
                : ''}
              <form method="post" action="/cart/update" style="margin-top:var(--space-2);display:flex;gap:var(--space-3);align-items:center">
                <input type="hidden" name="variant_id" value="${line.variant_id}" />
                <div class="quantity">
                  <button type="submit" name="quantity" value="${line.quantity - 1}" aria-label="Menge verringern">−</button>
                  <input type="number" name="quantity" value="${line.quantity}" min="0" max="99" aria-label="Menge" />
                  <button type="submit" name="quantity" value="${line.quantity + 1}" aria-label="Menge erhöhen">+</button>
                </div>
                <noscript><button class="btn btn-sm btn-secondary" type="submit">Ändern</button></noscript>
              </form>
              <form method="post" action="/cart/remove" style="margin-top:var(--space-2)">
                <input type="hidden" name="variant_id" value="${line.variant_id}" />
                <button class="remove" type="submit">Entfernen</button>
              </form>
            </div>
            <div class="line-total">
              ${formatMoney(line.total, currency)}
              ${line.discount > 0
                ? html`<div class="variant" style="text-align:right">−${formatMoney(line.discount, currency)}</div>`
                : ''}
            </div>
          </div>`,
        )}
      </div>

      <aside class="summary">
        <h2 style="font-size:1.125rem">Zusammenfassung</h2>
        ${summaryRows(summary, currency)}
        <form method="post" action="/cart/discount" class="discount-form">
          <label class="visually-hidden" for="discount">Rabattcode</label>
          <input type="text" id="discount" name="code" value="${summary.discount_code}" placeholder="Rabattcode" />
          <button class="btn btn-sm btn-secondary" type="submit">Einlösen</button>
        </form>
        <a class="btn btn-block" href="/checkout">Zur Kasse</a>
        <p class="summary-note">Versandkosten werden im nächsten Schritt anhand deiner Adresse berechnet.</p>
      </aside>
    </div>
  </div>`;
}

function summaryRows(summary, currency) {
  return html`
    <div class="summary-row"><span>Zwischensumme</span><span>${formatMoney(summary.subtotal, currency)}</span></div>
    ${summary.discount_total > 0
      ? html`<div class="summary-row">
          <span>Rabatt ${summary.discount_code ? html`<span class="muted">(${summary.discount_code})</span>` : ''}</span>
          <span>−${formatMoney(summary.discount_total, currency)}</span>
        </div>`
      : ''}
    <div class="summary-row">
      <span>Versand</span>
      <span>${summary.requires_shipping
        ? summary.shipping_total === 0
          ? 'kostenlos'
          : formatMoney(summary.shipping_total, currency)
        : '—'}</span>
    </div>
    <div class="summary-row total"><span>Gesamt</span><span>${formatMoney(summary.total, currency)}</span></div>
    ${summary.tax_lines.map(
      (tax) => html`<div class="summary-row">
        <span class="muted">enthaltene MwSt. ${(tax.rate_bp / 100).toFixed(0)} %</span>
        <span class="muted">${formatMoney(tax.amount, currency)}</span>
      </div>`,
    )}
  `;
}

// --- Kasse ------------------------------------------------------------------

export function checkout(snapshot, { cart: summary, providers, checkout: settings, error = '', values = {} }) {
  const currency = summary.currency || currencyOf(snapshot);
  const countries = [
    ['DE', 'Deutschland'], ['AT', 'Österreich'], ['CH', 'Schweiz'],
    ['NL', 'Niederlande'], ['BE', 'Belgien'], ['LU', 'Luxemburg'],
    ['FR', 'Frankreich'], ['IT', 'Italien'], ['ES', 'Spanien'], ['PL', 'Polen'],
  ];

  return html`<div class="container">
    <div class="page-head"><h1>Kasse</h1></div>
    ${error ? html`<div class="notice error">${error}</div>` : ''}

    <form method="post" action="/checkout" class="checkout-layout">
      <div>
        <fieldset class="fieldset">
          <legend>Kontakt</legend>
          <div class="field">
            <label for="email">E-Mail-Adresse</label>
            <input type="email" id="email" name="email" required autocomplete="email" value="${values.email || summary.email || ''}" />
            <div class="hint">Hierhin schicken wir die Bestellbestätigung.</div>
          </div>
          <div class="field">
            <label for="phone">Telefon ${settings.require_phone ? '' : '(optional)'}</label>
            <input type="tel" id="phone" name="phone" autocomplete="tel" ${settings.require_phone ? raw('required') : ''} value="${values.phone || ''}" />
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Lieferadresse</legend>
          <div class="field-row">
            <div class="field">
              <label for="first_name">Vorname</label>
              <input type="text" id="first_name" name="shipping_first_name" required autocomplete="given-name" value="${values.shipping_first_name || ''}" />
            </div>
            <div class="field">
              <label for="last_name">Nachname</label>
              <input type="text" id="last_name" name="shipping_last_name" required autocomplete="family-name" value="${values.shipping_last_name || ''}" />
            </div>
          </div>
          <div class="field">
            <label for="company">Firma (optional)</label>
            <input type="text" id="company" name="shipping_company" autocomplete="organization" value="${values.shipping_company || ''}" />
          </div>
          <div class="field">
            <label for="address1">Straße und Hausnummer</label>
            <input type="text" id="address1" name="shipping_address1" required autocomplete="address-line1" value="${values.shipping_address1 || ''}" />
          </div>
          <div class="field">
            <label for="address2">Adresszusatz (optional)</label>
            <input type="text" id="address2" name="shipping_address2" autocomplete="address-line2" value="${values.shipping_address2 || ''}" />
          </div>
          <div class="field-row">
            <div class="field">
              <label for="zip">PLZ</label>
              <input type="text" id="zip" name="shipping_zip" required autocomplete="postal-code" value="${values.shipping_zip || ''}" />
            </div>
            <div class="field">
              <label for="city">Ort</label>
              <input type="text" id="city" name="shipping_city" required autocomplete="address-level2" value="${values.shipping_city || ''}" />
            </div>
          </div>
          <div class="field">
            <label for="country">Land</label>
            <select id="country" name="shipping_country" data-country>
              ${countries.map(
                ([code, name]) =>
                  html`<option value="${code}" ${(values.shipping_country || summary.country) === code ? raw('selected') : ''}>${name}</option>`,
              )}
            </select>
          </div>
        </fieldset>

        ${summary.requires_shipping
          ? html`<fieldset class="fieldset">
              <legend>Versand</legend>
              ${summary.shipping_rates.length > 0
                ? summary.shipping_rates.map(
                    (rate) => html`<label class="choice">
                      <input type="radio" name="shipping_rate_id" value="${rate.id}"
                        ${rate.id === summary.shipping_rate?.id ? raw('checked') : ''} data-shipping-rate />
                      <span>
                        <span class="choice-label">${rate.name}</span>
                        ${rate.delivery_time ? html`<span class="choice-hint">${rate.delivery_time}</span>` : ''}
                        ${rate.free_applied ? html`<span class="choice-hint">Versandkostenfrei erreicht</span>` : ''}
                      </span>
                      <span class="choice-price">${rate.effective_price === 0 ? 'kostenlos' : formatMoney(rate.effective_price, currency)}</span>
                    </label>`,
                  )
                : html`<div class="notice error">In dieses Land liefern wir derzeit nicht.</div>`}
            </fieldset>`
          : ''}

        <fieldset class="fieldset">
          <legend>Zahlung</legend>
          ${providers.length > 0
            ? providers.map(
                (provider, index) => html`<label class="choice">
                  <input type="radio" name="payment_provider" value="${provider.id}" ${index === 0 ? raw('checked') : ''} required />
                  <span>
                    <span class="choice-label">${provider.label}</span>
                    ${provider.instructions ? html`<span class="choice-hint">${provider.instructions}</span>` : ''}
                  </span>
                </label>`,
              )
            : html`<div class="notice error">Es ist keine Zahlart konfiguriert. Bitte im Backend unter Einstellungen → Zahlungen aktivieren.</div>`}
        </fieldset>

        <div class="field">
          <label for="note">Anmerkung zur Bestellung (optional)</label>
          <textarea id="note" name="note" rows="3">${values.note || ''}</textarea>
        </div>

        ${settings.terms_required
          ? html`<label class="checkbox-row">
              <input type="checkbox" name="accept_terms" value="1" required />
              <span>Ich habe die <a href="/pages/${snapshot.legal.terms_page || 'agb'}" target="_blank">AGB</a>
                und die <a href="/pages/${snapshot.legal.withdrawal_page || 'widerruf'}" target="_blank">Widerrufsbelehrung</a> gelesen und akzeptiere sie.</span>
            </label>`
          : ''}
        <label class="checkbox-row">
          <input type="checkbox" name="accepts_marketing" value="1" />
          <span>Ich möchte den Newsletter erhalten (jederzeit abbestellbar).</span>
        </label>

        <button class="btn btn-block" type="submit" ${providers.length === 0 ? raw('disabled') : ''}>
          Zahlungspflichtig bestellen
        </button>
      </div>

      <aside class="summary">
        <h2 style="font-size:1.125rem">Deine Bestellung</h2>
        ${summary.lines.map(
          (line) => html`<div class="summary-row">
            <span>${line.quantity}× ${line.title}
              ${line.variant_title && line.variant_title !== 'Standard' ? html`<span class="muted">(${line.variant_title})</span>` : ''}
            </span>
            <span>${formatMoney(line.total, currency)}</span>
          </div>`,
        )}
        <hr style="border:0;border-top:1px solid var(--border);margin:var(--space-3) 0" />
        ${summaryRows(summary, currency)}
        <p class="summary-note"><a href="/cart">Warenkorb bearbeiten</a></p>
      </aside>
    </form>
  </div>`;
}

// --- Bestellstatus ----------------------------------------------------------

export function orderStatus(snapshot, { order, justPlaced = false }) {
  const currency = order.currency;
  const statusText = {
    paid: 'Bezahlt',
    pending: 'Zahlung ausstehend',
    authorized: 'Zahlung autorisiert',
    refunded: 'Erstattet',
    partially_refunded: 'Teilweise erstattet',
    voided: 'Storniert',
  }[order.financial_status] || order.financial_status;

  const fulfillmentText = {
    unfulfilled: 'Noch nicht versendet',
    partial: 'Teilweise versendet',
    fulfilled: 'Versendet',
  }[order.fulfillment_status];

  const address = order.shipping_address || {};

  return html`<div class="container">
    <div class="order-status">
      ${justPlaced
        ? html`<div class="notice success">
            <strong>Vielen Dank für deine Bestellung!</strong>
            <p style="margin:var(--space-2) 0 0">${snapshot.checkout.thank_you_text}</p>
          </div>`
        : ''}

      <h1>Bestellung ${order.number}</h1>
      <p style="color:var(--muted)">Aufgegeben am ${new Date(order.created_at).toLocaleString('de-DE')}</p>

      <div class="status-pills">
        <span class="pill ${order.financial_status === 'paid' ? 'paid' : order.status === 'cancelled' ? 'cancelled' : 'pending'}">${statusText}</span>
        <span class="pill">${fulfillmentText}</span>
        ${order.status === 'cancelled' ? html`<span class="pill cancelled">Storniert</span>` : ''}
      </div>

      ${order.financial_status === 'pending' && order.payment_provider
        ? html`<div class="notice info">${paymentInstructions(snapshot, order)}</div>`
        : ''}

      ${order.fulfillments.length > 0
        ? html`<div class="notice info">
            <strong>Sendungsverfolgung</strong>
            ${order.fulfillments.map(
              (f) => html`<p style="margin:var(--space-2) 0 0">
                ${f.carrier} ${f.tracking_number}
                ${f.tracking_url ? html` – <a href="${f.tracking_url}" rel="noopener">Sendung verfolgen</a>` : ''}
              </p>`,
            )}
          </div>`
        : ''}

      <div class="data-list">
        <div>
          <h3>Lieferadresse</h3>
          <p>
            ${address.first_name} ${address.last_name}<br />
            ${address.company ? html`${address.company}<br />` : ''}
            ${address.address1}<br />
            ${address.address2 ? html`${address.address2}<br />` : ''}
            ${address.zip} ${address.city}<br />
            ${address.country}
          </p>
        </div>
        <div>
          <h3>Kontakt</h3>
          <p>${order.email}${order.phone ? html`<br />${order.phone}` : ''}</p>
          <h3 style="margin-top:var(--space-4)">Versandart</h3>
          <p>${order.shipping_method || '—'}</p>
        </div>
      </div>

      <h3 style="margin-top:var(--space-6)">Positionen</h3>
      ${order.lines.map(
        (line) => html`<div class="line-item">
          <div class="thumb">${line.image_url ? html`<img src="${line.image_url}" alt="${line.title}" />` : ''}</div>
          <div>
            <strong>${line.title}</strong>
            ${line.variant_title && line.variant_title !== 'Standard' ? html`<div class="variant">${line.variant_title}</div>` : ''}
            <div class="variant">Menge: ${line.quantity}${line.sku ? html` · Art.-Nr. ${line.sku}` : ''}</div>
          </div>
          <div class="line-total">${formatMoney(line.total, currency)}</div>
        </div>`,
      )}

      <div class="summary" style="margin-top:var(--space-5)">
        <div class="summary-row"><span>Zwischensumme</span><span>${formatMoney(order.subtotal, currency)}</span></div>
        ${order.discount_total > 0
          ? html`<div class="summary-row"><span>Rabatt ${order.discount_code}</span><span>−${formatMoney(order.discount_total, currency)}</span></div>`
          : ''}
        <div class="summary-row"><span>Versand</span><span>${order.shipping_total === 0 ? 'kostenlos' : formatMoney(order.shipping_total, currency)}</span></div>
        <div class="summary-row total"><span>Gesamt</span><span>${formatMoney(order.total, currency)}</span></div>
        <div class="summary-row"><span class="muted">enthaltene MwSt.</span><span class="muted">${formatMoney(order.tax_total, currency)}</span></div>
        ${order.refunded_total > 0
          ? html`<div class="summary-row"><span>Erstattet</span><span>−${formatMoney(order.refunded_total, currency)}</span></div>`
          : ''}
      </div>

      <p style="margin-top:var(--space-6)"><a class="btn btn-secondary" href="/">Weiter einkaufen</a></p>
    </div>
  </div>`;
}

function paymentInstructions(snapshot, order) {
  const store = snapshot.store;
  if (order.payment_provider === 'prepayment') {
    return html`<strong>Zahlung per Vorkasse</strong>
      <p style="margin:var(--space-2) 0 0">
        Bitte überweise ${formatMoney(order.total, order.currency)} unter Angabe der Bestellnummer
        <strong>${order.number}</strong> an ${store.address?.company || store.name}.
        Die Bankverbindung steht in deiner Bestellbestätigung.
      </p>`;
  }
  if (order.payment_provider === 'invoice') {
    return html`<strong>Kauf auf Rechnung</strong>
      <p style="margin:var(--space-2) 0 0">Die Rechnung liegt der Sendung bei und ist innerhalb von 14 Tagen zahlbar.</p>`;
  }
  if (order.payment_provider === 'cod') {
    return html`<strong>Nachnahme</strong>
      <p style="margin:var(--space-2) 0 0">Bitte halte den Betrag bei Lieferung bereit.</p>`;
  }
  return html`<strong>Zahlung ausstehend</strong>
    <p style="margin:var(--space-2) 0 0">Sobald die Zahlung eingegangen ist, bereiten wir deine Bestellung vor.</p>`;
}

// --- Inhaltsseiten ----------------------------------------------------------

export function contentPage(snapshot, { page: content }) {
  return html`<div class="container">
    <article class="content-narrow">
      <h1>${content.title}</h1>
      <div class="rte">${raw(content.body_html)}</div>
    </article>
  </div>`;
}

export function blogIndex(snapshot, { posts }) {
  return html`<div class="container">
    <div class="page-head"><h1>Journal</h1></div>
    ${posts.length > 0
      ? html`<div class="post-grid" style="padding-bottom:var(--space-8)">
          ${posts.map(
            (post) => html`<article class="post-card">
              <a href="/blog/${post.handle}">
                <div class="media">
                  ${post.image_url ? html`<img src="${post.image_url}" alt="${post.title}" loading="lazy" />` : ''}
                </div>
                ${post.published_at
                  ? html`<time datetime="${post.published_at}">${new Date(post.published_at).toLocaleDateString('de-DE')}</time>`
                  : ''}
                <h3>${post.title}</h3>
                <p style="color:var(--muted);font-size:0.9375rem">${post.excerpt}</p>
              </a>
            </article>`,
          )}
        </div>`
      : html`<div class="empty-state"><p>Noch keine Beiträge veröffentlicht.</p></div>`}
  </div>`;
}

export function blogPost(snapshot, { post }) {
  return html`<div class="container">
    <article class="content-narrow">
      <nav class="breadcrumbs"><a href="/">Start</a> / <a href="/blog">Journal</a></nav>
      <h1>${post.title}</h1>
      <p style="color:var(--muted)">
        ${post.published_at ? new Date(post.published_at).toLocaleDateString('de-DE') : ''}
        ${post.author ? html` · ${post.author}` : ''}
      </p>
      ${post.image_url ? html`<img src="${post.image_url}" alt="${post.title}" style="border-radius:var(--radius);margin:var(--space-5) 0" />` : ''}
      <div class="rte">${raw(post.body_html)}</div>
    </article>
  </div>`;
}

// --- Suche & Fehler ---------------------------------------------------------

export function search(snapshot, { query, products, soldOut }) {
  const currency = currencyOf(snapshot);
  return html`<div class="container">
    <div class="page-head">
      <h1>Suche</h1>
      <form action="/search" role="search" style="max-width:420px">
        <div class="field">
          <label class="visually-hidden" for="sq">Suchbegriff</label>
          <input type="search" id="sq" name="q" value="${query}" placeholder="Wonach suchst du?" />
        </div>
      </form>
    </div>
    ${query
      ? html`<p class="result-count">${products.length} Treffer für „${query}“</p>`
      : ''}
    ${products.length > 0
      ? html`<div class="product-grid" style="padding-bottom:var(--space-8)">
          ${products.map((p) => productCard(p, { currency, theme: snapshot.theme, soldOut: soldOut.has(p.id) }))}
        </div>`
      : query
        ? html`<div class="empty-state"><p>Keine Treffer. Versuch es mit einem anderen Begriff.</p></div>`
        : ''}
  </div>`;
}

export function notFound(snapshot) {
  return html`<div class="container">
    <div class="empty-state" style="padding:var(--space-8) 0">
      <h1>Seite nicht gefunden</h1>
      <p>Die aufgerufene Adresse existiert nicht (mehr).</p>
      <a class="btn" href="/">Zur Startseite</a>
    </div>
  </div>`;
}

export function notPublished(store) {
  return `<!doctype html><html lang="de"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>${store?.name || 'Shop'} – noch nicht veröffentlicht</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
display:grid;place-items:center;min-height:100vh;margin:0;background:#f7f7f8;color:#16181d}
.box{max-width:480px;padding:40px;background:#fff;border-radius:12px;text-align:center;
box-shadow:0 4px 24px rgba(0,0,0,.06)}
a{color:#16181d}
</style></head><body><div class="box">
<h1>Noch nichts veröffentlicht</h1>
<p>Dieser Shop wurde noch nicht veröffentlicht. Lege im Backend Artikel an und
klicke dort auf <strong>Veröffentlichen</strong>.</p>
<p><a href="/admin">Zum Backend</a></p>
</div></body></html>`;
}
