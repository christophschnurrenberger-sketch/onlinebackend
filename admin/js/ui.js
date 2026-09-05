/*
 * Bausteine der Oberfläche.
 *
 * Kein Framework: das Backend rendert Zeichenketten und hängt Verhalten per
 * Event-Delegation an. Für ein Werkzeug dieser Größe ist das schneller zu
 * laden und leichter zu debuggen als eine Komponentenbibliothek.
 */

// --- Formatierung -----------------------------------------------------------

let currency = 'EUR';
export const setCurrency = (code) => {
  currency = code || 'EUR';
};

export const money = (cents) =>
  new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format((cents || 0) / 100);

/** Cent als reine Zahl für Eingabefelder: 1990 -> "19,90". */
export const moneyInput = (cents) =>
  cents === null || cents === undefined || cents === '' ? '' : ((cents || 0) / 100).toFixed(2).replace('.', ',');

export const date = (iso) => (iso ? new Date(iso).toLocaleDateString('de-DE') : '—');

export const dateTime = (iso) =>
  iso ? new Date(iso).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

export function relativeTime(iso) {
  if (!iso) return '—';
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.round(diff / 60000);
  if (minutes < 1) return 'gerade eben';
  if (minutes < 60) return `vor ${minutes} Min.`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `vor ${hours} Std.`;
  const days = Math.round(hours / 24);
  if (days < 30) return `vor ${days} Tag${days === 1 ? '' : 'en'}`;
  return date(iso);
}

export const number = (value) => new Intl.NumberFormat('de-DE').format(value || 0);

// --- HTML -------------------------------------------------------------------

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (value) =>
  value === null || value === undefined ? '' : String(value).replace(/[&<>"']/g, (c) => ESCAPES[c]);

/** Attributwert für ein Eingabefeld. */
export const attr = (value) => esc(value ?? '');

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

// --- Statusanzeigen ---------------------------------------------------------

export const STATUS_LABELS = {
  draft: 'Entwurf',
  active: 'Aktiv',
  archived: 'Archiviert',
  open: 'Offen',
  cancelled: 'Storniert',
  pending: 'Offen',
  authorized: 'Autorisiert',
  paid: 'Bezahlt',
  partially_refunded: 'Teilerstattet',
  refunded: 'Erstattet',
  voided: 'Verfallen',
  unfulfilled: 'Nicht versendet',
  partial: 'Teilversendet',
  fulfilled: 'Versendet',
};

const STATUS_TONES = {
  active: 'success',
  paid: 'success',
  fulfilled: 'success',
  draft: '',
  pending: 'warning',
  partial: 'warning',
  unfulfilled: 'warning',
  authorized: 'info',
  archived: '',
  cancelled: 'critical',
  voided: 'critical',
  refunded: 'critical',
  partially_refunded: 'warning',
};

export const badge = (status, label) =>
  `<span class="badge ${STATUS_TONES[status] ?? ''}"><span class="dot"></span>${esc(
    label || STATUS_LABELS[status] || status,
  )}</span>`;

// --- Meldungen --------------------------------------------------------------

export function toast(message, tone = '') {
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.append(stack);
  }
  const element = document.createElement('div');
  element.className = `toast ${tone}`;
  element.textContent = message;
  stack.append(element);
  setTimeout(() => element.remove(), tone === 'critical' ? 6000 : 3200);
}

// --- Dialoge ----------------------------------------------------------------

/**
 * Öffnet einen Dialog. `render` liefert den Inhalt, `onSubmit` bekommt die
 * Formulardaten. Gibt onSubmit `false` zurück, bleibt der Dialog offen.
 */
export function modal({ title, body, confirmLabel = 'Speichern', tone = 'primary', wide = false, onSubmit }) {
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop';
  backdrop.innerHTML = `
    <form class="modal ${wide ? 'wide' : ''}">
      <div class="modal-head"><h2>${esc(title)}</h2>
        <button class="btn plain sm" type="button" data-close>✕</button></div>
      <div class="modal-body">${body}</div>
      <div class="modal-foot">
        <button class="btn" type="button" data-close>Abbrechen</button>
        <button class="btn ${tone}" type="submit">${esc(confirmLabel)}</button>
      </div>
    </form>`;

  const close = () => backdrop.remove();
  backdrop.addEventListener('click', (event) => {
    if (event.target === backdrop || event.target.closest('[data-close]')) close();
  });
  backdrop.querySelector('form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target));
    const submit = event.target.querySelector('[type=submit]');
    submit.disabled = true;
    try {
      const result = await onSubmit(data, event.target);
      if (result !== false) close();
    } catch (error) {
      toast(error.message, 'critical');
    } finally {
      submit.disabled = false;
    }
  });

  document.body.append(backdrop);
  backdrop.querySelector('input, select, textarea')?.focus();
  return { close };
}

