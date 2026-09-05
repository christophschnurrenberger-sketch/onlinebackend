/*
 * Redaktionelle Inhalte: Seiten, Blogbeiträge und Navigationsmenüs.
 *
 * Seiten und Beiträge teilen sich fast dieselbe Form, deshalb erzeugt eine
 * Fabrik beide CRUD-Sätze. Die Menüs bilden eine zweistufige Navigation ab –
 * mehr Ebenen braucht ein Shop-Header praktisch nie.
 */

import { all, get, run, insert, update, remove, transaction, nowIso } from '../db/index.js';
import { uniqueSlug } from '../lib/slug.js';
import { sanitizeHtml, stripTags } from '../lib/html.js';
import { str, int, bool } from '../lib/validate.js';
import { notFound, badRequest } from '../lib/http.js';

function makeCrud(table, { fallbackSlug, extraFields = () => ({}) }) {
  const taken = (handle, excludeId = 0) =>
    Boolean(get(`SELECT id FROM ${table} WHERE handle = ? AND id != ?`, [handle, excludeId]));

  function normalize(input, existing = null) {
    const title = str(input.title ?? existing?.title, { max: 200 });
    if (!title) throw badRequest('Titel ist erforderlich', { fields: ['title'] });
    const body = sanitizeHtml(input.body_html ?? existing?.body_html ?? '');
    return {
      title,
      body_html: body,
      published: bool(input.published ?? existing?.published, false) ? 1 : 0,
      seo_title: str(input.seo_title ?? existing?.seo_title, { max: 200 }),
      seo_description:
        str(input.seo_description ?? existing?.seo_description, { max: 400 }) || stripTags(body, 160),
      updated_at: nowIso(),
      ...extraFields(input, existing, body),
    };
  }

  return {
    list({ published = null, search = '' } = {}) {
      const where = [];
      const params = [];
      if (published !== null) {
        where.push('published = ?');
        params.push(published ? 1 : 0);
      }
      if (search) {
        where.push('(title LIKE ? OR handle LIKE ?)');
        params.push(`%${search}%`, `%${search}%`);
      }
      const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
      return all(`SELECT * FROM ${table} ${clause} ORDER BY updated_at DESC`, params);
    },
    get: (id) => get(`SELECT * FROM ${table} WHERE id = ?`, [id]),
    getByHandle: (handle) => get(`SELECT * FROM ${table} WHERE handle = ?`, [handle]),
    create(input = {}) {
      const data = normalize(input);
      const handle = uniqueSlug(input.handle || data.title, (c) => taken(c), fallbackSlug);
      const id = insert(table, { ...data, handle, created_at: nowIso() });
      return get(`SELECT * FROM ${table} WHERE id = ?`, [id]);
    },
    update(id, input = {}) {
      const existing = get(`SELECT * FROM ${table} WHERE id = ?`, [id]);
      if (!existing) throw notFound('Eintrag nicht gefunden');
      const data = normalize(input, existing);
      if (input.handle !== undefined && str(input.handle) !== existing.handle) {
        data.handle = uniqueSlug(input.handle || data.title, (c) => taken(c, id), fallbackSlug);
      }
      update(table, id, data);
      return get(`SELECT * FROM ${table} WHERE id = ?`, [id]);
    },
    remove: (id) => remove(table, id),
  };
}

export const pages = makeCrud('pages', { fallbackSlug: 'seite' });

export const posts = makeCrud('posts', {
  fallbackSlug: 'beitrag',
  extraFields: (input, existing, body) => {
    const published = bool(input.published ?? existing?.published, false);
    return {
      excerpt: str(input.excerpt ?? existing?.excerpt, { max: 500 }) || stripTags(body, 200),
      image_url: str(input.image_url ?? existing?.image_url, { max: 500 }),
      author: str(input.author ?? existing?.author, { max: 120 }),
      tags: Array.isArray(input.tags)
        ? input.tags.map((t) => str(t, { max: 50 })).filter(Boolean).join(', ')
        : str(input.tags ?? existing?.tags, { max: 500 }),
      published_at: published ? existing?.published_at || nowIso() : null,
    };
  },
});

// --- Navigation -------------------------------------------------------------

export function listMenus() {
  return all('SELECT * FROM menus ORDER BY id').map((menu) => ({
    ...menu,
    items: menuTree(menu.id),
  }));
}

export function getMenuByHandle(handle) {
  const menu = get('SELECT * FROM menus WHERE handle = ?', [handle]);
  return menu ? { ...menu, items: menuTree(menu.id) } : null;
}

function menuTree(menuId) {
  const rows = all('SELECT * FROM menu_items WHERE menu_id = ? ORDER BY position, id', [menuId]);
  const byId = new Map(rows.map((r) => [r.id, { ...r, children: [] }]));
  const roots = [];
  for (const row of byId.values()) {
    if (row.parent_id && byId.has(row.parent_id)) byId.get(row.parent_id).children.push(row);
    else roots.push(row);
  }
  return roots;
}

export function ensureMenu(handle, title) {
  const existing = get('SELECT * FROM menus WHERE handle = ?', [handle]);
  if (existing) return existing.id;
  return insert('menus', { handle, title });
}

/** Ersetzt ein Menü komplett – der Admin schickt immer den ganzen Baum. */
export function setMenuItems(menuId, items) {
  return transaction(() => {
    run('DELETE FROM menu_items WHERE menu_id = ?', [menuId]);
    const insertLevel = (list, parentId) => {
      (Array.isArray(list) ? list : []).slice(0, 50).forEach((item, index) => {
        const label = str(item?.label, { max: 80 });
        if (!label) return;
        const id = insert('menu_items', {
          menu_id: menuId,
          parent_id: parentId,
          label,
          url: str(item?.url, { max: 300, fallback: '/' }) || '/',
          position: index,
        });
        if (parentId === null) insertLevel(item.children, id);
      });
    };
    insertLevel(items, null);
    return menuTree(menuId);
  });
}

// --- Medien -----------------------------------------------------------------

export const listMedia = ({ limit = 100, offset = 0 } = {}) =>
  all('SELECT * FROM media ORDER BY created_at DESC LIMIT ? OFFSET ?', [
    int(limit, { min: 1, max: 500, fallback: 100 }),
    int(offset, { min: 0, fallback: 0 }),
  ]);

export const createMedia = (data) =>
  insert('media', {
    filename: str(data.filename, { max: 255 }),
    url: str(data.url, { max: 500 }),
    mime: str(data.mime, { max: 100 }),
    size: int(data.size, { min: 0, fallback: 0 }),
    alt: str(data.alt, { max: 200 }),
    created_at: nowIso(),
  });

export const deleteMedia = (id) => remove('media', id);
