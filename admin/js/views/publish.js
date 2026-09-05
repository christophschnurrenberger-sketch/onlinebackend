/*
 * Veröffentlichen.
 *
 * Zeigt vor dem Klick, was sich seit der letzten Veröffentlichung geändert hat.
 * Ohne diese Liste wäre „Veröffentlichen“ ein Blindflug – gerade wenn mehrere
 * Personen im Backend arbeiten.
 */

import { api } from '../api.js';
import { esc, dateTime, relativeTime, toast, modal, confirmDialog, textarea, field } from '../ui.js';
import { refreshSession, render } from '../app.js';

const KIND_LABELS = {
  product: 'Artikel', collection: 'Kategorie', page: 'Seite',
  post: 'Beitrag', theme: 'Design', store: 'Shop', menu: 'Navigation',
};
const TYPE_LABELS = { added: 'neu', changed: 'geändert', removed: 'entfernt', initial: 'erstmalig' };

export async function publishView(root) {
  const load = async () => api.get('/publish');
  let data = await load();

  const paint = () => {
    const { pending, versions, live_version: liveVersion } = data;

    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Veröffentlichen</h1>
          <div class="subtitle">Der Shop zeigt Version ${liveVersion || '–'}${
            versions[0]?.created_at && liveVersion
              ? ` · zuletzt ${esc(relativeTime(versions.find((v) => v.live)?.created_at))}`
              : ''}</div></div>
        <div class="actions">
          <a class="btn" href="/?preview=1" target="_blank" rel="noopener">Entwurf ansehen ↗</a>
          <button class="btn publish" data-publish ${pending.count === 0 && !pending.never_published ? 'disabled' : ''}>
            Jetzt veröffentlichen</button>
        </div>
      </div>

      ${pending.never_published
        ? `<div class="banner warning"><div class="banner-body">
            <strong>Der Shop ist noch nicht online</strong>
            Besucher sehen bisher nur einen Hinweis. Mit dem ersten Veröffentlichen geht der Katalog live.
          </div></div>`
        : pending.count === 0
          ? `<div class="banner success"><div class="banner-body">
              <strong>Alles veröffentlicht</strong>
              Der Shop zeigt genau den Stand, den du im Backend siehst.
            </div></div>`
          : ''}

      <div class="grid-2">
        <section class="card">
          <div class="card-head"><h2>Offene Änderungen${pending.count > 0 ? ` (${pending.count})` : ''}</h2></div>
          <div class="card-body">
            ${pending.changes.length === 0
              ? '<p class="hint">Seit der letzten Veröffentlichung wurde nichts geändert.</p>'
              : `<ul class="change-list">
                  ${pending.changes.map((change) => `
                    <li>
                      <span class="change-type ${esc(change.type)}">${esc(TYPE_LABELS[change.type] || change.type)}</span>
                      <span>${esc(KIND_LABELS[change.kind] || change.label || '')}</span>
                      <strong>${esc(change.title || change.label || '')}</strong>
                    </li>`).join('')}
                </ul>`}
          </div>
          <div class="card-footer">
            <button class="btn publish" data-publish ${pending.count === 0 && !pending.never_published ? 'disabled' : ''}>
              Jetzt veröffentlichen</button>
          </div>
        </section>

        <div>
          <section class="card">
            <div class="card-head"><h2>Verlauf</h2></div>
            <div class="card-body tight">
              ${versions.length === 0
                ? '<div class="empty" style="padding:24px"><p>Noch keine Veröffentlichung.</p></div>'
                : `<div class="table-wrap"><table>
                    <thead><tr><th>Version</th><th>Inhalt</th><th>Wann</th><th></th></tr></thead>
                    <tbody>${versions.map((version) => `
                      <tr>
                        <td><strong>v${version.version}</strong>
                          ${version.live ? '<div><span class="badge success"><span class="dot"></span>live</span></div>' : ''}</td>
                        <td class="cell-sub">${version.stats.products ?? 0} Artikel · ${version.stats.collections ?? 0} Kategorien
                          ${version.note ? `<div>${esc(version.note)}</div>` : ''}</td>
                        <td class="cell-sub" title="${esc(dateTime(version.created_at))}">
                          ${esc(relativeTime(version.created_at))}
                          ${version.user_name ? `<div>${esc(version.user_name)}</div>` : ''}</td>
                        <td class="num">${version.live ? '' :
                          `<button class="btn sm" data-rollback="${version.version}">Zurücksetzen</button>`}</td>
                      </tr>`).join('')}</tbody></table></div>`}
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Statischer Export</h2></div>
            <div class="card-body">
              <p class="hint">Erzeugt aus der aktuell veröffentlichten Version fertige HTML-Dateien.
                Der Ordner lässt sich auf jeden Webspace, Netlify, Vercel oder GitHub Pages hochladen.
                Warenkorb und Kasse sprechen dabei weiterhin mit diesem Backend.</p>
              <button class="btn" data-export style="margin-top:8px">Export erzeugen</button>
              <div data-export-result></div>
            </div>
          </section>
        </div>
      </div>`;

    bind();
  };

  function bind() {
    root.querySelectorAll('[data-publish]').forEach((button) =>
      button.addEventListener('click', () =>
        modal({
          title: 'Änderungen veröffentlichen',
          body: `<p>Der aktuelle Stand wird als neue Version live geschaltet. Bestehende
              Bestellungen und Bestände bleiben davon unberührt.</p>
            ${textarea({ label: 'Notiz zur Version (optional)', name: 'note', rows: 2,
              hint: 'Hilft später im Verlauf, z. B. „Herbstkollektion online“.' })}`,
          confirmLabel: 'Veröffentlichen',
          tone: 'publish',
          onSubmit: async (values) => {
            const result = await api.post('/publish', { note: values.note });
            toast(`Version ${result.publication.version} ist live`);
            await refreshSession();
            data = await load();
            render();
          },
        }),
      ),
    );

    root.querySelectorAll('[data-rollback]').forEach((button) =>
      button.addEventListener('click', () =>
        confirmDialog({
          title: `Auf Version ${button.dataset.rollback} zurücksetzen`,
          message: 'Der Shop zeigt sofort wieder diesen älteren Stand. Deine Arbeitsdaten im Backend bleiben unverändert.',
          confirmLabel: 'Zurücksetzen',
          onConfirm: async () => {
            await api.post('/publish/rollback', { version: Number(button.dataset.rollback) });
            toast(`Version ${button.dataset.rollback} ist wieder live`);
            await refreshSession();
            data = await load();
            paint();
          },
        }),
      ),
    );

    root.querySelector('[data-export]')?.addEventListener('click', () =>
      modal({
        title: 'Statischen Export erzeugen',
        body: `${field({ label: 'Öffentliche Adresse der Website', name: 'base_url',
          placeholder: 'https://mein-shop.de',
          hint: 'Wird für Links, Sitemap und SEO-Angaben verwendet. Leer lassen für die Server-Adresse.' })}`,
        confirmLabel: 'Export starten',
        onSubmit: async (values) => {
          const result = await api.post('/publish/export', { base_url: values.base_url });
          root.querySelector('[data-export-result]').innerHTML = `
            <div class="banner success" style="margin-top:12px"><div class="banner-body">
              <strong>${result.files} Dateien erzeugt</strong>
              Ordner: <code>${esc(result.directory)}</code><br />
              Basis-Adresse: ${esc(result.base_url)}
            </div></div>`;
          toast('Export erzeugt');
        },
      }),
    );
  }

  paint();
}
