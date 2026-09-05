/*
 * Kollektionen (Kategorien).
 *
 * Zwei Sorten, wie bei den großen Systemen:
 *   manuell – der Betreiber legt die Produkte und ihre Reihenfolge selbst fest
 *   automatisch – Regeln (Tag, Typ, Preis, Hersteller) bestimmen die Mitglieder
 * Automatische Kollektionen werden beim Lesen ausgewertet, nicht materialisiert:
 * so ist ein neu angelegtes Produkt sofort in der richtigen Kategorie.
 */

import { all, get, run, insert, update, remove, transaction, parseJson, nowIso } from '../db/index.js';
import { uniqueSlug } from '../lib/slug.js';
import { sanitizeHtml, stripTags } from '../lib/html.js';
import { parseMoney } from '../lib/money.js';
import { str, int, bool, oneOf } from '../lib/validate.js';
import { notFound, badRequest } from '../lib/http.js';

export const RULE_FIELDS = ['tag', 'product_type', 'vendor', 'title', 'price'];
export const RULE_OPERATORS = ['equals', 'not_equals', 'contains', 'starts_with', 'greater_than', 'less_than'];
export const SORT_ORDERS = ['manual', 'title-asc', 'title-desc', 'price-asc', 'price-desc', 'created-desc'];

const handleTaken = (handle, excludeId = 0) =>
  Boolean(get('SELECT id FROM collections WHERE handle = ? AND id != ?', [handle, excludeId]));

export function listCollections({ published = null, search = '' } = {}) {
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
  return all(
    `SELECT * FROM collections ${clause} ORDER BY position ASC, title COLLATE NOCASE ASC`,
    params,
  ).map(decorate);
}

function decorate(row) {
  return {
    ...row,
    rules: parseJson(row.rules_json, []),
    product_count: countProducts(row),
  };
}

export const getCollection = (id) => {
  const row = get('SELECT * FROM collections WHERE id = ?', [id]);
  return row ? decorate(row) : null;
};

export const getCollectionByHandle = (handle) => {
  const row = get('SELECT * FROM collections WHERE handle = ?', [handle]);
  return row ? decorate(row) : null;
};

function normalize(input, existing = null) {
  const title = str(input.title ?? existing?.title, { max: 200 });
  if (!title) throw badRequest('Titel ist erforderlich', { fields: ['title'] });

  const body = sanitizeHtml(input.body_html ?? existing?.body_html ?? '');
  return {
    title,
    body_html: body,
    image_url: str(input.image_url ?? existing?.image_url, { max: 500 }),
    rule_type: oneOf(input.rule_type ?? existing?.rule_type, ['manual', 'auto'], 'manual'),
    rules_json: JSON.stringify(normalizeRules(input.rules ?? parseJson(existing?.rules_json, []))),
    rules_match: oneOf(input.rules_match ?? existing?.rules_match, ['all', 'any'], 'all'),
    sort_order: oneOf(input.sort_order ?? existing?.sort_order, SORT_ORDERS, 'manual'),
    published: bool(input.published ?? existing?.published, false) ? 1 : 0,
    position: int(input.position ?? existing?.position, { min: 0, fallback: 0 }),
    seo_title: str(input.seo_title ?? existing?.seo_title, { max: 200 }),
    seo_description:
      str(input.seo_description ?? existing?.seo_description, { max: 400 }) || stripTags(body, 160),
    updated_at: nowIso(),
  };
}

function normalizeRules(rules) {
  return (Array.isArray(rules) ? rules : [])
    .slice(0, 10)
    .map((rule) => ({
      field: oneOf(rule?.field, RULE_FIELDS, 'tag'),
      operator: oneOf(rule?.operator, RULE_OPERATORS, 'equals'),
      value: str(rule?.value, { max: 120 }),
    }))
    .filter((rule) => rule.value !== '');
}

export function createCollection(input = {}) {
  return transaction(() => {
    const data = normalize(input);
    const handle = uniqueSlug(input.handle || data.title, (c) => handleTaken(c), 'kategorie');
    const id = insert('collections', { ...data, handle, created_at: nowIso() });
    if (Array.isArray(input.product_ids)) setProducts(id, input.product_ids);
    return getCollection(id);
  });
}

export function updateCollection(id, input = {}) {
  return transaction(() => {
    const existing = get('SELECT * FROM collections WHERE id = ?', [id]);
    if (!existing) throw notFound('Kategorie nicht gefunden');

    const data = normalize(input, existing);
    if (input.handle !== undefined && str(input.handle) !== existing.handle) {
      data.handle = uniqueSlug(input.handle || data.title, (c) => handleTaken(c, id), 'kategorie');
    }
    update('collections', id, data);
    if (Array.isArray(input.product_ids)) setProducts(id, input.product_ids);
    return getCollection(id);
  });
}

export const deleteCollection = (id) => remove('collections', id);

export function setProducts(collectionId, productIds) {
  run('DELETE FROM collection_products WHERE collection_id = ?', [collectionId]);
  productIds.slice(0, 2000).forEach((productId, index) => {
    const id = int(productId, { fallback: 0 });
    if (!id) return;
    run(
      `INSERT OR IGNORE INTO collection_products (collection_id, product_id, position)
       VALUES (?, ?, ?)`,
      [collectionId, id, index],
    );
  });
}

