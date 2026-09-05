/*
 * Einstellungen.
 *
 * In Reiter geteilt, weil die Bereiche unterschiedliche Leute betreffen:
 * Stammdaten und Rechtliches der Inhaber, Zahlungen und Versand die Buchhaltung,
 * Benutzer die Administration.
 */

import { api } from '../api.js';
import {
  esc, attr, money, moneyInput, badge, toast, modal, confirmDialog,
  field, textarea, select, checkbox, moneyField, formValues, dateTime,
} from '../ui.js';
import { refreshSession } from '../app.js';

const TABS = [
  ['store', 'Shop-Daten'],
  ['checkout', 'Kasse'],
  ['payments', 'Zahlungen'],
  ['shipping', 'Versand'],
  ['taxes', 'Steuern'],
  ['legal', 'Rechtliches'],
  ['users', 'Benutzer'],
];

export async function settingsView(root, params) {
  const active = TABS.some(([key]) => key === params.tab) ? params.tab : 'store';
  let data = await api.get('/settings');

  const reload = async () => {
    data = await api.get('/settings');
    paint();
  };

  const paint = () => {
    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Einstellungen</h1></div>
      </div>
      <section class="card">
        <div class="tabs">
          ${TABS.map(([key, label]) =>
            `<a class="tab ${active === key ? 'active' : ''}" href="#/settings/${key}">${esc(label)}</a>`).join('')}
        </div>
        <div class="card-body" data-panel></div>
      </section>`;

    const panel = root.querySelector('[data-panel]');
    ({
      store: storePanel, checkout: checkoutPanel, payments: paymentsPanel,
      shipping: shippingPanel, taxes: taxesPanel, legal: legalPanel, users: usersPanel,
    })[active](panel, data, reload);
  };

  paint();
}

// --- Shop-Daten -------------------------------------------------------------

function storePanel(panel, data, reload) {
  const store = data.settings.store;
  panel.innerHTML = `
    <form data-form>
      <div class="field-row">
        ${field({ label: 'Shopname', name: 'name', value: store.name, required: true })}
        ${field({ label: 'Slogan', name: 'tagline', value: store.tagline })}
      </div>
      ${textarea({ label: 'Kurzbeschreibung', name: 'description', value: store.description, rows: 2,
        hint: 'Erscheint als Standardbeschreibung bei Suchmaschinen und in sozialen Netzwerken.' })}
      <div class="field-row">
        ${field({ label: 'E-Mail', name: 'email', value: store.email, type: 'email' })}
        ${field({ label: 'Telefon', name: 'phone', value: store.phone })}
      </div>
      <div class="field-row">
        ${select({ label: 'Währung', name: 'currency', value: store.currency,
          options: [['EUR', 'Euro (€)'], ['CHF', 'Schweizer Franken'], ['USD', 'US-Dollar'], ['GBP', 'Britisches Pfund']] })}
        ${select({ label: 'Sprache / Format', name: 'locale', value: store.locale,
          options: [['de-DE', 'Deutsch (Deutschland)'], ['de-AT', 'Deutsch (Österreich)'], ['de-CH', 'Deutsch (Schweiz)'], ['en-GB', 'Englisch']] })}
      </div>
      <div class="field-row">
        ${field({ label: 'Logo (URL)', name: 'logo_url', value: store.logo_url })}
        ${field({ label: 'Favicon (URL)', name: 'favicon_url', value: store.favicon_url })}
      </div>

      <h3 style="margin:20px 0 10px">Anschrift</h3>
      ${field({ label: 'Firma', name: 'address_company', value: store.address.company })}
      ${field({ label: 'Straße und Hausnummer', name: 'address_address1', value: store.address.address1 })}
      <div class="field-row">
        ${field({ label: 'PLZ', name: 'address_zip', value: store.address.zip })}
        ${field({ label: 'Ort', name: 'address_city', value: store.address.city })}
      </div>
      ${field({ label: 'Land (2 Buchstaben)', name: 'address_country', value: store.address.country })}

      <h3 style="margin:20px 0 10px">Soziale Netzwerke</h3>
      <div class="field-row">
        ${field({ label: 'Instagram', name: 'social_instagram', value: store.social.instagram })}
        ${field({ label: 'Facebook', name: 'social_facebook', value: store.social.facebook })}
      </div>
      <div class="field-row">
        ${field({ label: 'TikTok', name: 'social_tiktok', value: store.social.tiktok })}
        ${field({ label: 'YouTube', name: 'social_youtube', value: store.social.youtube })}
      </div>

      <button class="btn primary" type="submit">Speichern</button>
    </form>`;

  bindForm(panel, async (values) => {
    await api.put('/settings/store', {
      name: values.name, tagline: values.tagline, description: values.description,
      email: values.email, phone: values.phone, currency: values.currency, locale: values.locale,
      logo_url: values.logo_url, favicon_url: values.favicon_url,
      address: {
        company: values.address_company, address1: values.address_address1,
        zip: values.address_zip, city: values.address_city,
        country: String(values.address_country || 'DE').toUpperCase(),
      },
      social: {
        instagram: values.social_instagram, facebook: values.social_facebook,
        tiktok: values.social_tiktok, youtube: values.social_youtube,
      },
    });
    await refreshSession();
    reload();
  });
}

// --- Kasse ------------------------------------------------------------------

function checkoutPanel(panel, data, reload) {
  const checkout = data.settings.checkout;
  panel.innerHTML = `
    <form data-form>
      ${checkbox({ label: 'Bestellung ohne Kundenkonto erlauben', name: 'guest_checkout', checked: checkout.guest_checkout })}
      ${checkbox({ label: 'Telefonnummer verlangen', name: 'require_phone', checked: checkout.require_phone })}
      ${checkbox({ label: 'Zustimmung zu AGB und Widerruf verlangen', name: 'terms_required', checked: checkout.terms_required })}
      ${checkbox({ label: 'Preise sind Bruttopreise (MwSt. enthalten)', name: 'prices_include_tax', checked: checkout.prices_include_tax })}

      <div class="field-row" style="margin-top:16px">
        ${field({ label: 'Erste Bestellnummer', name: 'order_number_start', type: 'number',
          value: checkout.order_number_start, hint: 'Gilt nur, solange es noch keine Bestellungen gibt.' })}
        ${moneyField({ label: 'Mindestbestellwert', name: 'min_order_total', value: checkout.min_order_total,
          hint: '0 bedeutet: kein Mindestwert.' })}
      </div>
      ${textarea({ label: 'Text auf der Dankeseite', name: 'thank_you_text', value: checkout.thank_you_text, rows: 3 })}
      <button class="btn primary" type="submit">Speichern</button>
    </form>`;

  bindForm(panel, async (values) => {
    await api.put('/settings/checkout', {
      guest_checkout: values.guest_checkout,
      require_phone: values.require_phone,
      terms_required: values.terms_required,
      prices_include_tax: values.prices_include_tax,
      order_number_start: Number(values.order_number_start) || 1000,
      min_order_total: parseMoneyInput(values.min_order_total),
      thank_you_text: values.thank_you_text,
    });
    reload();
  });
}

// --- Zahlungen --------------------------------------------------------------

function paymentsPanel(panel, data, reload) {
  const payments = data.settings.payments;
  const providers = data.payment_providers.all;
  const enabled = new Set(payments.enabled || []);

  panel.innerHTML = `
    <div class="banner info"><div class="banner-body">
      <strong>Zugangsdaten liegen in der Umgebung, nicht in der Datenbank</strong>
      Stripe und PayPal werden über <code>.env</code> konfiguriert
      (<code>STRIPE_SECRET_KEY</code>, <code>PAYPAL_CLIENT_ID</code> …). Erst wenn die Schlüssel
      gesetzt sind, lässt sich die Zahlart hier aktivieren.
    </div></div>

    <form data-form>
      ${providers.map((provider) => `
        <div class="option-editor">
          <div class="field-inline" style="justify-content:space-between;margin-bottom:8px">
            <label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600">
              <input type="checkbox" name="enabled_${provider.id}" ${enabled.has(provider.id) ? 'checked' : ''}
                ${provider.configured ? '' : 'disabled'} />
              ${esc(provider.label)}
            </label>
            ${provider.configured ? badge('active', 'Einsatzbereit') : badge('draft', 'Nicht konfiguriert')}
          </div>
          ${field({ label: 'Beschriftung im Checkout', name: `label_${provider.id}`,
            value: payments[provider.id]?.label || provider.label })}
          ${['invoice', 'prepayment', 'cod', 'mock'].includes(provider.id)
            ? textarea({ label: 'Hinweis für Kunden', name: `instructions_${provider.id}`,
                value: payments[provider.id]?.instructions || '', rows: 2 })
            : ''}
        </div>`).join('')}
      <button class="btn primary" type="submit">Speichern</button>
    </form>

    <h3 style="margin:24px 0 10px">Webhook-Adressen</h3>
    <p class="hint">Diese URLs beim jeweiligen Anbieter eintragen, damit Zahlungen auch dann
      ankommen, wenn der Kunde den Browser vorzeitig schließt.</p>
    <table><tbody>
      <tr><td>Stripe</td><td><code>${esc(location.origin)}/webhooks/stripe</code></td></tr>
      <tr><td>PayPal</td><td><code>${esc(location.origin)}/webhooks/paypal</code></td></tr>
    </tbody></table>`;

  bindForm(panel, async (values) => {
    const payload = { enabled: providers.filter((p) => values[`enabled_${p.id}`]).map((p) => p.id) };
    for (const provider of providers) {
      payload[provider.id] = {
        label: values[`label_${provider.id}`],
        ...(values[`instructions_${provider.id}`] !== undefined
          ? { instructions: values[`instructions_${provider.id}`] }
          : {}),
      };
    }
    await api.put('/settings/payments', payload);
    reload();
  });
}

// --- Versand ----------------------------------------------------------------

function shippingPanel(panel, data, reload) {
  panel.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <p class="hint" style="margin:0">Eine Zone ohne Länder gilt als Auffangzone für alle übrigen Länder.</p>
      <button class="btn" data-add-zone>Zone hinzufügen</button>
    </div>
    ${data.shipping_zones.length === 0
      ? '<p class="hint">Noch keine Versandzone – ohne Zone kann niemand bestellen.</p>'
      : data.shipping_zones.map((zone) => `
        <div class="option-editor">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div><strong>${esc(zone.name)}</strong>
              <div class="cell-sub">${zone.countries.length > 0 ? esc(zone.countries.join(', ')) : 'alle übrigen Länder'}</div></div>
            <div class="btn-group">
              <button class="btn sm" data-edit-zone="${zone.id}">Bearbeiten</button>
              <button class="btn sm plain critical" data-delete-zone="${zone.id}">✕</button>
            </div>
          </div>
          <table><tbody>
            ${zone.rates.map((rate) => `
              <tr><td>${esc(rate.name)}${rate.delivery_time ? `<div class="cell-sub">${esc(rate.delivery_time)}</div>` : ''}</td>
                  <td class="cell-sub">${rate.free_over !== null ? `kostenlos ab ${money(rate.free_over)}` : ''}</td>
                  <td class="num"><strong>${money(rate.price)}</strong></td></tr>`).join('')
              || '<tr><td class="cell-sub">Keine Versandart – in diese Zone kann nicht bestellt werden.</td></tr>'}
          </tbody></table>
        </div>`).join('')}`;

  panel.querySelector('[data-add-zone]')?.addEventListener('click', () => zoneDialog(null, reload));
  panel.querySelectorAll('[data-edit-zone]').forEach((button) =>
    button.addEventListener('click', () =>
      zoneDialog(data.shipping_zones.find((z) => z.id === Number(button.dataset.editZone)), reload),
    ),
  );
  panel.querySelectorAll('[data-delete-zone]').forEach((button) =>
    button.addEventListener('click', () =>
      confirmDialog({
        title: 'Zone löschen',
        message: 'Die Zone und ihre Versandarten werden gelöscht.',
        onConfirm: async () => {
          await api.delete(`/shipping/zones/${button.dataset.deleteZone}`);
          toast('Zone gelöscht');
          reload();
        },
      }),
    ),
  );
}

function zoneDialog(zone, onDone) {
  const rates = zone?.rates || [{ name: 'Standardversand', price: 0, delivery_time: '', free_over: null }];
  modal({
    title: zone ? `Zone ${zone.name}` : 'Neue Versandzone',
    wide: true,
    body: `
      ${field({ label: 'Name der Zone', name: 'name', value: zone?.name || '', required: true, placeholder: 'Deutschland' })}
      ${field({ label: 'Länder (Kürzel, kommagetrennt)', name: 'countries',
        value: (zone?.countries || []).join(', '), placeholder: 'DE, AT',
        hint: 'Leer lassen für „alle übrigen Länder“.' })}
      <h3 style="margin:16px 0 8px">Versandarten</h3>
      <div data-rates>
        ${rates.map((rate, index) => rateRow(rate, index)).join('')}
      </div>
      <button class="btn sm" type="button" data-add-rate>Versandart hinzufügen</button>`,
    confirmLabel: 'Speichern',
    onSubmit: async (values, form) => {
      const collected = [...form.querySelectorAll('[data-rate-row]')].map((row) => ({
        name: row.querySelector('[data-rate-name]').value,
        price: row.querySelector('[data-rate-price]').value,
        delivery_time: row.querySelector('[data-rate-time]').value,
        free_over: row.querySelector('[data-rate-free]').value || null,
      })).filter((rate) => rate.name.trim());

      const payload = {
        name: values.name,
        countries: String(values.countries || '').split(',').map((c) => c.trim()).filter(Boolean),
        rates: collected,
      };
      if (zone) await api.put(`/shipping/zones/${zone.id}`, payload);
      else await api.post('/shipping/zones', payload);
      toast('Versandzone gespeichert');
      onDone();
    },
  });

  // Der Dialog liegt bereits im DOM, wenn modal() zurückkehrt.
  const backdrop = document.querySelector('.modal-backdrop:last-of-type');
  backdrop.querySelector('[data-add-rate]')?.addEventListener('click', () => {
    const list = backdrop.querySelector('[data-rates]');
    list.insertAdjacentHTML('beforeend', rateRow({ name: '', price: 0, delivery_time: '', free_over: null }, list.children.length));
  });
}

const rateRow = (rate, index) => `
  <div class="field-row" data-rate-row style="grid-template-columns:1.4fr 0.8fr 1fr 1fr;margin-bottom:8px">
    <input type="text" data-rate-name value="${attr(rate.name)}" placeholder="Standardversand"
      style="padding:8px 11px;border:1px solid var(--border-strong);border-radius:6px;font:inherit" />
    <input type="text" data-rate-price value="${attr(moneyInput(rate.price))}" placeholder="4,90"
      style="padding:8px 11px;border:1px solid var(--border-strong);border-radius:6px;font:inherit" />
    <input type="text" data-rate-time value="${attr(rate.delivery_time)}" placeholder="2–3 Werktage"
      style="padding:8px 11px;border:1px solid var(--border-strong);border-radius:6px;font:inherit" />
    <input type="text" data-rate-free value="${attr(rate.free_over === null ? '' : moneyInput(rate.free_over))}"
      placeholder="frei ab …"
      style="padding:8px 11px;border:1px solid var(--border-strong);border-radius:6px;font:inherit" />
  </div>`;

// --- Steuern ----------------------------------------------------------------

function taxesPanel(panel, data, reload) {
  panel.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <p class="hint" style="margin:0">Der Standardsatz gilt für alle Artikel ohne eigenen Steuersatz.</p>
      <button class="btn" data-add-tax>Steuersatz hinzufügen</button>
    </div>
    <table>
      <thead><tr><th>Name</th><th>Satz</th><th>Land</th><th>Standard</th><th></th></tr></thead>
      <tbody>${data.tax_rates.map((rate) => `
        <tr><td>${esc(rate.name)}</td>
            <td>${(rate.rate_bp / 100).toLocaleString('de-DE')} %</td>
            <td>${esc(rate.country)}</td>
            <td>${rate.is_default ? badge('active', 'Standard') : ''}</td>
            <td class="num"><button class="btn sm plain critical" data-delete-tax="${rate.id}">✕</button></td>
        </tr>`).join('')}</tbody></table>`;

  panel.querySelector('[data-add-tax]')?.addEventListener('click', () =>
    modal({
      title: 'Steuersatz hinzufügen',
      body: `${field({ label: 'Name', name: 'name', required: true, placeholder: 'Standard (19 %)' })}
        ${field({ label: 'Satz in Prozent', name: 'rate', required: true, placeholder: '19' })}
        ${field({ label: 'Land (2 Buchstaben)', name: 'country', value: 'DE' })}
        ${checkbox({ label: 'Als Standardsatz verwenden', name: 'is_default' })}`,
      onSubmit: async (values, form) => {
        await api.post('/tax-rates', formValues(form));
        toast('Steuersatz gespeichert');
        reload();
      },
    }),
  );

  panel.querySelectorAll('[data-delete-tax]').forEach((button) =>
    button.addEventListener('click', () =>
      confirmDialog({
        title: 'Steuersatz löschen',
        message: 'Artikel mit diesem Satz fallen auf den Standardsatz zurück.',
        onConfirm: async () => {
          await api.delete(`/tax-rates/${button.dataset.deleteTax}`);
          toast('Steuersatz gelöscht');
          reload();
        },
      }),
    ),
  );
}

// --- Rechtliches ------------------------------------------------------------

function legalPanel(panel, data, reload) {
  const legal = data.settings.legal;
  const store = data.settings.store;
  panel.innerHTML = `
    <p class="hint">Welche Seite hinter welchem Pflichtlink steht. Die Seiten selbst liegen
      unter <a href="#/pages">Inhalte → Seiten</a>.</p>
    <form data-form>
      <div class="field-row">
        ${field({ label: 'Impressum', name: 'imprint_page', value: legal.imprint_page })}
        ${field({ label: 'Datenschutz', name: 'privacy_page', value: legal.privacy_page })}
      </div>
      <div class="field-row">
        ${field({ label: 'AGB', name: 'terms_page', value: legal.terms_page })}
        ${field({ label: 'Widerruf', name: 'withdrawal_page', value: legal.withdrawal_page })}
      </div>
      ${field({ label: 'Versand & Zahlung', name: 'shipping_page', value: legal.shipping_page })}

      <h3 style="margin:20px 0 10px">Angaben für das Impressum</h3>
      <div class="field-row">
        ${field({ label: 'Umsatzsteuer-ID', name: 'vat_id', value: store.legal.vat_id })}
        ${field({ label: 'Handelsregister', name: 'register', value: store.legal.register })}
      </div>
      ${field({ label: 'Geschäftsführung', name: 'managing_director', value: store.legal.managing_director })}
      <button class="btn primary" type="submit">Speichern</button>
    </form>`;

  bindForm(panel, async (values) => {
    await api.put('/settings/legal', {
      imprint_page: values.imprint_page, privacy_page: values.privacy_page,
      terms_page: values.terms_page, withdrawal_page: values.withdrawal_page,
      shipping_page: values.shipping_page,
    });
    await api.put('/settings/store', {
      legal: { vat_id: values.vat_id, register: values.register, managing_director: values.managing_director },
    });
    reload();
  });
}

// --- Benutzer ---------------------------------------------------------------

async function usersPanel(panel, data, reload) {
  panel.innerHTML = '<div class="skeleton" style="width:30%"></div>';
  const { items } = await api.get('/users');

  panel.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <p class="hint" style="margin:0">Inhaber und Admins dürfen Einstellungen ändern, Mitarbeiter nur Inhalte pflegen.</p>
      <button class="btn" data-add-user>Benutzer hinzufügen</button>
    </div>
    <table>
      <thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Zuletzt angemeldet</th><th></th></tr></thead>
      <tbody>${items.map((user) => `
        <tr><td>${esc(user.name || '—')}</td>
            <td>${esc(user.email)}</td>
            <td>${badge(user.role === 'owner' ? 'active' : '', ROLE_LABELS[user.role] || user.role)}</td>
            <td class="cell-sub">${user.last_login_at ? esc(dateTime(user.last_login_at)) : 'nie'}</td>
            <td class="num">
              <button class="btn sm plain" data-password="${user.id}">Passwort</button>
              <button class="btn sm plain critical" data-delete-user="${user.id}">✕</button>
            </td>
        </tr>`).join('')}</tbody></table>`;

  panel.querySelector('[data-add-user]')?.addEventListener('click', () =>
    modal({
      title: 'Benutzer hinzufügen',
      body: `${field({ label: 'Name', name: 'name' })}
        ${field({ label: 'E-Mail', name: 'email', type: 'email', required: true })}
        ${field({ label: 'Passwort', name: 'password', type: 'password', required: true,
          hint: 'Mindestens 8 Zeichen.' })}
        ${select({ label: 'Rolle', name: 'role', options: Object.entries(ROLE_LABELS).filter(([key]) => key !== 'owner') })}`,
      onSubmit: async (values) => {
        await api.post('/users', values);
        toast('Benutzer angelegt');
        reload();
      },
    }),
  );

  panel.querySelectorAll('[data-password]').forEach((button) =>
    button.addEventListener('click', () =>
      modal({
        title: 'Passwort ändern',
        body: field({ label: 'Neues Passwort', name: 'password', type: 'password', required: true,
          hint: 'Mindestens 8 Zeichen.' }),
        onSubmit: async (values) => {
          await api.post(`/users/${button.dataset.password}/password`, values);
          toast('Passwort geändert');
        },
      }),
    ),
  );

  panel.querySelectorAll('[data-delete-user]').forEach((button) =>
    button.addEventListener('click', () =>
      confirmDialog({
        title: 'Benutzer löschen',
        message: 'Der Zugang wird sofort entzogen.',
        onConfirm: async () => {
          await api.delete(`/users/${button.dataset.deleteUser}`);
          toast('Benutzer gelöscht');
          reload();
        },
      }),
    ),
  );
}

const ROLE_LABELS = { owner: 'Inhaber', admin: 'Administrator', staff: 'Mitarbeiter' };

// --- Gemeinsames ------------------------------------------------------------

function bindForm(panel, onSubmit) {
  const form = panel.querySelector('[data-form]');
  if (!form) return;
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = form.querySelector('[type=submit]');
    submit.disabled = true;
    try {
      await onSubmit(formValues(form));
      toast('Gespeichert');
    } catch (error) {
      toast(error.message, 'critical');
    } finally {
      submit.disabled = false;
    }
  });
}

const parseMoneyInput = (input) => {
  const value = parseFloat(String(input || '0').replace(/\./g, '').replace(',', '.'));
  return Number.isFinite(value) ? Math.round(value * 100) : 0;
};