export function confirmDialog({ title, message, confirmLabel = 'Löschen', onConfirm }) {
  return modal({
    title,
    body: `<p>${esc(message)}</p>`,
    confirmLabel,
    tone: 'critical',
    onSubmit: onConfirm,
  });
}

// --- Formularbausteine ------------------------------------------------------

export const field = ({ label, name, value = '', type = 'text', hint = '', placeholder = '', required = false, attrs = '' }) => `
  <div class="field">
    <label for="f-${name}">${esc(label)}</label>
    <input type="${type}" id="f-${name}" name="${name}" value="${attr(value)}"
      placeholder="${attr(placeholder)}" ${required ? 'required' : ''} ${attrs} />
    ${hint ? `<div class="hint">${esc(hint)}</div>` : ''}
  </div>`;

export const textarea = ({ label, name, value = '', rows = 4, hint = '', className = '' }) => `
  <div class="field">
    <label for="f-${name}">${esc(label)}</label>
    <textarea id="f-${name}" name="${name}" rows="${rows}" class="${className}">${esc(value)}</textarea>
    ${hint ? `<div class="hint">${esc(hint)}</div>` : ''}
  </div>`;

export const select = ({ label, name, value = '', options = [], hint = '' }) => `
  <div class="field">
    <label for="f-${name}">${esc(label)}</label>
    <select id="f-${name}" name="${name}">
      ${options
        .map(([optionValue, optionLabel]) =>
          `<option value="${attr(optionValue)}" ${String(optionValue) === String(value) ? 'selected' : ''}>${esc(optionLabel)}</option>`,
        )
        .join('')}
    </select>
    ${hint ? `<div class="hint">${esc(hint)}</div>` : ''}
  </div>`;

export const checkbox = ({ label, name, checked = false, hint = '' }) => `
  <div class="field-inline">
    <input type="checkbox" id="f-${name}" name="${name}" ${checked ? 'checked' : ''} />
    <label for="f-${name}" style="margin:0">${esc(label)}</label>
  </div>
  ${hint ? `<div class="hint" style="margin:-8px 0 12px 24px">${esc(hint)}</div>` : ''}`;

export const moneyField = ({ label, name, value = '', hint = '', symbol = '€' }) => `
  <div class="field">
    <label for="f-${name}">${esc(label)}</label>
    <div class="input-prefix" data-prefix="${esc(symbol)}">
      <input type="text" id="f-${name}" name="${name}" value="${attr(moneyInput(value))}" inputmode="decimal" placeholder="0,00" />
    </div>
    ${hint ? `<div class="hint">${esc(hint)}</div>` : ''}
  </div>`;

export const colorField = ({ label, name, value = '#000000' }) => `
  <div class="field">
    <label for="f-${name}">${esc(label)}</label>
    <div class="color-field">
      <input type="color" value="${attr(/^#[0-9a-f]{6}$/i.test(value) ? value : '#000000')}"
        oninput="this.nextElementSibling.value = this.value" />
      <input type="text" id="f-${name}" name="${name}" value="${attr(value)}" />
    </div>
  </div>`;

export const emptyState = (title, message, action = '') => `
  <div class="empty"><h3>${esc(title)}</h3><p>${esc(message)}</p>${action}</div>`;

export const cardSection = (title, body, actions = '') => `
  <section class="card">
    ${title ? `<div class="card-head"><h2>${esc(title)}</h2>${actions}</div>` : ''}
    <div class="card-body">${body}</div>
  </section>`;

/** Formulardaten in ein Objekt, Checkboxen als Boolean. */
export function formValues(form) {
  const data = Object.fromEntries(new FormData(form));
  for (const input of form.querySelectorAll('input[type=checkbox]')) {
    data[input.name] = input.checked;
  }
  return data;
}