export function addProduct(collectionId, productId) {
  const next = (get(
    'SELECT MAX(position) AS p FROM collection_products WHERE collection_id = ?',
    [collectionId],
  )?.p ?? -1) + 1;
  run(
    'INSERT OR IGNORE INTO collection_products (collection_id, product_id, position) VALUES (?, ?, ?)',
    [collectionId, productId, next],
  );
}

export const removeProduct = (collectionId, productId) =>
  run('DELETE FROM collection_products WHERE collection_id = ? AND product_id = ?', [
    collectionId,
    productId,
  ]).changes;

// --- Regelauswertung --------------------------------------------------------

/** Baut aus einer Regel ein SQL-Fragment gegen die products-Tabelle. */
function ruleSql(rule) {
  const { field, operator, value } = rule;

  if (field === 'price') {
    const cents = parseMoney(value);
    const column = '(SELECT MIN(price) FROM variants v WHERE v.product_id = p.id)';
    const op = { greater_than: '>', less_than: '<', equals: '=', not_equals: '!=' }[operator] || '=';
    return { sql: `${column} ${op} ?`, params: [cents] };
  }

  const column = { tag: 'p.tags', product_type: 'p.product_type', vendor: 'p.vendor', title: 'p.title' }[field];
  switch (operator) {
    case 'not_equals':
      return { sql: `${column} != ?`, params: [value] };
    case 'contains':
      return { sql: `${column} LIKE ?`, params: [`%${value}%`] };
    case 'starts_with':
      return { sql: `${column} LIKE ?`, params: [`${value}%`] };
    case 'equals':
    default:
      // Tags sind eine kommagetrennte Liste: "equals" meint "enthält das Tag".
      if (field === 'tag') {
        return { sql: `(',' || replace(p.tags, ', ', ',') || ',') LIKE ?`, params: [`%,${value},%`] };
      }
      return { sql: `${column} = ?`, params: [value] };
  }
}

const ORDER_SQL = {
  manual: 'cp.position ASC, p.id ASC',
  'title-asc': 'p.title COLLATE NOCASE ASC',
  'title-desc': 'p.title COLLATE NOCASE DESC',
  'price-asc': '(SELECT MIN(price) FROM variants v WHERE v.product_id = p.id) ASC',
  'price-desc': '(SELECT MIN(price) FROM variants v WHERE v.product_id = p.id) DESC',
  'created-desc': 'p.created_at DESC',
};

/**
 * Produkte einer Kollektion. `onlyActive` ist für die Storefront gedacht –
 * im Admin will man auch die Entwürfe sehen.
 */
export function collectionProducts(collection, { onlyActive = true, limit = 500, offset = 0 } = {}) {
  const params = [];
  const where = [];
  if (onlyActive) where.push("p.status = 'active'");

  let join = '';
  let orderBy = ORDER_SQL[collection.sort_order] || ORDER_SQL['title-asc'];

  if (collection.rule_type === 'auto') {
    const rules = collection.rules?.length ? collection.rules : parseJson(collection.rules_json, []);
    const fragments = rules.map(ruleSql);
    if (fragments.length > 0) {
      const glue = collection.rules_match === 'any' ? ' OR ' : ' AND ';
      where.push(`(${fragments.map((f) => f.sql).join(glue)})`);
      params.push(...fragments.flatMap((f) => f.params));
    } else {
      // Automatische Kollektion ohne Regeln ist absichtlich leer, nicht "alles".
      return [];
    }
    if (orderBy === ORDER_SQL.manual) orderBy = ORDER_SQL['title-asc'];
  } else {
    join = 'JOIN collection_products cp ON cp.product_id = p.id AND cp.collection_id = ?';
    params.unshift(collection.id);
  }

  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  return all(
    `SELECT p.*,
            (SELECT MIN(price) FROM variants v WHERE v.product_id = p.id) AS min_price,
            (SELECT MAX(price) FROM variants v WHERE v.product_id = p.id) AS max_price,
            (SELECT MAX(COALESCE(compare_at_price,0)) FROM variants v WHERE v.product_id = p.id) AS max_compare_at,
            (SELECT SUM(inventory_quantity) FROM variants v WHERE v.product_id = p.id) AS inventory_total,
            (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.position, i.id LIMIT 1) AS image_url
       FROM products p ${join} ${clause}
      ORDER BY ${orderBy}
      LIMIT ? OFFSET ?`,
    [...params, int(limit, { min: 1, max: 1000, fallback: 500 }), int(offset, { min: 0, fallback: 0 })],
  );
}

function countProducts(row) {
  const collection = { ...row, rules: parseJson(row.rules_json, []) };
  if (collection.rule_type === 'manual') {
    return get('SELECT COUNT(*) AS n FROM collection_products WHERE collection_id = ?', [row.id])?.n ?? 0;
  }
  return collectionProducts(collection, { onlyActive: false, limit: 1000 }).length;
}
