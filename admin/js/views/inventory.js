/*
 * Bestandsübersicht.
 *
 * Eine Tabelle, in der Mengen direkt änderbar sind – Lagerkorrekturen macht
 * niemand gern über zehn Einzelseiten. Jede Änderung erzeugt serverseitig eine
 * Bestandsbewegung, die im Verlauf nachvollziehbar bleibt.
 */

import { api } from '../api.js';
import { esc, attr, money, badge, toast, modal, dateTime, emptyState } from '../ui.js';

export async function inventoryView(root) {
  let search = '';

  const render = async () => {
    const data = await api.get(`/inventory${search ? `?search=${encodeURIComponent(search)}` : ''}`);

    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Bestand</h1>
          <div class="subtitle">${data.items.length} Varianten</div></div>
      </div>
      <section class="card">
        <div class="toolbar">
          <input type="search" placeholder="Artikel oder Artikelnummer suchen …" value="${attr(search)}" data-search />
        </div>
        <div class="card-body tight">
          ${data.items.length === 0
            ? emptyState('Keine Varianten', 'Lege zuerst Artikel an.')
            : `<div class="table-wrap"><table>
                <thead><tr><th>Artikel</th><th>Artikelnr.</th><th>Status</th>
                  <th class="num">Preis</th><th class="num" style="width:150px">Bestand</th><th></th></tr></thead>
                <tbody>${data.items.map((item) => `
                  <tr>
                    <td><div class="cell-title">${esc(item.product_title)}</div>
                        <div class="cell-sub">${esc(item.variant_title)}</div></td>
                    <td class="cell-sub">${esc(item.sku || '—')}</td>
                    <td>${badge(item.status)}</td>
                    <td class="num">${money(item.price)}</td>
                    <td class="num">
                      ${item.track_inventory
                        ? `<input type="number" value="${item.inventory_quantity}" data-stock="${item.id}"
                             style="width:80px;padding:5px 8px;border:1px solid var(--border-strong);border-radius:6px;text-align:right;font:inherit" />`
                        : '<span class="cell-sub">nicht geführt</span>'}
                    </td>
                    <td class="num"><button class="btn sm plain" data-history="${item.id}" title="Verlauf">≡</button></td>
                  </tr>`).join('')}</tbody></table></div>`}
        </div>
      </section>`;

    let timer;
    root.querySelector('[data-search]')?.addEventListener('input', (event) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        search = event.target.value;
        render();
      }, 280);
    });

    // Speichern beim Verlassen des Feldes: so bleibt Tippen flüssig und es
    // entsteht pro Korrektur genau eine Bestandsbewegung.
    root.querySelectorAll('[data-stock]').forEach((input) => {
      const original = input.value;
      input.addEventListener('blur', async () => {
        if (input.value === original) return;
        try {
          await api.post(`/inventory/${input.dataset.stock}`, { set: Number(input.value), reason: 'correction' });
          toast('Bestand aktualisiert');
        } catch (error) {
          toast(error.message, 'critical');
          input.value = original;
        }
      });
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') input.blur();
      });
    });

    root.querySelectorAll('[data-history]').forEach((button) =>
      button.addEventListener('click', async () => {
        const { moves } = await api.get(`/inventory/${button.dataset.history}/moves`);
        modal({
          title: 'Bestandsverlauf',
          confirmLabel: 'Schließen',
          wide: true,
          body: moves.length === 0
            ? '<p class="hint">Noch keine Bewegungen aufgezeichnet.</p>'
            : `<div class="table-wrap"><table>
                <thead><tr><th>Zeit</th><th>Menge</th><th>Grund</th><th>Bestellung</th><th>Von</th></tr></thead>
                <tbody>${moves.map((move) => `
                  <tr><td class="cell-sub">${esc(dateTime(move.created_at))}</td>
                      <td><strong style="color:${move.delta < 0 ? 'var(--critical)' : 'var(--success)'}">
                        ${move.delta > 0 ? '+' : ''}${move.delta}</strong></td>
                      <td>${esc(REASONS[move.reason] || move.reason)}</td>
                      <td>${move.order_number ? `#${move.order_number}` : '—'}</td>
                      <td class="cell-sub">${esc(move.user_name || 'System')}</td></tr>`).join('')}
                </tbody></table></div>`,
          onSubmit: () => true,
        });
      }),
    );
  };

  await render();
}

const REASONS = {
  sale: 'Verkauf',
  restock: 'Wareneingang',
  correction: 'Korrektur',
  refund: 'Retoure',
  cancel: 'Storno',
};
