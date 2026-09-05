/*
 * Artikelliste und Artikeleditor.
 *
 * Der Editor ist die meistgenutzte Maske des Backends. Er ist deshalb so
 * gebaut, dass der häufige Fall schnell geht (Titel, Preis, Bild, speichern)
 * und der seltene möglich bleibt (Optionen, Varianten, SEO, Bestandspolitik).
 */

import { api, query } from '../api.js';
import {
  money, moneyInput, esc, attr, badge, toast, modal, confirmDialog,
  field, textarea, select, checkbox, emptyState, formValues, $$,
} from '../ui.js';
import { navigate, markChanged } from '../app.js';

const STATUS_TABS = [
  ['', 'Alle'],
  ['active', 'Aktiv'],
  ['draft', 'Entwürfe'],
  ['archived', 'Archiv'],
];

// --- Liste ------------------------------------------------------------------

export async function productsView(root) {
  const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
  const filter = {
    status: params.get('status') || '',
    search: params.get('search') || '',
    sort: params.get('sort') || 'updated-desc',
    offset: Number(params.get('offset') || 0),
    limit: 50,
  };

  const render = async () => {
    const [data, meta] = await Promise.all([
      api.get(`/products${query(filter)}`),
      api.get('/products/meta'),
    ]);

    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Artikel</h1>
          <div class="subtitle">${data.total} Artikel im Katalog</div></div>
        <div class="actions">
          <button class="btn" data-export-csv>CSV exportieren</button>
          <button class="btn primary" data-new>Artikel anlegen</button>
        </div>
      </div>

      <section class="card">
        <div class="tabs">
          ${STATUS_TABS.map(([value, label]) =>
            `<button class="tab ${filter.status === value ? 'active' : ''}" data-status="${value}">${label}</button>`).join('')}
        </div>
        <div class="toolbar">
          <input type="search" placeholder="Nach Titel oder Artikelnummer suchen …" value="${attr(filter.search)}" data-search />
          <select data-sort>
            ${[
              ['updated-desc', 'Zuletzt bearbeitet'],
              ['created-desc', 'Neueste zuerst'],
              ['title-asc', 'Titel A–Z'],
              ['price-asc', 'Preis aufsteigend'],
              ['price-desc', 'Preis absteigend'],
            ].map(([value, label]) => `<option value="${value}" ${filter.sort === value ? 'selected' : ''}>${label}</option>`).join('')}
          </select>
        </div>

        <div data-bulk></div>

        <div class="card-body tight">
          ${data.items.length === 0
            ? emptyState(
                filter.search ? 'Keine Treffer' : 'Noch keine Artikel',
                filter.search ? 'Versuch es mit einem anderen Suchbegriff.' : 'Lege deinen ersten Artikel an – Titel und Preis genügen zum Start.',
                '<button class="btn primary" data-new>Artikel anlegen</button>',
              )
            : `<div class="table-wrap"><table>
                <thead><tr>
                  <th style="width:34px"><input type="checkbox" data-check-all /></th>
                  <th class="thumb-cell"></th><th>Artikel</th><th>Status</th>
                  <th class="num">Bestand</th><th class="num">Preis</th><th>Kategorie</th>
                </tr></thead>
                <tbody>
                  ${data.items.map((product) => `
                    <tr data-id="${product.id}">
                      <td><input type="checkbox" data-check value="${product.id}" /></td>
                      <td class="thumb-cell">${product.image_url
                        ? `<img class="row-thumb" src="${attr(product.image_url)}" alt="" />`
                        : '<div class="row-thumb empty">▦</div>'}</td>
                      <td class="clickable-cell" data-open="${product.id}">
                        <div class="cell-title">${esc(product.title)}</div>
                        <div class="cell-sub">${product.variant_count > 1 ? `${product.variant_count} Varianten` : esc(product.vendor || '—')}</div>
                      </td>
                      <td>${badge(product.status)}</td>
                      <td class="num">${product.inventory_total}</td>
                      <td class="num">${product.min_price === product.max_price
                        ? money(product.min_price)
                        : `${money(product.min_price)}–${money(product.max_price)}`}</td>
                      <td class="cell-sub">${esc(product.product_type || '—')}</td>
                    </tr>`).join('')}
                </tbody></table></div>`}
        </div>

        ${data.total > filter.limit
          ? `<div class="pagination">
              <span>${filter.offset + 1}–${Math.min(filter.offset + filter.limit, data.total)} von ${data.total}</span>
              <span class="btn-group">
                <button class="btn sm" data-page="prev" ${filter.offset === 0 ? 'disabled' : ''}>Zurück</button>
                <button class="btn sm" data-page="next" ${filter.offset + filter.limit >= data.total ? 'disabled' : ''}>Weiter</button>
              </span>
            </div>`
          : ''}
      </section>`;

    bind(data, meta);
  };

  const bind = (data, meta) => {
    root.querySelectorAll('[data-new]').forEach((button) =>
      button.addEventListener('click', () => navigate('products/new')),
    );
    root.querySelectorAll('[data-open]').forEach((cell) =>
      cell.addEventListener('click', () => navigate(`products/${cell.dataset.open}`)),
    );
    root.querySelectorAll('[data-status]').forEach((tab) =>
      tab.addEventListener('click', () => {
        filter.status = tab.dataset.status;
        filter.offset = 0;
        render();
      }),
    );
    root.querySelector('[data-sort]')?.addEventListener('change', (event) => {
      filter.sort = event.target.value;
      render();
    });

    // Suche mit kurzer Verzögerung, damit nicht jeder Tastendruck eine
    // Anfrage auslöst.
    let timer;
    root.querySelector('[data-search]')?.addEventListener('input', (event) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        filter.search = event.target.value;
        filter.offset = 0;
        render();
      }, 280);
    });

    root.querySelectorAll('[data-page]').forEach((button) =>
      button.addEventListener('click', () => {
        filter.offset += button.dataset.page === 'next' ? filter.limit : -filter.limit;
        filter.offset = Math.max(0, filter.offset);
        render();
      }),
    );

    root.querySelector('[data-check-all]')?.addEventListener('change', (event) => {
      root.querySelectorAll('[data-check]').forEach((box) => {
        box.checked = event.target.checked;
      });
      updateBulkBar();
    });
    root.querySelectorAll('[data-check]').forEach((box) => box.addEventListener('change', updateBulkBar));

    function selectedIds() {
      return $$('[data-check]:checked', root).map((box) => Number(box.value));
    }

    function updateBulkBar() {
      const ids = selectedIds();
      const bar = root.querySelector('[data-bulk]');
      if (ids.length === 0) return void (bar.innerHTML = '');

      bar.innerHTML = `<div class="bulk-bar">
        <strong>${ids.length} ausgewählt</strong>
        <span class="btn-group">
          <button class="btn sm" data-bulk-action="active">Aktivieren</button>
          <button class="btn sm" data-bulk-action="draft">Auf Entwurf</button>
          <button class="btn sm" data-bulk-action="archived">Archivieren</button>
          <button class="btn sm" data-bulk-collection>Zu Kategorie</button>
          <button class="btn sm critical" data-bulk-action="delete">Löschen</button>
        </span></div>`;

      bar.querySelectorAll('[data-bulk-action]').forEach((button) =>
        button.addEventListener('click', async () => {
          const action = button.dataset.bulkAction;
          const run = async () => {
            await api.post('/products/bulk', action === 'delete'
              ? { ids, action: 'delete' }
              : { ids, action: 'status', status: action });
            toast(`${ids.length} Artikel aktualisiert`);
            await markChanged();
            render();
          };
          if (action === 'delete') {
            confirmDialog({
              title: 'Artikel löschen',
              message: `${ids.length} Artikel endgültig löschen? Bestellungen bleiben davon unberührt.`,
              onConfirm: run,
            });
          } else {
            await run();
          }
        }),
      );

      bar.querySelector('[data-bulk-collection]')?.addEventListener('click', () => {
        const manual = meta.collections.filter((c) => c.rule_type === 'manual');
        if (manual.length === 0) {
          return toast('Es gibt noch keine manuelle Kategorie', 'critical');
        }
        modal({
          title: 'Zu Kategorie hinzufügen',
          body: select({
            label: 'Kategorie',
            name: 'collection_id',
            options: manual.map((c) => [c.id, c.title]),
          }),
          confirmLabel: 'Hinzufügen',
          onSubmit: async (values) => {
            await api.post('/products/bulk', {
              ids,
              action: 'add_to_collection',
              collection_id: Number(values.collection_id),
            });
            toast(`${ids.length} Artikel zugeordnet`);
            await markChanged();
            render();
          },
        });
      });
    }

    root.querySelector('[data-export-csv]')?.addEventListener('click', () => exportCsv(data.items));
  };

  await render();
}

/** Artikelliste als CSV – für Preislisten, Steuerberater und Tabellenarbeit. */
function exportCsv(items) {
  const header = ['Titel', 'Handle', 'Status', 'Typ', 'Hersteller', 'Varianten', 'Bestand', 'Preis min', 'Preis max'];
  const rows = items.map((product) => [
    product.title, product.handle, product.status, product.product_type, product.vendor,
    product.variant_count, product.inventory_total,
    moneyInput(product.min_price), moneyInput(product.max_price),
  ]);
  const csv = [header, ...rows]
    .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(';'))
    .join('\n');

  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `artikel-${new Date().toISOString().slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

// --- Editor -----------------------------------------------------------------

export async function productEditorView(root, params) {
  const isNew = !params.id;
  const meta = await api.get('/products/meta');
  const product = isNew
    ? {
        title: '', subtitle: '', body_html: '', vendor: '', product_type: '', tags: [],
        status: 'draft', handle: '', seo_title: '', seo_description: '',
        images: [], options: [], collections: [],
        variants: [{ title: 'Standard', price: 0, sku: '', inventory_quantity: 0, track_inventory: 1, inventory_policy: 'deny' }],
      }
    : (await api.get(`/products/${params.id}`)).product;

  // Arbeitskopie: erst beim Speichern geht etwas zum Server, dadurch kann der
  // Betreiber Varianten und Bilder gefahrlos umsortieren.
  const draft = structuredClone(product);

  const paint = () => {
    root.innerHTML = `
      <div class="page-header">
        <div class="titles">
          <a class="back-link" href="#/products">← Alle Artikel</a>
          <h1>${isNew ? 'Neuer Artikel' : esc(draft.title)}</h1>
          ${!isNew ? `<div class="subtitle">Zuletzt bearbeitet ${esc(new Date(draft.updated_at).toLocaleString('de-DE'))}</div>` : ''}
        </div>
        <div class="actions">
          ${!isNew ? '<button class="btn" data-duplicate>Duplizieren</button>' : ''}
          ${!isNew ? `<a class="btn" href="/products/${attr(draft.handle)}" target="_blank" rel="noopener">Im Shop ansehen ↗</a>` : ''}
          <button class="btn primary" data-save>Speichern</button>
        </div>
      </div>

      <form class="grid-2" data-form>
        <div>
          <section class="card"><div class="card-body">
            ${field({ label: 'Titel', name: 'title', value: draft.title, required: true, placeholder: 'z. B. Leinenhemd Sommer' })}
            ${field({ label: 'Kurzbeschreibung', name: 'subtitle', value: draft.subtitle, hint: 'Eine Zeile unter dem Titel im Shop.' })}
            ${textarea({ label: 'Beschreibung', name: 'body_html', value: draft.body_html, rows: 9,
              hint: 'Einfaches HTML ist erlaubt: <p>, <ul>, <strong>, <a>. Skripte werden entfernt.' })}
          </div></section>

          <section class="card">
            <div class="card-head"><h2>Bilder</h2></div>
            <div class="card-body">
              <div class="media-grid" data-images>
                ${draft.images.map((image, index) => `
                  <div class="media-item" data-image-index="${index}">
                    <img src="${attr(image.url)}" alt="${attr(image.alt)}" />
                    <button class="remove" type="button" data-remove-image="${index}" title="Entfernen">✕</button>
                  </div>`).join('')}
              </div>
              <div class="dropzone" data-dropzone style="margin-top:${draft.images.length ? '10px' : '0'}">
                Bilder hierher ziehen oder klicken zum Auswählen
                <input type="file" accept="image/*" multiple hidden data-file />
              </div>
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Optionen &amp; Varianten</h2>
              <button class="btn sm" type="button" data-add-option>Option hinzufügen</button></div>
            <div class="card-body">
              <div data-options>
                ${draft.options.map((option, index) => optionEditor(option, index)).join('')}
              </div>
              ${draft.options.length > 0
                ? '<button class="btn sm" type="button" data-rebuild-variants>Varianten aus Optionen neu erzeugen</button>'
                : '<p class="hint">Ohne Optionen hat der Artikel genau eine Variante mit einem Preis.</p>'}

              <div style="margin-top:16px">
                <div class="variant-row variant-head">
                  <div>Variante</div><div>Preis</div><div>Streichpreis</div>
                  <div>Artikelnr.</div><div>Bestand</div><div></div>
                </div>
                <div data-variants>
                  ${draft.variants.map((variant, index) => variantRow(variant, index)).join('')}
                </div>
                <button class="btn sm" type="button" data-add-variant style="margin-top:10px">Variante hinzufügen</button>
              </div>
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Suchmaschinen</h2></div>
            <div class="card-body">
              ${field({ label: 'URL-Pfad', name: 'handle', value: draft.handle,
                hint: `Der Artikel wird unter /products/${draft.handle || 'titel'} erreichbar sein.` })}
              ${field({ label: 'SEO-Titel', name: 'seo_title', value: draft.seo_title, placeholder: draft.title })}
              ${textarea({ label: 'SEO-Beschreibung', name: 'seo_description', value: draft.seo_description, rows: 3,
                hint: 'Rund 150 Zeichen. Leer lassen, um sie aus der Beschreibung zu erzeugen.' })}
            </div>
          </section>
        </div>

        <div>
          <section class="card"><div class="card-body">
            ${select({ label: 'Status', name: 'status', value: draft.status, options: [
              ['draft', 'Entwurf – nicht im Shop sichtbar'],
              ['active', 'Aktiv – wird beim Veröffentlichen live'],
              ['archived', 'Archiviert'],
            ] })}
            <div class="hint" style="margin-top:-6px">Auch aktive Artikel erscheinen erst nach dem Veröffentlichen im Shop.</div>
          </div></section>

          <section class="card">
            <div class="card-head"><h2>Einordnung</h2></div>
            <div class="card-body">
              ${field({ label: 'Produkttyp', name: 'product_type', value: draft.product_type,
                attrs: 'list=types', placeholder: 'z. B. Hemden' })}
              <datalist id="types">${meta.types.map((t) => `<option value="${attr(t)}"></option>`).join('')}</datalist>
              ${field({ label: 'Hersteller', name: 'vendor', value: draft.vendor, attrs: 'list=vendors' })}
              <datalist id="vendors">${meta.vendors.map((v) => `<option value="${attr(v)}"></option>`).join('')}</datalist>
              ${field({ label: 'Schlagwörter', name: 'tags', value: (draft.tags || []).join(', '),
                hint: 'Kommagetrennt. Automatische Kategorien greifen darauf zu.' })}
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Versand &amp; Steuer</h2></div>
            <div class="card-body">
              ${checkbox({ label: 'Artikel muss versendet werden', name: 'requires_shipping',
                checked: draft.variants[0]?.requires_shipping !== 0 })}
              ${select({ label: 'Steuersatz', name: 'tax_rate_id', value: draft.variants[0]?.tax_rate_id || '',
                options: [['', 'Standardsatz des Shops'], ...meta.tax_rates.map((r) => [r.id, `${r.name}`])] })}
              ${field({ label: 'Gewicht in Gramm', name: 'weight_grams', type: 'number',
                value: draft.variants[0]?.weight_grams || 0 })}
            </div>
          </section>

          ${!isNew && draft.collections.length > 0
            ? `<section class="card">
                <div class="card-head"><h2>In Kategorien</h2></div>
                <div class="card-body">
                  ${draft.collections.map((c) => `<a class="badge" href="#/collections/${c.id}" style="margin:0 4px 4px 0">${esc(c.title)}</a>`).join('')}
                </div></section>`
            : ''}

          ${!isNew
            ? `<section class="card"><div class="card-body">
                <button class="btn critical" type="button" data-delete style="width:100%">Artikel löschen</button>
              </div></section>`
            : ''}
        </div>
      </form>`;

    bindEditor();
  };

  function optionEditor(option, index) {
    return `<div class="option-editor" data-option="${index}">
      <div class="field-row">
        <div class="field" style="margin:0">
          <label>Optionsname</label>
          <input type="text" value="${attr(option.name)}" data-option-name="${index}" placeholder="Größe" />
        </div>
        <div class="field" style="margin:0">
          <label>Werte (kommagetrennt)</label>
          <input type="text" value="${attr((option.values || []).join(', '))}" data-option-values="${index}" placeholder="S, M, L" />
        </div>
      </div>
      <button class="btn sm plain critical" type="button" data-remove-option="${index}" style="margin-top:8px">Option entfernen</button>
    </div>`;
  }

  function variantRow(variant, index) {
    return `<div class="variant-row" data-variant="${index}">
      <input type="text" value="${attr(variant.title)}" data-v="title" data-i="${index}" placeholder="Standard" />
      <input type="text" value="${attr(moneyInput(variant.price))}" data-v="price" data-i="${index}" inputmode="decimal" placeholder="0,00" />
      <input type="text" value="${attr(moneyInput(variant.compare_at_price))}" data-v="compare_at_price" data-i="${index}" inputmode="decimal" placeholder="—" />
      <input type="text" value="${attr(variant.sku)}" data-v="sku" data-i="${index}" placeholder="SKU" />
      <input type="number" value="${Number(variant.inventory_quantity) || 0}" data-v="inventory_quantity" data-i="${index}" />
      <button class="btn sm plain critical" type="button" data-remove-variant="${index}" title="Variante entfernen">✕</button>
    </div>`;
  }

  function collectForm() {
    const form = root.querySelector('[data-form]');
    const values = formValues(form);

    // Die Felder in der Seitenspalte gelten für alle Varianten gemeinsam.
    const shared = {
      requires_shipping: values.requires_shipping ? 1 : 0,
      tax_rate_id: values.tax_rate_id ? Number(values.tax_rate_id) : null,
      weight_grams: Number(values.weight_grams) || 0,
    };

    return {
      title: values.title,
      subtitle: values.subtitle,
      body_html: values.body_html,
      status: values.status,
      handle: values.handle,
      vendor: values.vendor,
      product_type: values.product_type,
      tags: String(values.tags || '').split(',').map((t) => t.trim()).filter(Boolean),
      seo_title: values.seo_title,
      seo_description: values.seo_description,
      images: draft.images,
      options: draft.options,
      variants: draft.variants.map((variant) => ({ ...variant, ...shared })),
    };
  }

  function syncVariantsFromInputs() {
    root.querySelectorAll('[data-v]').forEach((input) => {
      const index = Number(input.dataset.i);
      const key = input.dataset.v;
      if (!draft.variants[index]) return;
      draft.variants[index][key] = input.value;
    });
  }

  function bindEditor() {
    const form = root.querySelector('[data-form]');
    form.addEventListener('submit', (event) => event.preventDefault());

    root.querySelector('[data-save]').addEventListener('click', async () => {
      syncVariantsFromInputs();
      const button = root.querySelector('[data-save]');
      button.disabled = true;
      button.textContent = 'Speichern …';
      try {
        const payload = collectForm();
        const result = isNew
          ? await api.post('/products', payload)
          : await api.put(`/products/${params.id}`, payload);
        toast('Artikel gespeichert');
        await markChanged();
        if (isNew) navigate(`products/${result.product.id}`);
        else {
          Object.assign(draft, result.product);
          paint();
        }
      } catch (error) {
        toast(error.message, 'critical');
        button.disabled = false;
        button.textContent = 'Speichern';
      }
    });

    root.querySelector('[data-duplicate]')?.addEventListener('click', async () => {
      const result = await api.post(`/products/${params.id}/duplicate`);
      toast('Kopie als Entwurf angelegt');
      navigate(`products/${result.product.id}`);
    });

    root.querySelector('[data-delete]')?.addEventListener('click', () =>
      confirmDialog({
        title: 'Artikel löschen',
        message: `„${draft.title}“ endgültig löschen? Bereits abgeschlossene Bestellungen bleiben unverändert.`,
        onConfirm: async () => {
          await api.delete(`/products/${params.id}`);
          toast('Artikel gelöscht');
          await markChanged();
          navigate('products');
        },
      }),
    );

    // --- Optionen ---
    root.querySelector('[data-add-option]')?.addEventListener('click', () => {
      if (draft.options.length >= 3) return toast('Mehr als drei Optionen sind nicht möglich', 'critical');
      syncVariantsFromInputs();
      draft.options.push({ name: '', values: [] });
      paint();
    });

    root.querySelectorAll('[data-remove-option]').forEach((button) =>
      button.addEventListener('click', () => {
        syncVariantsFromInputs();
        draft.options.splice(Number(button.dataset.removeOption), 1);
        paint();
      }),
    );

    root.querySelectorAll('[data-option-name]').forEach((input) =>
      input.addEventListener('input', () => {
        draft.options[Number(input.dataset.optionName)].name = input.value;
      }),
    );
    root.querySelectorAll('[data-option-values]').forEach((input) =>
      input.addEventListener('input', () => {
        draft.options[Number(input.dataset.optionValues)].values = input.value
          .split(',').map((v) => v.trim()).filter(Boolean);
      }),
    );

    root.querySelector('[data-rebuild-variants]')?.addEventListener('click', () => {
      const axes = draft.options.filter((o) => o.name && o.values.length > 0);
      if (axes.length === 0) return toast('Bitte zuerst Optionen mit Werten ausfüllen', 'critical');

      // Bestehende Varianten anhand ihrer Optionswerte wiederfinden, damit
      // Preise, Bestände und SKUs beim Neuaufbau nicht verloren gehen.
      const existing = new Map(
        draft.variants.map((v) => [[v.option1, v.option2, v.option3].filter(Boolean).join('|'), v]),
      );
      const base = draft.variants[0] || {};

      let combos = [[]];
      for (const axis of axes) combos = combos.flatMap((c) => axis.values.map((v) => [...c, v]));

      draft.variants = combos.slice(0, 100).map((combo) => {
        const key = combo.join('|');
        return existing.get(key) || {
          title: combo.join(' / '),
          option1: combo[0] || '', option2: combo[1] || '', option3: combo[2] || '',
          price: base.price || 0,
          compare_at_price: base.compare_at_price ?? null,
          sku: '', inventory_quantity: 0,
          track_inventory: 1, inventory_policy: 'deny',
        };
      });
      paint();
      toast(`${draft.variants.length} Varianten erzeugt`);
    });

    // --- Varianten ---
    root.querySelector('[data-add-variant]')?.addEventListener('click', () => {
      syncVariantsFromInputs();
      draft.variants.push({
        title: 'Neue Variante', price: draft.variants[0]?.price || 0, sku: '',
        inventory_quantity: 0, track_inventory: 1, inventory_policy: 'deny',
      });
      paint();
    });

    root.querySelectorAll('[data-remove-variant]').forEach((button) =>
      button.addEventListener('click', () => {
        if (draft.variants.length <= 1) return toast('Ein Artikel braucht mindestens eine Variante', 'critical');
        syncVariantsFromInputs();
        draft.variants.splice(Number(button.dataset.removeVariant), 1);
        paint();
      }),
    );

    // --- Bilder ---
    const dropzone = root.querySelector('[data-dropzone]');
    const fileInput = root.querySelector('[data-file]');

    const uploadFiles = async (files) => {
      for (const file of files) {
        try {
          const result = await api.upload(file);
          draft.images.push({ url: result.url, alt: '' });
        } catch (error) {
          toast(`${file.name}: ${error.message}`, 'critical');
        }
      }
      syncVariantsFromInputs();
      paint();
    };

    dropzone?.addEventListener('click', () => fileInput.click());
    fileInput?.addEventListener('change', (event) => uploadFiles([...event.target.files]));
    dropzone?.addEventListener('dragover', (event) => {
      event.preventDefault();
      dropzone.classList.add('over');
    });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('over'));
    dropzone?.addEventListener('drop', (event) => {
      event.preventDefault();
      dropzone.classList.remove('over');
      uploadFiles([...event.dataTransfer.files].filter((f) => f.type.startsWith('image/')));
    });

    root.querySelectorAll('[data-remove-image]').forEach((button) =>
      button.addEventListener('click', () => {
        syncVariantsFromInputs();
        draft.images.splice(Number(button.dataset.removeImage), 1);
        paint();
      }),
    );

    // Handle aus dem Titel vorschlagen, solange keiner von Hand gesetzt wurde.
    const titleInput = form.querySelector('[name=title]');
    const handleInput = form.querySelector('[name=handle]');
    if (isNew) {
      titleInput.addEventListener('input', () => {
        handleInput.value = titleInput.value
          .toLowerCase()
          .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
          .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      });
    }
  }

  paint();
}
