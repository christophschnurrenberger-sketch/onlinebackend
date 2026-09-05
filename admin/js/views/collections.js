/*
 * Kategorien.
 *
 * Manuell zusammengestellt oder regelbasiert. Die Regelvorschau zeigt sofort,
 * welche Artikel eine Regel trifft – ohne sie müsste der Betreiber speichern
 * und im Shop nachsehen, um zu verstehen, was er gebaut hat.
 */

import { api } from '../api.js';
import {
  esc, attr, money, badge, toast, confirmDialog, field, textarea, select,
  checkbox, emptyState, formValues,
} from '../ui.js';
import { navigate, markChanged } from '../app.js';

const RULE_FIELDS = [
  ['tag', 'Schlagwort'],
  ['product_type', 'Produkttyp'],
  ['vendor', 'Hersteller'],
  ['title', 'Titel'],
  ['price', 'Preis'],
];
const RULE_OPERATORS = [
  ['equals', 'ist gleich'],
  ['not_equals', 'ist nicht'],
  ['contains', 'enthält'],
  ['starts_with', 'beginnt mit'],
  ['greater_than', 'ist größer als'],
  ['less_than', 'ist kleiner als'],
];
const SORT_ORDERS = [
  ['manual', 'Manuell'],
  ['title-asc', 'Titel A–Z'],
  ['title-desc', 'Titel Z–A'],
  ['price-asc', 'Preis aufsteigend'],
  ['price-desc', 'Preis absteigend'],
  ['created-desc', 'Neueste zuerst'],
];

export async function collectionsView(root) {
  const data = await api.get('/collections');

  root.innerHTML = `
    <div class="page-header">
      <div class="titles"><h1>Kategorien</h1>
        <div class="subtitle">${data.items.length} Kategorien</div></div>
      <div class="actions"><button class="btn primary" data-new>Kategorie anlegen</button></div>
    </div>
    <section class="card"><div class="card-body tight">
      ${data.items.length === 0
        ? emptyState('Noch keine Kategorien', 'Kategorien gliedern deinen Shop und tauchen in der Navigation auf.',
            '<button class="btn primary" data-new>Kategorie anlegen</button>')
        : `<div class="table-wrap"><table>
            <thead><tr><th>Kategorie</th><th>Art</th><th class="num">Artikel</th><th>Sichtbar</th></tr></thead>
            <tbody>${data.items.map((collection) => `
              <tr class="clickable" data-id="${collection.id}">
                <td><div class="cell-title">${esc(collection.title)}</div>
                    <div class="cell-sub">/collections/${esc(collection.handle)}</div></td>
                <td>${collection.rule_type === 'auto'
                  ? badge('info', `Automatisch (${collection.rules.length} Regel${collection.rules.length === 1 ? '' : 'n'})`)
                  : badge('', 'Manuell')}</td>
                <td class="num">${collection.product_count}</td>
                <td>${collection.published ? badge('active', 'Im Shop') : badge('draft', 'Versteckt')}</td>
              </tr>`).join('')}</tbody></table></div>`}
    </div></section>`;

  root.querySelectorAll('[data-new]').forEach((b) => b.addEventListener('click', () => navigate('collections/new')));
  root.querySelectorAll('[data-id]').forEach((row) =>
    row.addEventListener('click', () => navigate(`collections/${row.dataset.id}`)),
  );
}

