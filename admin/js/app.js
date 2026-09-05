/*
 * Backend-Anwendung.
 *
 * Hash-Router mit einem Layout-Rahmen: die Seitennavigation bleibt beim
 * Wechseln stehen, nur der Inhaltsbereich wird neu gerendert. Jede View ist
 * eine Funktion, die HTML zurückgibt und danach ihre Ereignisse anbindet.
 */

import { api, setCsrfToken } from './api.js';
import { $, esc, setCurrency, relativeTime } from './ui.js';
import { loginView } from './views/login.js';
import { dashboardView } from './views/dashboard.js';
import { productsView, productEditorView } from './views/products.js';
import { collectionsView, collectionEditorView } from './views/collections.js';
import { inventoryView } from './views/inventory.js';
import { ordersView, orderDetailView } from './views/orders.js';
import { customersView, customerDetailView } from './views/customers.js';
import { discountsView } from './views/discounts.js';
import { contentView, contentEditorView } from './views/content.js';
import { navigationView } from './views/navigation.js';
import { themeView } from './views/theme.js';
import { publishView } from './views/publish.js';
import { settingsView } from './views/settings.js';

export const state = {
  user: null,
  store: null,
  publishing: null,
  pendingCount: 0,
};

// --- Routen -----------------------------------------------------------------

const ROUTES = [
  ['', dashboardView],
  ['products', productsView],
  ['products/new', productEditorView],
  ['products/:id', productEditorView],
  ['collections', collectionsView],
  ['collections/new', collectionEditorView],
  ['collections/:id', collectionEditorView],
  ['inventory', inventoryView],
  ['orders', ordersView],
  ['orders/:id', orderDetailView],
  ['customers', customersView],
  ['customers/:id', customerDetailView],
  ['discounts', discountsView],
  ['pages', contentView],
  ['pages/new', contentEditorView],
  ['pages/:id', contentEditorView],
  ['posts', contentView],
  ['posts/new', contentEditorView],
  ['posts/:id', contentEditorView],
  ['navigation', navigationView],
  ['theme', themeView],
  ['publish', publishView],
  ['settings', settingsView],
  ['settings/:tab', settingsView],
];

const NAV = [
  { group: '', items: [
    { path: '', label: 'Übersicht', icon: '◧' },
    { path: 'orders', label: 'Bestellungen', icon: '▤', badge: 'orders' },
    { path: 'products', label: 'Artikel', icon: '▦' },
    { path: 'customers', label: 'Kunden', icon: '☺' },
    { path: 'discounts', label: 'Rabatte', icon: '%' },
  ]},
  { group: 'Katalog', items: [
    { path: 'collections', label: 'Kategorien', icon: '▩' },
    { path: 'inventory', label: 'Bestand', icon: '▣' },
  ]},
  { group: 'Onlineshop', items: [
    { path: 'theme', label: 'Design', icon: '◑' },
    { path: 'pages', label: 'Seiten', icon: '▭' },
    { path: 'posts', label: 'Journal', icon: '✎' },
    { path: 'navigation', label: 'Navigation', icon: '⌘' },
    { path: 'publish', label: 'Veröffentlichen', icon: '↑', badge: 'pending' },
  ]},
  { group: '', items: [
    { path: 'settings', label: 'Einstellungen', icon: '⚙' },
  ]},
];

function matchRoute(path) {
  for (const [pattern, view] of ROUTES) {
    const patternParts = pattern.split('/').filter(Boolean);
    const pathParts = path.split('/').filter(Boolean);
    if (patternParts.length !== pathParts.length) continue;

    const params = {};
    let matched = true;
    for (let i = 0; i < patternParts.length; i += 1) {
      if (patternParts[i].startsWith(':')) params[patternParts[i].slice(1)] = pathParts[i];
      else if (patternParts[i] !== pathParts[i]) { matched = false; break; }
    }
    if (matched) return { view, params, path };
  }
  return null;
}

// --- Rendern ----------------------------------------------------------------

const app = () => document.getElementById('app');

export function navigate(path) {
  window.location.hash = `#/${path}`;
}

