/* Kunden: Liste, Detailansicht mit Bestellhistorie und Adressen. */

import { api, query } from '../api.js';
import {
  esc, attr, money, number, badge, date, toast, modal, confirmDialog,
  field, textarea, checkbox, emptyState, formValues,
} from '../ui.js';
import { navigate } from '../app.js';

export async function customersView(root) {
  const filter = { search: '', sort: 'created-desc', offset: 0, limit: 50 };

  const render = async () => {
    const data = await api.get(`/customers${query(filter)}`);

    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Kunden</h1>
          <div class="subtitle">${data.total} Kunden</div></div>
        <div class="actions"><button class="btn primary" data-new>Kunde anlegen</button></div>
      </div>
      <section class="card">
        <div class="toolbar">
          <input type="search" placeholder="Name, E-Mail oder Firma …" value="${attr(filter.search)}" data-search />
          <select data-sort>
            ${[['created-desc', 'Neueste zuerst'], ['spent-desc', 'Höchster Umsatz'],
               ['orders-desc', 'Meiste Bestellungen'], ['name-asc', 'Name A–Z']]
              .map(([value, label]) => `<option value="${value}" ${filter.sort === value ? 'selected' : ''}>${label}</option>`).join('')}
          </select>
        </div>
        <div class="card-body tight">
          ${data.items.length === 0
            ? emptyState('Keine Kunden', 'Kunden entstehen automatisch mit der ersten Bestellung.')
            : `<div class="table-wrap"><table>
                <thead><tr><th>Kunde</th><th>Ort</th><th class="num">Bestellungen</th>
                  <th class="num">Umsatz</th><th>Kunde seit</th></tr></thead>
                <tbody>${data.items.map((customer) => `
                  <tr class="clickable" data-id="${customer.id}">
                    <td><div class="cell-title">${esc([customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.email)}</div>
                        <div class="cell-sub">${esc(customer.email)}</div></td>
                    <td class="cell-sub">${esc(customer.company || '—')}</td>
                    <td class="num">${number(customer.orders_count)}</td>
                    <td class="num"><strong>${money(customer.total_spent)}</strong></td>
                    <td class="cell-sub">${esc(date(customer.created_at))}</td>
                  </tr>`).join('')}</tbody></table></div>`}
        </div>
      </section>`;

    root.querySelectorAll('[data-id]').forEach((row) =>
      row.addEventListener('click', () => navigate(`customers/${row.dataset.id}`)),
    );
    root.querySelector('[data-sort]')?.addEventListener('change', (event) => {
      filter.sort = event.target.value;
      render();
    });

    let timer;
    root.querySelector('[data-search]')?.addEventListener('input', (event) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        filter.search = event.target.value;
        filter.offset = 0;
        render();
      }, 280);
    });

    root.querySelector('[data-new]')?.addEventListener('click', () =>
      modal({
        title: 'Kunde anlegen',
        body: `${field({ label: 'E-Mail', name: 'email', type: 'email', required: true })}
          <div class="field-row">
            ${field({ label: 'Vorname', name: 'first_name' })}
            ${field({ label: 'Nachname', name: 'last_name' })}
          </div>
          ${field({ label: 'Firma', name: 'company' })}
          ${field({ label: 'Telefon', name: 'phone' })}
          ${checkbox({ label: 'Newsletter erlaubt', name: 'accepts_marketing' })}`,
        confirmLabel: 'Anlegen',
        onSubmit: async (values, form) => {
          const data = formValues(form);
          const result = await api.post('/customers', data);
          toast('Kunde angelegt');
          navigate(`customers/${result.customer.id}`);
        },
      }),
    );
  };

  await render();
}

export async function customerDetailView(root, params) {
  const load = async () => (await api.get(`/customers/${params.id}`)).customer;
  let customer = await load();

  const paint = () => {
    root.innerHTML = `
      <div class="page-header">
        <div class="titles">
          <a class="back-link" href="#/customers">← Alle Kunden</a>
          <h1>${esc([customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.email)}</h1>
          <div class="subtitle">Kunde seit ${esc(date(customer.created_at))}</div>
        </div>
        <div class="actions"><button class="btn" data-edit>Bearbeiten</button></div>
      </div>

      <div class="grid-2">
        <div>
          <section class="card">
            <div class="card-head"><h2>Bestellungen</h2></div>
            <div class="card-body tight">
              ${customer.orders.length === 0
                ? emptyState('Noch keine Bestellungen', 'Dieser Kunde hat bisher nichts bestellt.')
                : `<div class="table-wrap"><table>
                    <thead><tr><th>Nr.</th><th>Datum</th><th>Zahlung</th><th>Versand</th><th class="num">Summe</th></tr></thead>
                    <tbody>${customer.orders.map((order) => `
                      <tr class="clickable" data-order="${order.id}">
                        <td><strong>#${order.number}</strong></td>
                        <td class="cell-sub">${esc(date(order.created_at))}</td>
                        <td>${badge(order.financial_status)}</td>
                        <td>${badge(order.fulfillment_status)}</td>
                        <td class="num">${money(order.total)}</td>
                      </tr>`).join('')}</tbody></table></div>`}
            </div>
          </section>
        </div>

        <div>
          <div class="grid-3" style="grid-template-columns:1fr 1fr;margin-bottom:16px">
            <div class="metric"><div class="label">Bestellungen</div><div class="value">${number(customer.orders_count)}</div></div>
            <div class="metric"><div class="label">Umsatz</div><div class="value">${money(customer.total_spent)}</div></div>
          </div>

          <section class="card">
            <div class="card-head"><h2>Kontakt</h2></div>
            <div class="card-body">
              <div class="cell-title">${esc(customer.email)}</div>
              ${customer.phone ? `<div class="cell-sub">${esc(customer.phone)}</div>` : ''}
              ${customer.company ? `<div class="cell-sub">${esc(customer.company)}</div>` : ''}
              <div style="margin-top:10px">${customer.accepts_marketing
                ? badge('active', 'Newsletter erlaubt')
                : badge('draft', 'Kein Newsletter')}</div>
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h2>Adressen</h2></div>
            <div class="card-body">
              ${customer.addresses.length === 0
                ? '<p class="hint">Keine Adresse hinterlegt.</p>'
                : customer.addresses.map((address) => `
                    <div style="padding:8px 0;border-bottom:1px solid var(--border)">
                      ${address.is_default ? `${badge('active', 'Standard')}<br />` : ''}
                      <span class="cell-sub">${[address.address1, address.address2,
                        [address.zip, address.city].filter(Boolean).join(' '), address.country]
                        .filter(Boolean).map((l) => esc(l)).join('<br />')}</span>
                    </div>`).join('')}
            </div>
          </section>

          ${customer.note
            ? `<section class="card"><div class="card-head"><h2>Notiz</h2></div>
               <div class="card-body"><p>${esc(customer.note)}</p></div></section>`
            : ''}

          <section class="card"><div class="card-body">
            <button class="btn critical" data-delete style="width:100%">Kunde löschen</button>
          </div></section>
        </div>
      </div>`;

    root.querySelectorAll('[data-order]').forEach((row) =>
      row.addEventListener('click', () => navigate(`orders/${row.dataset.order}`)),
    );

    root.querySelector('[data-edit]')?.addEventListener('click', () =>
      modal({
        title: 'Kunde bearbeiten',
        body: `${field({ label: 'E-Mail', name: 'email', value: customer.email, type: 'email', required: true })}
          <div class="field-row">
            ${field({ label: 'Vorname', name: 'first_name', value: customer.first_name })}
            ${field({ label: 'Nachname', name: 'last_name', value: customer.last_name })}
          </div>
          ${field({ label: 'Firma', name: 'company', value: customer.company })}
          ${field({ label: 'Telefon', name: 'phone', value: customer.phone })}
          ${textarea({ label: 'Interne Notiz', name: 'note', value: customer.note, rows: 3 })}
          ${checkbox({ label: 'Newsletter erlaubt', name: 'accepts_marketing', checked: Boolean(customer.accepts_marketing) })}`,
        onSubmit: async (values, form) => {
          await api.put(`/customers/${params.id}`, formValues(form));
          toast('Kunde gespeichert');
          customer = await load();
          paint();
        },
      }),
    );

    root.querySelector('[data-delete]')?.addEventListener('click', () =>
      confirmDialog({
        title: 'Kunde löschen',
        message: 'Das Kundenkonto wird gelöscht. Bereits abgeschlossene Bestellungen bleiben erhalten.',
        onConfirm: async () => {
          await api.delete(`/customers/${params.id}`);
          toast('Kunde gelöscht');
          navigate('customers');
        },
      }),
    );
  };

  paint();
}
