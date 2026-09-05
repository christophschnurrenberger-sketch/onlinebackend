/* Rabattcodes. */

import { api } from '../api.js';
import {
  esc, money, moneyInput, date, badge, toast, modal, confirmDialog,
  field, select, checkbox, moneyField, emptyState, formValues,
} from '../ui.js';

const TYPE_LABELS = {
  percentage: 'Prozent',
  fixed: 'Fester Betrag',
  free_shipping: 'Gratisversand',
};

export async function discountsView(root) {
  const render = async () => {
    const data = await api.get('/discounts');

    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Rabatte</h1>
          <div class="subtitle">${data.items.length} Codes</div></div>
        <div class="actions"><button class="btn primary" data-new>Rabattcode anlegen</button></div>
      </div>
      <section class="card"><div class="card-body tight">
        ${data.items.length === 0
          ? emptyState('Keine Rabattcodes', 'Rabattcodes können Kunden im Warenkorb einlösen.',
              '<button class="btn primary" data-new>Rabattcode anlegen</button>')
          : `<div class="table-wrap"><table>
              <thead><tr><th>Code</th><th>Art</th><th>Wert</th><th>Bedingung</th>
                <th class="num">Eingelöst</th><th>Gültig bis</th><th>Status</th><th></th></tr></thead>
              <tbody>${data.items.map((discount) => `
                <tr>
                  <td><strong style="font-family:var(--mono)">${esc(discount.code)}</strong>
                      ${discount.title ? `<div class="cell-sub">${esc(discount.title)}</div>` : ''}</td>
                  <td class="cell-sub">${esc(TYPE_LABELS[discount.type])}</td>
                  <td>${discount.type === 'percentage'
                    ? `${(discount.value / 100).toLocaleString('de-DE')} %`
                    : discount.type === 'fixed' ? money(discount.value) : '—'}</td>
                  <td class="cell-sub">${discount.min_subtotal > 0 ? `ab ${money(discount.min_subtotal)}` : '—'}
                      ${discount.once_per_customer ? '<br />1× pro Kunde' : ''}</td>
                  <td class="num">${discount.used_count}${discount.usage_limit ? ` / ${discount.usage_limit}` : ''}</td>
                  <td class="cell-sub">${discount.ends_at ? esc(date(discount.ends_at)) : 'unbefristet'}</td>
                  <td>${discount.active ? badge('active', 'Aktiv') : badge('draft', 'Inaktiv')}</td>
                  <td class="num">
                    <button class="btn sm plain" data-edit="${discount.id}">Bearbeiten</button>
                    <button class="btn sm plain critical" data-delete="${discount.id}">✕</button>
                  </td>
                </tr>`).join('')}</tbody></table></div>`}
      </div></section>`;

    root.querySelectorAll('[data-new]').forEach((button) =>
      button.addEventListener('click', () => openEditor(null, render)),
    );
    root.querySelectorAll('[data-edit]').forEach((button) =>
      button.addEventListener('click', () =>
        openEditor(data.items.find((d) => d.id === Number(button.dataset.edit)), render),
      ),
    );
    root.querySelectorAll('[data-delete]').forEach((button) =>
      button.addEventListener('click', () => {
        const discount = data.items.find((d) => d.id === Number(button.dataset.delete));
        confirmDialog({
          title: 'Rabattcode löschen',
          message: `Code „${discount.code}“ löschen? Bereits damit abgeschlossene Bestellungen bleiben unberührt.`,
          onConfirm: async () => {
            await api.delete(`/discounts/${discount.id}`);
            toast('Rabattcode gelöscht');
            render();
          },
        });
      }),
    );
  };

  await render();
}

function openEditor(discount, onDone) {
  const isNew = !discount;
  const value = discount
    ? discount.type === 'percentage'
      ? String(discount.value / 100).replace('.', ',')
      : moneyInput(discount.value)
    : '';

  modal({
    title: isNew ? 'Rabattcode anlegen' : `Code ${discount.code}`,
    wide: true,
    body: `
      <div class="field-row">
        ${field({ label: 'Code', name: 'code', value: discount?.code || '', required: true,
          placeholder: 'SOMMER25', hint: 'Wird automatisch großgeschrieben.' })}
        ${field({ label: 'Interner Name', name: 'title', value: discount?.title || '',
          placeholder: 'Sommeraktion 2026' })}
      </div>
      <div class="field-row">
        ${select({ label: 'Art', name: 'type', value: discount?.type || 'percentage',
          options: Object.entries(TYPE_LABELS) })}
        ${field({ label: 'Wert', name: 'value', value,
          hint: 'Prozent (z. B. 10) oder Betrag in Euro. Bei Gratisversand egal.' })}
      </div>
      <div class="field-row">
        ${moneyField({ label: 'Mindestbestellwert', name: 'min_subtotal', value: discount?.min_subtotal || 0 })}
        ${field({ label: 'Maximale Einlösungen', name: 'usage_limit', type: 'number',
          value: discount?.usage_limit ?? '', hint: 'Leer = unbegrenzt' })}
      </div>
      <div class="field-row">
        ${field({ label: 'Gültig ab', name: 'starts_at', type: 'date',
          value: (discount?.starts_at || '').slice(0, 10) })}
        ${field({ label: 'Gültig bis', name: 'ends_at', type: 'date',
          value: (discount?.ends_at || '').slice(0, 10) })}
      </div>
      ${checkbox({ label: 'Nur einmal pro Kunde einlösbar', name: 'once_per_customer',
        checked: Boolean(discount?.once_per_customer) })}
      ${checkbox({ label: 'Aktiv', name: 'active', checked: isNew ? true : Boolean(discount?.active) })}`,
    onSubmit: async (values, form) => {
      const data = formValues(form);
      const payload = {
        code: data.code,
        title: data.title,
        type: data.type,
        value: data.value,
        min_subtotal: data.min_subtotal,
        usage_limit: data.usage_limit === '' ? null : Number(data.usage_limit),
        once_per_customer: data.once_per_customer,
        active: data.active,
        starts_at: data.starts_at ? `${data.starts_at}T00:00:00.000Z` : null,
        ends_at: data.ends_at ? `${data.ends_at}T23:59:59.000Z` : null,
      };
      if (isNew) await api.post('/discounts', payload);
      else await api.put(`/discounts/${discount.id}`, payload);
      toast('Rabattcode gespeichert');
      onDone();
    },
  });
}