export async function render() {
  if (!state.user) return renderLogin();

  const path = window.location.hash.replace(/^#\/?/, '').split('?')[0];
  const route = matchRoute(path);

  app().className = '';
  app().innerHTML = layout(path);
  bindShell();

  const outlet = $('#outlet');
  if (!route) {
    outlet.innerHTML = `<div class="empty"><h3>Seite nicht gefunden</h3>
      <p>Der Bereich „${esc(path)}“ existiert nicht.</p></div>`;
    return;
  }

  outlet.innerHTML = '<div class="card"><div class="card-body"><div class="skeleton" style="width:40%"></div></div></div>';
  try {
    await route.view(outlet, { ...route.params, section: path.split('/')[0] });
  } catch (error) {
    if (error.status === 401) return logout();
    outlet.innerHTML = `<div class="banner critical"><div class="banner-body">
      <strong>Das hat nicht geklappt</strong>${esc(error.message)}</div></div>`;
  }
}

function layout(path) {
  const section = path.split('/')[0];
  const initial = (state.store?.name || 'S').slice(0, 1).toUpperCase();
  const pending = state.pendingCount;

  return `
  <div class="layout">
    <aside class="sidebar" id="sidebar">
      <div class="store">
        <div class="avatar">${esc(initial)}</div>
        <div style="min-width:0">
          <div class="name">${esc(state.store?.name || 'Shop')}</div>
          <div class="role">${esc(state.user.name || state.user.email)}</div>
        </div>
      </div>
      ${NAV.map((group) => `
        <div class="nav-group">
          ${group.group ? `<div class="group-label">${esc(group.group)}</div>` : ''}
          ${group.items.map((item) => `
            <a class="nav-link ${section === item.path || (item.path === '' && path === '') ? 'active' : ''}"
               href="#/${item.path}">
              <span class="icon">${item.icon}</span>${esc(item.label)}
              ${item.badge === 'pending' && pending > 0 ? `<span class="count">${pending}</span>` : ''}
            </a>`).join('')}
        </div>`).join('')}
      <div class="spacer"></div>
      <div class="footer-links">
        <a href="/" target="_blank" rel="noopener">Shop ansehen ↗</a><br />
        <a href="#/" data-logout>Abmelden</a>
      </div>
    </aside>

    <div>
      <header class="topbar">
        <button class="btn plain sm menu-toggle" data-menu-toggle aria-label="Menü">☰</button>
        <div class="search">
          <input type="search" placeholder="Artikel, Bestellungen, Kunden suchen …" data-global-search />
        </div>
        <div class="actions">
          <div class="publish-state">
            ${pending > 0
              ? `<strong>${pending} Änderung${pending === 1 ? '' : 'en'} nicht veröffentlicht</strong>`
              : 'Alles veröffentlicht'}
            <br />${state.publishing?.last_published_at
              ? `zuletzt ${esc(relativeTime(state.publishing.last_published_at))}`
              : 'noch nie veröffentlicht'}
          </div>
          <button class="btn publish" data-publish>Veröffentlichen</button>
        </div>
      </header>
      <main class="content" id="outlet"></main>
    </div>
  </div>`;
}

function bindShell() {
  $('[data-menu-toggle]')?.addEventListener('click', () => {
    $('#sidebar').classList.toggle('open');
  });

  $('[data-logout]')?.addEventListener('click', async (event) => {
    event.preventDefault();
    await logout();
  });

  $('[data-publish]')?.addEventListener('click', () => navigate('publish'));

  // Die globale Suche schickt in den passenden Bereich weiter, statt eine
  // eigene Trefferliste zu bauen – die Listen können bereits suchen.
  $('[data-global-search]')?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    const term = event.target.value.trim();
    if (!term) return;
    const target = /^\d+$/.test(term) ? 'orders' : 'products';
    navigate(`${target}?search=${encodeURIComponent(term)}`);
  });

  document.querySelectorAll('.nav-link').forEach((link) =>
    link.addEventListener('click', () => $('#sidebar')?.classList.remove('open')),
  );
}

function renderLogin() {
  app().className = '';
  loginView(app(), async (user, csrf) => {
    setCsrfToken(csrf);
    state.user = user;
    await refreshSession();
    render();
  });
}

async function logout() {
  try {
    await api.post('/logout');
  } catch {
    /* Abmelden soll auch bei Serverfehler die Oberfläche zurücksetzen. */
  }
  state.user = null;
  setCsrfToken('');
  render();
}

/** Sitzungsdaten und die Zahl offener Änderungen neu laden. */
export async function refreshSession() {
  const me = await api.get('/me');
  state.user = me.user;
  state.store = me.store;
  state.publishing = me.publishing;
  setCsrfToken(me.csrf_token);
  setCurrency(me.store?.currency);

  try {
    const publishState = await api.get('/publish');
    state.pendingCount = publishState.pending.never_published ? 1 : publishState.pending.count;
  } catch {
    state.pendingCount = 0;
  }
}

/** Nach jeder Änderung aufrufen, damit der Kopf die offenen Änderungen zeigt. */
export async function markChanged() {
  try {
    const publishState = await api.get('/publish');
    state.pendingCount = publishState.pending.never_published ? 1 : publishState.pending.count;
    const indicator = $('.publish-state');
    if (indicator) {
      indicator.innerHTML = `${
        state.pendingCount > 0
          ? `<strong>${state.pendingCount} Änderung${state.pendingCount === 1 ? '' : 'en'} nicht veröffentlicht</strong>`
          : 'Alles veröffentlicht'
      }<br />${
        state.publishing?.last_published_at
          ? `zuletzt ${esc(relativeTime(state.publishing.last_published_at))}`
          : 'noch nie veröffentlicht'
      }`;
    }
  } catch {
    /* Der Zähler ist Komfort, kein Grund die Aktion scheitern zu lassen. */
  }
}

// --- Start ------------------------------------------------------------------

window.addEventListener('hashchange', render);
window.addEventListener('unhandledrejection', (event) => {
  if (event.reason?.status === 401) {
    state.user = null;
    render();
    event.preventDefault();
  }
});

(async function start() {
  try {
    await refreshSession();
  } catch {
    state.user = null;
  }
  render();
})();