export async function collectionEditorView(root, params) {
  const isNew = !params.id;
  const loaded = isNew ? null : await api.get(`/collections/${params.id}`);
  const collection = loaded?.collection || {
    title: '', handle: '', body_html: '', image_url: '', rule_type: 'manual',
    rules: [], rules_match: 'all', sort_order: 'manual', published: false,
    seo_title: '', seo_description: '',
  };

  const draft = structuredClone(collection);
  let members = loaded?.products || [];

  const paint = () => {
    root.innerHTML = `
      <div class="page-header">
        <div class="titles">
          <a class="back-link" href="#/collections">← Alle Kategorien</a>
          <h1>${isNew ? 'Neue Kategorie' : esc(draft.title)}</h1>
        </div>
        <div class="actions">
          ${!isNew ? `<a class="btn" href="/collections/${attr(draft.handle)}" target="_blank" rel="noopener">Im Shop ansehen ↗</a>` : ''}
          <button class="btn primary" data-save>Speichern</button>
        </div>
      </div>

      <form class="grid-2" data-form>
        <div>
          <section class="card"><div class="card-body">
            ${field({ label: 'Titel', name: 'title', value: draft.title, required: true })}
            ${textarea({ label: 'Beschreibung', name: 'body_html', value: draft.body_html, rows: 5 })}
            ${field({ label: 'Titelbild (URL)', name: 'image_url', value: draft.image_url,
              hint: 'Ein Bild aus der Mediathek oder eine externe Adresse.' })}
          </div></section>

          <section class="card">
            <div class="card-head"><h2>Artikel</h2></div>
            <div class="card-body">
              ${select({ label: 'Zusammenstellung', name: 'rule_type', value: draft.rule_type, options: [
                ['manual', 'Manuell – ich wähle die Artikel selbst'],
                ['auto', 'Automatisch – Regeln bestimmen die Artikel'],
              ] })}

              <div data-rules style="${draft.rule_type === 'auto' ? '' : 'display:none'}">
                ${select({ label: 'Artikel müssen', name: 'rules_match', value: draft.rules_match, options: [
                  ['all', 'alle Bedingungen erfüllen'],
                  ['any', 'mindestens eine Bedingung erfüllen'],
                ] })}
                <div data-rule-list>
                  ${draft.rules.map((rule, index) => ruleRow(rule, index)).join('')}
                </div>
                <button class="btn sm" type="button" data-add-rule>Bedingung hinzufügen</button>
              </div>

              <div data-manual style="${draft.rule_type === 'manual' ? '' : 'display:none'}">
                <div class="hint" style="margin-bottom:10px">
                  ${isNew ? 'Nach dem Speichern kannst du hier Artikel zuordnen.' : 'Artikel per Suche hinzufügen oder entfernen.'}
                </div>
                ${!isNew ? '<input type="search" placeholder="Artikel suchen und hinzufügen …" data-product-search style="width:100%;padding:8px 11px;border:1px solid var(--border-strong);border-radius:6px;font:inherit" />' : ''}
                <div data-search-results></div>
              </div>

              <div style="margin-top:14px" data-members>
                ${members.length === 0
                  ? '<p class="hint">Noch keine Artikel in dieser Kategorie.</p>'
                  : `<div class="table-wrap"><table><tbody>
                      ${members.map((product) => `
                        <tr>
                          <td class="thumb-cell">${product.image_url
                            ? `<img class="row-thumb" src="${attr(product.image_url)}" alt="" />`
                            : '<div class="row-thumb empty">▦</div>'}</td>
                          <td><div class="cell-title">${esc(product.title)}</div>
                              <div class="cell-sub">${badge(product.status)}</div></td>
                          <td class="num">${money(product.min_price || 0)}</td>
                          <td class="num">${draft.rule_type === 'manual' && !isNew
                            ? `<button class="btn sm plain critical" type="button" data-remove-member="${product.id}">Entfernen</button>`
                            : '<span class="cell-sub">automatisch</span>'}</td>
                        </tr>`).join('')}
                    </tbody></table></div>`}
              </div>
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Suchmaschinen</h2></div>
            <div class="card-body">
              ${field({ label: 'URL-Pfad', name: 'handle', value: draft.handle })}
              ${field({ label: 'SEO-Titel', name: 'seo_title', value: draft.seo_title, placeholder: draft.title })}
              ${textarea({ label: 'SEO-Beschreibung', name: 'seo_description', value: draft.seo_description, rows: 3 })}
            </div>
          </section>
        </div>

        <div>
          <section class="card"><div class="card-body">
            ${checkbox({ label: 'Im Shop sichtbar', name: 'published', checked: Boolean(draft.published) })}
            <div class="hint" style="margin-top:-6px">Wird erst nach dem Veröffentlichen wirksam.</div>
          </div></section>

          <section class="card">
            <div class="card-head"><h2>Sortierung</h2></div>
            <div class="card-body">
              ${select({ label: 'Reihenfolge im Shop', name: 'sort_order', value: draft.sort_order, options: SORT_ORDERS })}
            </div>
          </section>

          ${!isNew
            ? `<section class="card"><div class="card-body">
                <button class="btn critical" type="button" data-delete style="width:100%">Kategorie löschen</button>
              </div></section>`
            : ''}
        </div>
      </form>`;

    bind();
  };

  function ruleRow(rule, index) {
    return `<div class="option-editor" data-rule="${index}">
      <div class="field-row three" style="margin-bottom:8px">
        <select data-rule-field="${index}">
          ${RULE_FIELDS.map(([value, label]) => `<option value="${value}" ${rule.field === value ? 'selected' : ''}>${label}</option>`).join('')}
        </select>
        <select data-rule-operator="${index}">
          ${RULE_OPERATORS.map(([value, label]) => `<option value="${value}" ${rule.operator === value ? 'selected' : ''}>${label}</option>`).join('')}
        </select>
        <input type="text" value="${attr(rule.value)}" data-rule-value="${index}" placeholder="Wert" />
      </div>
      <button class="btn sm plain critical" type="button" data-remove-rule="${index}">Bedingung entfernen</button>
    </div>`;
  }

  function collect() {
    const values = formValues(root.querySelector('[data-form]'));
    return {
      title: values.title,
      handle: values.handle,
      body_html: values.body_html,
      image_url: values.image_url,
      rule_type: values.rule_type,
      rules_match: values.rules_match,
      sort_order: values.sort_order,
      published: values.published,
      seo_title: values.seo_title,
      seo_description: values.seo_description,
      rules: draft.rules,
      ...(draft.rule_type === 'manual' ? { product_ids: members.map((p) => p.id) } : {}),
    };
  }

  function bind() {
    const form = root.querySelector('[data-form]');
    form.addEventListener('submit', (event) => event.preventDefault());

    form.querySelector('[name=rule_type]').addEventListener('change', (event) => {
      draft.rule_type = event.target.value;
      root.querySelector('[data-rules]').style.display = draft.rule_type === 'auto' ? '' : 'none';
      root.querySelector('[data-manual]').style.display = draft.rule_type === 'manual' ? '' : 'none';
    });

    root.querySelector('[data-add-rule]')?.addEventListener('click', () => {
      draft.rules.push({ field: 'tag', operator: 'equals', value: '' });
      paint();
    });
    root.querySelectorAll('[data-remove-rule]').forEach((button) =>
      button.addEventListener('click', () => {
        draft.rules.splice(Number(button.dataset.removeRule), 1);
        paint();
      }),
    );
    root.querySelectorAll('[data-rule-field], [data-rule-operator], [data-rule-value]').forEach((input) => {
      input.addEventListener('change', () => {
        const index = Number(input.dataset.ruleField ?? input.dataset.ruleOperator ?? input.dataset.ruleValue);
        const key = input.dataset.ruleField !== undefined ? 'field'
          : input.dataset.ruleOperator !== undefined ? 'operator' : 'value';
        draft.rules[index][key] = input.value;
      });
    });

    // Artikelsuche für manuelle Kategorien.
    let timer;
    root.querySelector('[data-product-search]')?.addEventListener('input', (event) => {
      clearTimeout(timer);
      const term = event.target.value.trim();
      const results = root.querySelector('[data-search-results]');
      if (term.length < 2) return void (results.innerHTML = '');

      timer = setTimeout(async () => {
        const found = await api.get(`/products?search=${encodeURIComponent(term)}&limit=8`);
        const candidates = found.items.filter((p) => !members.some((m) => m.id === p.id));
        results.innerHTML = candidates.length === 0
          ? '<p class="hint">Keine weiteren Treffer.</p>'
          : `<div class="table-wrap" style="margin-top:8px"><table><tbody>
              ${candidates.map((p) => `
                <tr><td>${esc(p.title)}</td>
                    <td class="num"><button class="btn sm" type="button" data-add-member="${p.id}">Hinzufügen</button></td></tr>`).join('')}
            </tbody></table></div>`;

        results.querySelectorAll('[data-add-member]').forEach((button) =>
          button.addEventListener('click', () => {
            const product = candidates.find((p) => p.id === Number(button.dataset.addMember));
            members.push(product);
            event.target.value = '';
            results.innerHTML = '';
            paint();
          }),
        );
      }, 280);
    });

    root.querySelectorAll('[data-remove-member]').forEach((button) =>
      button.addEventListener('click', () => {
        members = members.filter((p) => p.id !== Number(button.dataset.removeMember));
        paint();
      }),
    );

    root.querySelector('[data-save]').addEventListener('click', async () => {
      try {
        const payload = collect();
        const result = isNew
          ? await api.post('/collections', payload)
          : await api.put(`/collections/${params.id}`, payload);
        toast('Kategorie gespeichert');
        await markChanged();
        if (isNew) navigate(`collections/${result.collection.id}`);
        else {
          const fresh = await api.get(`/collections/${params.id}`);
          Object.assign(draft, fresh.collection);
          members = fresh.products;
          paint();
        }
      } catch (error) {
        toast(error.message, 'critical');
      }
    });

    root.querySelector('[data-delete]')?.addEventListener('click', () =>
      confirmDialog({
        title: 'Kategorie löschen',
        message: `„${draft.title}“ löschen? Die enthaltenen Artikel bleiben bestehen.`,
        onConfirm: async () => {
          await api.delete(`/collections/${params.id}`);
          toast('Kategorie gelöscht');
          await markChanged();
          navigate('collections');
        },
      }),
    );
  }

  paint();
}
