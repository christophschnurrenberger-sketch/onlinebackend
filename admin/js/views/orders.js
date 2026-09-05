/*
 * Bestellungen.
 *
 * Die Detailansicht ist als Arbeitsfläche gedacht: bezahlt markieren,
 * versenden, erstatten, stornieren – jeweils genau ein Klick, mit dem
 * Ereignisverlauf daneben, damit jederzeit klar ist, was passiert ist.
 */

import { api, query } from '../api.js';
import {
  esc, attr, money, moneyInput, badge, dateTime, relativeTime, toast, modal,
  field, textarea, select, checkbox, emptyState, STATUS_LABELS,
} from '../ui.js';
import { navigate } from '../app.js';

const TABS = [
  ['', 'Alle'],
  ['open', 'Offen'],
  ['unpaid', 'Unbezahlt'],
  ['unfulfilled', 'Zu versenden'],
  ['archived', 'Archiviert'],
];

export async function ordersView(root) {
  const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
  const filter = { search: params.get('search') || '', tab: '', offset: 0, limit: 50 };

  const render = async () => {
    const request = { search: filter.search, limit: filter.limit, offset: filter.offset };
    if (filter.tab === 'open') request.status = 'open';
    if (filter.tab === 'archived') request.status = 'archived';
    if (filter.tab === 'unpaid') request.financial_status = 'pending';
    if (filter.tab === 'unfulfilled') request.fulfillment_status = 'unfulfilled';

    const data = await api.get(`/orders${query(request)}`);

    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Bestellungen</h1>
          <div class="subtitle">${data.total} Bestellungen · ${money(data.sum_total)} Umsatz</div></div>
        <div class="actions"><button class="btn" data-export>CSV exportieren</button></div>
      </div>

      <section class="card">
        <div class="tabs">
          ${TABS.map(([value, label]) =>
            `<button class="tab ${filter.tab === value ? 'active' : ''}" data-tab="${value}">${label}</button>`).join('')}
        </div>
        <div class="toolbar">
          <input type="search" placeholder="Bestellnummer, E-Mail oder Name …" value="${attr(filter.search)}" data-search />
        </div>
        <div class="card-body tight">
          ${data.items.length === 0
            ? emptyState('Keine Bestellungen', filter.search || filter.tab
                ? 'Für diesen Filter gibt es keine Bestellungen.'
                : 'Sobald jemand im Shop bestellt, erscheint die Bestellung hier.')
            : `<div class="table-wrap"><table>
                <thead><tr><th>Nr.</th><th>Datum</th><th>Kunde</th><th>Zahlung</th>
                  <th>Versand</th><th class="num">Artikel</th><th class="num">Summe</th></tr></thead>
                <tbody>${data.items.map((order) => `
                  <tr class="clickable" data-id="${order.id}">
                    <td><strong>#${order.number}</strong>
                      ${order.status === 'cancelled' ? `<div>${badge('cancelled')}</div>` : ''}</td>
                    <td class="cell-sub" title="${attr(dateTime(order.created_at))}">${esc(relativeTime(order.created_at))}</td>
                    <td><div class="cell-title">${esc([order.shipping_address?.first_name, order.shipping_address?.last_name].filter(Boolean).join(' ') || '—')}</div>
                        <div class="cell-sub">${esc(order.email)}</div></td>
                    <td>${badge(order.financial_status)}</td>
                    <td>${badge(order.fulfillment_status)}</td>
                    <td class="num">${order.item_count || 0}</td>
                    <td class="num"><strong>${money(order.total)}</strong></td>
                  </tr>`).join('')}</tbody></table></div>`}
        </div>
        ${data.total > filter.limit
          ? `<div class="pagination">
              <span>${filter.offset + 1}–${Math.min(filter.offset + filter.limit, data.total)} von ${data.total}</span>
              <span class="btn-group">
                <button class="btn sm" data-page="prev" ${filter.offset === 0 ? 'disabled' : ''}>Zurück</button>
                <button class="btn sm" data-page="next" ${filter.offset + filter.limit >= data.total ? 'disabled' : ''}>Weiter</button>
              </span></div>`
          : ''}
      </section>`;

    root.querySelectorAll('[data-id]').forEach((row) =>
      row.addEventListener('click', () => navigate(`orders/${row.dataset.id}`)),
    );
    root.querySelectorAll('[data-tab]').forEach((tab) =>
      tab.addEventListener('click', () => {
        filter.tab = tab.dataset.tab;
        filter.offset = 0;
        render();
      }),
    );
    root.querySelectorAll('[data-page]').forEach((button) =>
      button.addEventListener('click', () => {
        filter.offset = Math.max(0, filter.offset + (button.dataset.page === 'next' ? filter.limit : -filter.limit));
        render();
      }),
    );

    let timer;
    root.querySelector('[data-search]')?.addEventListener('input', (event) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        filter.search = event.target.value;
        filter.offset = 0;
        render();
      }, 280);
    });

    root.querySelector('[data-export]')?.addEventListener('click', () => exportOrders(data.items));
  };

  await render();
}

function exportOrders(orders) {
  const header = ['Nummer', 'Datum', 'E-Mail', 'Name', 'Zahlung', 'Versand', 'Zwischensumme', 'Rabatt', 'Versandkosten', 'MwSt', 'Gesamt'];
  const rows = orders.map((order) => [
    order.number, order.created_at, order.email,
    [order.shipping_address?.first_name, order.shipping_address?.last_name].filter(Boolean).join(' '),
    STATUS_LABELS[order.financial_status] || order.financial_status,
    STATUS_LABELS[order.fulfillment_status] || order.fulfillment_status,
    moneyInput(order.subtotal), moneyInput(order.discount_total), moneyInput(order.shipping_total),
    moneyInput(order.tax_total), moneyInput(order.total),
  ]);
  const csv = [header, ...rows]
    .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(';'))
    .join('\n');
  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `bestellungen-${new Date().toISOString().slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

// --- Detailansicht ----------------------------------------------------------

export async function orderDetailView(root, params) {
  const load = async () => (await api.get(`/orders/${params.id}`)).order;
  let order = await load();

  const refresh = async () => {
    order = await load();
    paint();
  };

  const paint = () => {
    const address = order.shipping_address || {};
    const billing = order.billing_address || {};
    const openLines = order.lines.filter((l) => l.requires_shipping && l.fulfilled_quantity < l.quantity);
    const refundable = order.total - order.refunded_total;

    root.innerHTML = `
      <div class="page-header">
        <div class="titles">
          <a class="back-link" href="#/orders">← Alle Bestellungen</a>
          <h1>Bestellung #${order.number}</h1>
          <div class="subtitle">${esc(dateTime(order.created_at))} · ${esc(order.email)}</div>
          <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
            ${badge(order.financial_status)}
            ${badge(order.fulfillment_status)}
            ${order.status === 'cancelled' ? badge('cancelled') : ''}
            ${order.status === 'archived' ? badge('archived') : ''}
          </div>
        </div>
        <div class="actions">
          <a class="btn" href="/order/${attr(order.token)}" target="_blank" rel="noopener">Kundenansicht ↗</a>
          <button class="btn" data-print>Drucken</button>
          ${order.status !== 'cancelled'
            ? `<button class="btn" data-archive>${order.status === 'archived' ? 'Wieder öffnen' : 'Archivieren'}</button>`
            : ''}
        </div>
      </div>

      <div class="grid-2">
        <div>
          <section class="card">
            <div class="card-head"><h2>Positionen</h2>
              ${openLines.length > 0 && order.status !== 'cancelled'
                ? '<button class="btn sm primary" data-fulfill>Versenden</button>' : ''}</div>
            <div class="card-body tight">
              <div class="table-wrap"><table>
                <tbody>${order.lines.map((line) => `
                  <tr>
                    <td class="thumb-cell">${line.image_url
                      ? `<img class="row-thumb" src="${attr(line.image_url)}" alt="" />`
                      : '<div class="row-thumb empty">▦</div>'}</td>
                    <td><div class="cell-title">${esc(line.title)}</div>
                      <div class="cell-sub">
                        ${line.variant_title && line.variant_title !== 'Standard' ? `${esc(line.variant_title)} · ` : ''}
                        ${line.sku ? `${esc(line.sku)} · ` : ''}
                        ${line.fulfilled_quantity > 0
                          ? `${line.fulfilled_quantity} von ${line.quantity} versendet`
                          : `${line.quantity} Stück`}
                      </div></td>
                    <td class="num cell-sub">${money(line.price)} × ${line.quantity}</td>
                    <td class="num"><strong>${money(line.total)}</strong>
                      ${line.discount > 0 ? `<div class="cell-sub">−${money(line.discount)}</div>` : ''}</td>
                  </tr>`).join('')}</tbody></table></div>
            </div>
            <div class="card-body" style="border-top:1px solid var(--border)">
              ${summaryRow('Zwischensumme', money(order.subtotal))}
              ${order.discount_total > 0 ? summaryRow(`Rabatt ${esc(order.discount_code)}`, `−${money(order.discount_total)}`) : ''}
              ${summaryRow(`Versand${order.shipping_method ? ` (${esc(order.shipping_method)})` : ''}`, money(order.shipping_total))}
              ${summaryRow('enthaltene MwSt.', money(order.tax_total), true)}
              <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:8px">
                ${summaryRow('<strong>Gesamt</strong>', `<strong>${money(order.total)}</strong>`)}
              </div>
              ${order.refunded_total > 0 ? summaryRow('Erstattet', `−${money(order.refunded_total)}`, true) : ''}
            </div>
            ${order.status !== 'cancelled'
              ? `<div class="card-footer">
                  ${order.financial_status === 'pending' ? '<button class="btn primary" data-mark-paid>Als bezahlt markieren</button>' : ''}
                  ${refundable > 0 && order.financial_status !== 'pending' ? '<button class="btn" data-refund>Erstatten</button>' : ''}
                  <button class="btn critical" data-cancel>Stornieren</button>
                </div>`
              : ''}
          </section>

          ${order.fulfillments.length > 0
            ? `<section class="card">
                <div class="card-head"><h2>Sendungen</h2></div>
                <div class="card-body tight"><div class="table-wrap"><table>
                  <tbody>${order.fulfillments.map((f) => `
                    <tr><td><div class="cell-title">${esc(f.carrier || 'Versand')}</div>
                            <div class="cell-sub">${esc(dateTime(f.created_at))}</div></td>
                        <td>${f.tracking_number ? esc(f.tracking_number) : '—'}</td>
                        <td class="num">${f.tracking_url ? `<a href="${attr(f.tracking_url)}" target="_blank" rel="noopener">Verfolgen ↗</a>` : ''}</td>
                    </tr>`).join('')}</tbody></table></div></div>
              </section>`
            : ''}

          ${order.refunds.length > 0
            ? `<section class="card">
                <div class="card-head"><h2>Erstattungen</h2></div>
                <div class="card-body tight"><div class="table-wrap"><table>
                  <tbody>${order.refunds.map((r) => `
                    <tr><td class="cell-sub">${esc(dateTime(r.created_at))}</td>
                        <td>${esc(r.reason || '—')}</td>
                        <td class="num"><strong>${money(r.amount)}</strong></td></tr>`).join('')}
                </tbody></table></div></div></section>`
            : ''}
        </div>

        <div>
          <section class="card">
            <div class="card-head"><h2>Kunde</h2></div>
            <div class="card-body">
              ${order.customer_id
                ? `<a href="#/customers/${order.customer_id}"><strong>${esc([address.first_name, address.last_name].filter(Boolean).join(' ') || order.email)}</strong></a>`
                : `<strong>${esc([address.first_name, address.last_name].filter(Boolean).join(' ') || 'Gast')}</strong>`}
              <div class="cell-sub" style="margin-top:4px">${esc(order.email)}</div>
              ${order.phone ? `<div class="cell-sub">${esc(order.phone)}</div>` : ''}
              <h3 style="margin-top:14px;color:var(--text-muted);font-size:12px;text-transform:uppercase">Lieferadresse</h3>
              <div class="cell-sub">${formatAddress(address)}</div>
              ${JSON.stringify(billing) !== JSON.stringify(address)
                ? `<h3 style="margin-top:12px;color:var(--text-muted);font-size:12px;text-transform:uppercase">Rechnungsadresse</h3>
                   <div class="cell-sub">${formatAddress(billing)}</div>`
                : ''}
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Zahlung</h2></div>
            <div class="card-body">
              <div class="field-inline" style="justify-content:space-between">
                <span>Zahlart</span><strong>${esc(order.payment_provider || '—')}</strong></div>
              ${order.payment_reference
                ? `<div class="field-inline" style="justify-content:space-between">
                    <span>Referenz</span><span class="cell-sub" style="word-break:break-all">${esc(order.payment_reference)}</span></div>`
                : ''}
              ${order.paid_at
                ? `<div class="field-inline" style="justify-content:space-between;margin-bottom:0">
                    <span>Bezahlt am</span><span class="cell-sub">${esc(dateTime(order.paid_at))}</span></div>`
                : ''}
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Interne Notiz</h2></div>
            <div class="card-body">
              <div class="field" style="margin:0">
                <textarea rows="3" data-note placeholder="Nur im Backend sichtbar">${esc(order.note)}</textarea>
              </div>
              <button class="btn sm" data-save-note style="margin-top:8px">Notiz speichern</button>
              ${order.customer_note
                ? `<div class="banner info" style="margin:12px 0 0"><div class="banner-body">
                    <strong>Anmerkung des Kunden</strong>${esc(order.customer_note)}</div></div>`
                : ''}
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Verlauf</h2></div>
            <div class="card-body">
              <ul class="timeline">
                ${order.events.map((event) => `
                  <li>${esc(event.message)}
                    <time>${esc(dateTime(event.created_at))}${event.user_name ? ` · ${esc(event.user_name)}` : ''}</time>
                  </li>`).join('')}
              </ul>
            </div>
          </section>
        </div>
      </div>`;

    bind();
  };

  function bind() {
    root.querySelector('[data-print]')?.addEventListener('click', () => window.print());

    root.querySelector('[data-mark-paid]')?.addEventListener('click', () =>
      modal({
        title: 'Als bezahlt markieren',
        body: `<p>Die Bestellung wird auf „bezahlt“ gesetzt. Beim Zahlungsanbieter passiert dabei nichts –
          das ist für Vorkasse, Rechnung und Überweisungen gedacht.</p>
          ${field({ label: 'Referenz (optional)', name: 'reference', placeholder: 'z. B. Verwendungszweck oder Beleg-Nr.' })}`,
        confirmLabel: 'Als bezahlt markieren',
        onSubmit: async (values) => {
          await api.post(`/orders/${params.id}/paid`, { reference: values.reference });
          toast('Als bezahlt markiert');
          await refresh();
        },
      }),
    );

    root.querySelector('[data-fulfill]')?.addEventListener('click', () =>
      modal({
        title: 'Sendung anlegen',
        body: `${select({ label: 'Versanddienstleister', name: 'carrier', options: [
            ['DHL', 'DHL'], ['DPD', 'DPD'], ['Hermes', 'Hermes'], ['GLS', 'GLS'],
            ['UPS', 'UPS'], ['Deutsche Post', 'Deutsche Post'], ['', 'Anderer / kein Tracking'],
          ] })}
          ${field({ label: 'Sendungsnummer', name: 'tracking_number' })}
          ${field({ label: 'Tracking-Link (optional)', name: 'tracking_url', type: 'url' })}
          <p class="hint">Alle noch offenen Positionen werden als versendet markiert.</p>`,
        confirmLabel: 'Als versendet markieren',
        onSubmit: async (values) => {
          await api.post(`/orders/${params.id}/fulfill`, {
            carrier: values.carrier,
            tracking_number: values.tracking_number,
            tracking_url: values.tracking_url,
          });
          toast('Sendung angelegt');
          await refresh();
        },
      }),
    );

    root.querySelector('[data-refund]')?.addEventListener('click', () => {
      const refundable = order.total - order.refunded_total;
      modal({
        title: 'Betrag erstatten',
        body: `<p>Höchstens ${money(refundable)} erstattbar. Bei Stripe und PayPal wird die
            Erstattung dort ausgelöst; bei anderen Zahlarten nur dokumentiert.</p>
          ${field({ label: 'Betrag in Euro', name: 'amount', value: moneyInput(refundable) })}
          ${textarea({ label: 'Grund', name: 'reason', rows: 2 })}
          ${checkbox({ label: 'Artikel zurück in den Bestand buchen', name: 'restock' })}`,
        confirmLabel: 'Erstatten',
        tone: 'critical',
        onSubmit: async (values) => {
          const cents = Math.round(parseFloat(String(values.amount).replace(',', '.')) * 100);
          if (!Number.isFinite(cents) || cents <= 0) {
            toast('Bitte einen gültigen Betrag eingeben', 'critical');
            return false;
          }
          await api.post(`/orders/${params.id}/refund`, {
            amount: cents,
            reason: values.reason,
            restock: values.restock === 'on',
          });
          toast('Erstattung erfasst');
          await refresh();
        },
      });
    });

    root.querySelector('[data-cancel]')?.addEventListener('click', () =>
      modal({
        title: 'Bestellung stornieren',
        body: `${textarea({ label: 'Grund', name: 'reason', rows: 2 })}
          ${checkbox({ label: 'Artikel zurück in den Bestand buchen', name: 'restock', checked: true })}
          <p class="hint">Eine bereits erfolgte Zahlung wird dadurch nicht erstattet.</p>`,
        confirmLabel: 'Stornieren',
        tone: 'critical',
        onSubmit: async (values) => {
          await api.post(`/orders/${params.id}/cancel`, {
            reason: values.reason,
            restock: values.restock === 'on',
          });
          toast('Bestellung storniert');
          await refresh();
        },
      }),
    );

    root.querySelector('[data-archive]')?.addEventListener('click', async () => {
      await api.post(`/orders/${params.id}/archive`, { archived: order.status !== 'archived' });
      await refresh();
    });

    root.querySelector('[data-save-note]')?.addEventListener('click', async () => {
      await api.post(`/orders/${params.id}/note`, { note: root.querySelector('[data-note]').value });
      toast('Notiz gespeichert');
      await refresh();
    });
  }

  paint();
}

const summaryRow = (label, value, muted = false) => `
  <div class="field-inline" style="justify-content:space-between;margin-bottom:6px${muted ? ';color:var(--text-muted)' : ''}">
    <span>${label}</span><span>${value}</span></div>`;

function formatAddress(address) {
  const lines = [
    [address.first_name, address.last_name].filter(Boolean).join(' '),
    address.company,
    address.address1,
    address.address2,
    [address.zip, address.city].filter(Boolean).join(' '),
    address.country,
  ].filter(Boolean);
  return lines.map((line) => esc(line)).join('<br />') || '—';
}
