/*
 * Seiten und Journal-Beiträge.
 *
 * Beide teilen sich die Masken – der Unterschied sind nur ein paar Felder.
 * Welcher Typ gemeint ist, entscheidet der Abschnitt der Route.
 */

import { api } from '../api.js';
import {
  esc, attr, badge, date, toast, confirmDialog, field, textarea, checkbox,
  emptyState, formValues,
} from '../ui.js';
import { navigate, markChanged } from '../app.js';

const TYPES = {
  pages: { path: 'pages', label: 'Seiten', single: 'Seite', urlPrefix: '/pages/' },
  posts: { path: 'posts', label: 'Journal', single: 'Beitrag', urlPrefix: '/blog/' },
};

export async function contentView(root, params) {
  const type = TYPES[params.section] || TYPES.pages;
  const data = await api.get(`/${type.path}`);

  root.innerHTML = `
    <div class="page-header">
      <div class="titles"><h1>${esc(type.label)}</h1>
        <div class="subtitle">${data.items.length} Einträge</div></div>
      <div class="actions"><button class="btn primary" data-new>${esc(type.single)} anlegen</button></div>
    </div>
    <section class="card"><div class="card-body tight">
      ${data.items.length === 0
        ? emptyState(`Noch keine ${esc(type.label)}`,
            type.path === 'pages'
              ? 'Impressum, AGB und Datenschutz gehören in jeden Shop – der Seed legt Entwürfe dafür an.'
              : 'Beiträge erscheinen unter /blog im Shop.',
            `<button class="btn primary" data-new>${esc(type.single)} anlegen</button>`)
        : `<div class="table-wrap"><table>
            <thead><tr><th>Titel</th><th>Adresse</th><th>Status</th><th>Zuletzt geändert</th></tr></thead>
            <tbody>${data.items.map((item) => `
              <tr class="clickable" data-id="${item.id}">
                <td><div class="cell-title">${esc(item.title)}</div>
                    ${item.excerpt ? `<div class="cell-sub">${esc(item.excerpt.slice(0, 90))}</div>` : ''}</td>
                <td class="cell-sub">${esc(type.urlPrefix)}${esc(item.handle)}</td>
                <td>${item.published ? badge('active', 'Veröffentlicht') : badge('draft', 'Entwurf')}</td>
                <td class="cell-sub">${esc(date(item.updated_at))}</td>
              </tr>`).join('')}</tbody></table></div>`}
    </div></section>`;

  root.querySelectorAll('[data-new]').forEach((button) =>
    button.addEventListener('click', () => navigate(`${type.path}/new`)),
  );
  root.querySelectorAll('[data-id]').forEach((row) =>
    row.addEventListener('click', () => navigate(`${type.path}/${row.dataset.id}`)),
  );
}

export async function contentEditorView(root, params) {
  const type = TYPES[params.section] || TYPES.pages;
  const isNew = !params.id || params.id === 'new';
  const item = isNew
    ? { title: '', handle: '', body_html: '', excerpt: '', image_url: '', author: '', published: false, seo_title: '', seo_description: '' }
    : (await api.get(`/${type.path}/${params.id}`)).item;

  root.innerHTML = `
    <div class="page-header">
      <div class="titles">
        <a class="back-link" href="#/${type.path}">← ${esc(type.label)}</a>
        <h1>${isNew ? `Neue${type.path === 'pages' ? '' : 'r'} ${esc(type.single)}` : esc(item.title)}</h1>
      </div>
      <div class="actions">
        ${!isNew && item.published
          ? `<a class="btn" href="${attr(type.urlPrefix + item.handle)}" target="_blank" rel="noopener">Im Shop ansehen ↗</a>`
          : ''}
        <button class="btn primary" data-save>Speichern</button>
      </div>
    </div>

    <form class="grid-2" data-form>
      <div>
        <section class="card"><div class="card-body">
          ${field({ label: 'Titel', name: 'title', value: item.title, required: true })}
          ${type.path === 'posts'
            ? field({ label: 'Kurzfassung', name: 'excerpt', value: item.excerpt,
                hint: 'Erscheint in der Übersicht. Leer lassen, um sie aus dem Text zu erzeugen.' })
            : ''}
          ${textarea({ label: 'Inhalt', name: 'body_html', value: item.body_html, rows: 18,
            hint: 'Einfaches HTML: <p>, <h2>, <ul>, <strong>, <a>. Skripte werden entfernt.' })}
        </div></section>

        <section class="card">
          <div class="card-head"><h2>Suchmaschinen</h2></div>
          <div class="card-body">
            ${field({ label: 'URL-Pfad', name: 'handle', value: item.handle,
              hint: `Erreichbar unter ${type.urlPrefix}${item.handle || 'titel'}` })}
            ${field({ label: 'SEO-Titel', name: 'seo_title', value: item.seo_title, placeholder: item.title })}
            ${textarea({ label: 'SEO-Beschreibung', name: 'seo_description', value: item.seo_description, rows: 3 })}
          </div>
        </section>
      </div>

      <div>
        <section class="card"><div class="card-body">
          ${checkbox({ label: 'Veröffentlicht', name: 'published', checked: Boolean(item.published) })}
          <div class="hint" style="margin-top:-6px">Wird im Shop erst nach dem nächsten Veröffentlichen sichtbar.</div>
        </div></section>

        ${type.path === 'posts'
          ? `<section class="card">
              <div class="card-head"><h2>Beitrag</h2></div>
              <div class="card-body">
                ${field({ label: 'Autor', name: 'author', value: item.author })}
                ${field({ label: 'Titelbild (URL)', name: 'image_url', value: item.image_url })}
                ${item.published_at ? `<div class="hint">Veröffentlicht am ${esc(date(item.published_at))}</div>` : ''}
              </div></section>`
          : ''}

        ${!isNew
          ? `<section class="card"><div class="card-body">
              <button class="btn critical" type="button" data-delete style="width:100%">Löschen</button>
            </div></section>`
          : ''}
      </div>
    </form>`;

  const form = root.querySelector('[data-form]');
  form.addEventListener('submit', (event) => event.preventDefault());

  root.querySelector('[data-save]').addEventListener('click', async () => {
    try {
      const payload = formValues(form);
      const result = isNew
        ? await api.post(`/${type.path}`, payload)
        : await api.put(`/${type.path}/${params.id}`, payload);
      toast(`${type.single} gespeichert`);
      await markChanged();
      if (isNew) navigate(`${type.path}/${result.item.id}`);
    } catch (error) {
      toast(error.message, 'critical');
    }
  });

  root.querySelector('[data-delete]')?.addEventListener('click', () =>
    confirmDialog({
      title: `${type.single} löschen`,
      message: `„${item.title}“ endgültig löschen?`,
      onConfirm: async () => {
        await api.delete(`/${type.path}/${params.id}`);
        toast('Gelöscht');
        await markChanged();
        navigate(type.path);
      },
    }),
  );

  // Handle aus dem Titel vorschlagen, solange keiner gesetzt ist.
  if (isNew) {
    const titleInput = form.querySelector('[name=title]');
    const handleInput = form.querySelector('[name=handle]');
    titleInput.addEventListener('input', () => {
      handleInput.value = titleInput.value.toLowerCase()
        .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    });
  }
}
