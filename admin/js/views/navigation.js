/*
 * Navigation.
 *
 * Zwei Menüs: Kopf- und Fußzeile. Das Kopfmenü darf eine zweite Ebene haben –
 * tiefer verschachtelte Shop-Navigationen sind auf Mobilgeräten unbedienbar.
 */

import { api } from '../api.js';
import { esc, attr, toast } from '../ui.js';
import { markChanged } from '../app.js';

const MENUS = [
  { handle: 'main', title: 'Hauptmenü (Kopfzeile)', nested: true },
  { handle: 'footer', title: 'Fußzeile', nested: false },
];

export async function navigationView(root) {
  const data = await api.get('/menus');
  const state = Object.fromEntries(
    MENUS.map((menu) => [menu.handle, structuredClone(data.items.find((m) => m.handle === menu.handle)?.items || [])]),
  );

  const paint = () => {
    root.innerHTML = `
      <div class="page-header">
        <div class="titles"><h1>Navigation</h1>
          <div class="subtitle">Menüpunkte für Kopf- und Fußzeile des Shops</div></div>
        <div class="actions"><button class="btn primary" data-save>Speichern</button></div>
      </div>

      <div class="banner info"><div class="banner-body">
        <strong>Adressen im Shop</strong>
        Kategorien: <code>/collections/handle</code> · Artikel: <code>/products/handle</code> ·
        Seiten: <code>/pages/handle</code> · Journal: <code>/blog</code>
      </div></div>

      ${MENUS.map((menu) => `
        <section class="card">
          <div class="card-head"><h2>${esc(menu.title)}</h2>
            <button class="btn sm" data-add="${menu.handle}">Punkt hinzufügen</button></div>
          <div class="card-body">
            ${state[menu.handle].length === 0
              ? '<p class="hint">Noch keine Menüpunkte.</p>'
              : state[menu.handle].map((item, index) => itemRow(menu, item, index)).join('')}
          </div>
        </section>`).join('')}`;

    bind();
  };

  function itemRow(menu, item, index, parentIndex = null) {
    const path = parentIndex === null ? `${index}` : `${parentIndex}.${index}`;
    return `
      <div class="option-editor" style="${parentIndex !== null ? 'margin-left:24px;background:var(--surface)' : ''}">
        <div class="field-row" style="margin-bottom:8px">
          <div class="field" style="margin:0">
            <label>Beschriftung</label>
            <input type="text" value="${attr(item.label)}" data-menu="${menu.handle}" data-path="${path}" data-key="label" />
          </div>
          <div class="field" style="margin:0">
            <label>Adresse</label>
            <input type="text" value="${attr(item.url)}" data-menu="${menu.handle}" data-path="${path}" data-key="url" />
          </div>
        </div>
        <div class="btn-group">
          <button class="btn sm plain" data-move="${menu.handle}:${path}:-1" type="button">↑</button>
          <button class="btn sm plain" data-move="${menu.handle}:${path}:1" type="button">↓</button>
          ${menu.nested && parentIndex === null
            ? `<button class="btn sm plain" data-add-child="${menu.handle}:${index}" type="button">Unterpunkt</button>`
            : ''}
          <button class="btn sm plain critical" data-remove="${menu.handle}:${path}" type="button">Entfernen</button>
        </div>
        ${(item.children || []).map((child, childIndex) => itemRow(menu, child, childIndex, index)).join('')}
      </div>`;
  }

  const resolve = (handle, path) => {
    const parts = path.split('.').map(Number);
    if (parts.length === 1) return { list: state[handle], index: parts[0] };
    const parent = state[handle][parts[0]];
    parent.children = parent.children || [];
    return { list: parent.children, index: parts[1] };
  };

  function bind() {
    root.querySelectorAll('[data-menu]').forEach((input) =>
      input.addEventListener('input', () => {
        const { list, index } = resolve(input.dataset.menu, input.dataset.path);
        list[index][input.dataset.key] = input.value;
      }),
    );

    root.querySelectorAll('[data-add]').forEach((button) =>
      button.addEventListener('click', () => {
        state[button.dataset.add].push({ label: 'Neuer Punkt', url: '/', children: [] });
        paint();
      }),
    );

    root.querySelectorAll('[data-add-child]').forEach((button) =>
      button.addEventListener('click', () => {
        const [handle, index] = button.dataset.addChild.split(':');
        const parent = state[handle][Number(index)];
        parent.children = parent.children || [];
        parent.children.push({ label: 'Unterpunkt', url: '/', children: [] });
        paint();
      }),
    );

    root.querySelectorAll('[data-remove]').forEach((button) =>
      button.addEventListener('click', () => {
        const [handle, path] = button.dataset.remove.split(':');
        const { list, index } = resolve(handle, path);
        list.splice(index, 1);
        paint();
      }),
    );

    root.querySelectorAll('[data-move]').forEach((button) =>
      button.addEventListener('click', () => {
        const [handle, path, direction] = button.dataset.move.split(':');
        const { list, index } = resolve(handle, path);
        const target = index + Number(direction);
        if (target < 0 || target >= list.length) return;
        [list[index], list[target]] = [list[target], list[index]];
        paint();
      }),
    );

    root.querySelector('[data-save]').addEventListener('click', async () => {
      try {
        for (const menu of MENUS) {
          await api.put(`/menus/${menu.handle}`, { title: menu.title, items: state[menu.handle] });
        }
        toast('Navigation gespeichert');
        await markChanged();
      } catch (error) {
        toast(error.message, 'critical');
      }
    });
  }

  paint();
}
