/*
 * Übersicht.
 *
 * Beantwortet die drei Fragen, mit denen ein Betreiber den Tag beginnt: Wie
 * lief es? Was liegt an? Und was fehlt gerade im Lager?
 */

import { api } from '../api.js';
import { money, number, esc, badge, relativeTime, emptyState } from '../ui.js';
import { navigate } from '../app.js';

export async function dashboardView(root, params = {}) {
  const days = Number(params.days) || 30;
  const data = await api.get(`/dashboard?days=${days}`);
  const { stats, counts, low_stock: lowStock, recent_orders: recentOrders, pending_changes: pending } = data;

  const revenueDelta = delta(stats.revenue, stats.previous_revenue);
  const ordersDelta = delta(stats.orders, stats.previous_orders);
  const maxDaily = Math.max(1, ...stats.daily.map((d) => d.revenue));

  root.innerHTML = `
    <div class="page-header">
      <div class="titles">
        <h1>Übersicht</h1>
        <div class="subtitle">Letzte ${stats.days} Tage</div>
      </div>
      <div class="actions">
        <select data-days>
          ${[7, 30, 90, 365].map((d) => `<option value="${d}" ${d === stats.days ? 'selected' : ''}>${d} Tage</option>`).join('')}
        </select>
        <button class="btn primary" data-new-product>Artikel anlegen</button>
      </div>
    </div>

    ${pending.never_published
      ? `<div class="banner warning"><div class="banner-body">
          <strong>Der Shop ist noch nicht veröffentlicht</strong>
          Besucher sehen bisher nichts. Klicke oben rechts auf „Veröffentlichen“, sobald du bereit bist.
        </div></div>`
      : pending.count > 0
        ? `<div class="banner info"><div class="banner-body">
            <strong>${pending.count} Änderung${pending.count === 1 ? '' : 'en'} wartet auf Veröffentlichung</strong>
            <a href="#/publish">Änderungen ansehen und veröffentlichen</a>
          </div></div>`
        : ''}

    <div class="grid-4" style="margin-bottom:16px">
      <div class="metric">
        <div class="label">Umsatz</div>
        <div class="value">${money(stats.revenue)}</div>
        ${revenueDelta}
        ${stats.daily.length > 1
          ? `<div class="sparkline">${stats.daily
              .map((d) => `<div class="bar" style="height:${Math.round((d.revenue / maxDaily) * 100)}%" title="${esc(d.day)}: ${money(d.revenue)}"></div>`)
              .join('')}</div>`
          : ''}
      </div>
      <div class="metric">
        <div class="label">Bestellungen</div>
        <div class="value">${number(stats.orders)}</div>
        ${ordersDelta}
      </div>
      <div class="metric">
        <div class="label">Ø Bestellwert</div>
        <div class="value">${money(stats.average_order_value)}</div>
      </div>
      <div class="metric">
        <div class="label">Zu erledigen</div>
        <div class="value">${number(stats.unfulfilled)}</div>
        <div class="delta">${number(stats.unpaid)} unbezahlt · ${number(stats.open_orders)} offen</div>
      </div>
    </div>

    <div class="grid-2">
      <section class="card">
        <div class="card-head"><h2>Neueste Bestellungen</h2>
          <a class="btn sm" href="#/orders">Alle ansehen</a></div>
        <div class="card-body tight">
          ${recentOrders.length === 0
            ? emptyState('Noch keine Bestellungen', 'Sobald jemand bestellt, erscheint die Bestellung hier.')
            : `<div class="table-wrap"><table>
                <thead><tr><th>Nr.</th><th>Kunde</th><th>Status</th><th class="num">Summe</th><th>Zeit</th></tr></thead>
                <tbody>${recentOrders.map((order) => `
                  <tr class="clickable" data-order="${order.id}">
                    <td><strong>#${order.number}</strong></td>
                    <td>${esc(order.shipping_address?.first_name || '')} ${esc(order.shipping_address?.last_name || order.email)}</td>
                    <td>${badge(order.financial_status)}</td>
                    <td class="num">${money(order.total)}</td>
                    <td class="cell-sub">${esc(relativeTime(order.created_at))}</td>
                  </tr>`).join('')}</tbody></table></div>`}
        </div>
      </section>

      <div>
        <section class="card">
          <div class="card-head"><h2>Katalog</h2></div>
          <div class="card-body">
            <div class="field-inline" style="justify-content:space-between">
              <span>Aktive Artikel</span><strong>${number(counts.active_products)}</strong></div>
            <div class="field-inline" style="justify-content:space-between">
              <span>Entwürfe</span><strong>${number(counts.draft_products)}</strong></div>
            <div class="field-inline" style="justify-content:space-between">
              <span>Kategorien</span><strong>${number(counts.collections)}</strong></div>
            <div class="field-inline" style="justify-content:space-between;margin-bottom:0">
              <span>Kunden</span><strong>${number(counts.customers)}</strong></div>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>Bestand wird knapp</h2></div>
          <div class="card-body tight">
            ${lowStock.length === 0
              ? `<div class="empty" style="padding:24px"><p>Alle Bestände sind in Ordnung.</p></div>`
              : `<div class="table-wrap"><table><tbody>
                  ${lowStock.map((item) => `
                    <tr class="clickable" data-product="${item.product_id}">
                      <td><div class="cell-title">${esc(item.product_title)}</div>
                        <div class="cell-sub">${esc(item.variant_title)}${item.sku ? ` · ${esc(item.sku)}` : ''}</div></td>
                      <td class="num">${badge(item.inventory_quantity <= 0 ? 'cancelled' : 'pending', `${item.inventory_quantity} Stück`)}</td>
                    </tr>`).join('')}
                </tbody></table></div>`}
          </div>
        </section>

        ${stats.top_products.length > 0
          ? `<section class="card">
              <div class="card-head"><h2>Meistverkauft</h2></div>
              <div class="card-body tight"><div class="table-wrap"><table><tbody>
                ${stats.top_products.map((product) => `
                  <tr><td>${esc(product.title)}<div class="cell-sub">${number(product.quantity)} verkauft</div></td>
                      <td class="num">${money(product.revenue)}</td></tr>`).join('')}
              </tbody></table></div></div>
            </section>`
          : ''}
      </div>
    </div>`;

  root.querySelectorAll('[data-order]').forEach((row) =>
    row.addEventListener('click', () => navigate(`orders/${row.dataset.order}`)),
  );
  root.querySelectorAll('[data-product]').forEach((row) =>
    row.addEventListener('click', () => navigate(`products/${row.dataset.product}`)),
  );
  root.querySelector('[data-new-product]')?.addEventListener('click', () => navigate('products/new'));

  // Der Zeitraum ist eine Ansichtsoption, keine eigene Route – deshalb wird
  // die View direkt neu gerendert statt über den Router zu gehen.
  root.querySelector('[data-days]')?.addEventListener('change', (event) => {
    dashboardView(root, { days: event.target.value });
  });
}

function delta(current, previous) {
  if (!previous) return '<div class="delta">kein Vorzeitraum</div>';
  const percent = Math.round(((current - previous) / previous) * 100);
  const direction = percent >= 0 ? 'up' : 'down';
  return `<div class="delta ${direction}">${percent >= 0 ? '▲' : '▼'} ${Math.abs(percent)} % zum Vorzeitraum</div>`;
}
