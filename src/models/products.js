/*
 * Produkte und Varianten.
 *
 * Ein Produkt ist die Sache im Katalog, die Variante die kaufbare Einheit.
 * Preis und Bestand hängen immer an der Variante – auch bei Produkten ohne
 * Optionen, die dafür genau eine Default-Variante bekommen. Das erspart im
 * Warenkorb, im Checkout und in der Bestellung jede Sonderbehandlung.
 */

import { all, get, run, insert, update, remove, transaction, nowIso } from '../db/index.js';
import { uniqueSlug } from '../lib/slug.js';
import { sanitizeHtml, stripTags } from '../lib/html.js';
import { parseMoney } from '../lib/money.js';
import { str, int, bool, oneOf } from '../lib/validate.js';
import { notFound, badRequest } from '../lib/http.js';

export const STATUSES = ['draft', 'active', 'archived'];

const handleTaken = (handle, excludeId = 0) =>
  Boolean(get('SELECT id FROM products WHERE handle = ? AND id != ?', [handle, excludeId]));

// --- Lesen ------------------------------------------------------------------

export function listProducts({
  status = '',
  search = '',
  collectionId = 0,
  productType = '',
  vendor = '',
  tag = '',
  limit = 50,
  offset = 0,
  sort = 'updated-desc',
} = {}) {
  const where = [];
  const params = [];

  if (status) {
    where.push('p.status = ?');
    params.push(status);
  }
  if (search) {
    where.push('(p.title LIKE ? OR p.handle LIKE ? OR EXISTS (SELECT 1 FROM variants v WHERE v.product_id = p.id AND v.sku LIKE ?))');
    const like = `%${search}%`;
    params.push(like, like, like);
  }
  if (productType) {
    where.push('p.product_type = ?');
    params.push(productType);
  }
  if (vendor) {
    where.push('p.vendor = ?');
    params.push(vendor);
  }
  if (tag) {
    where.push("(',' || replace(p.tags, ', ', ',') || ',') LIKE ?");
    params.push(`%,${tag},%`);
  }
  if (collectionId) {
    where.push('EXISTS (SELECT 1 FROM collection_products cp WHERE cp.product_id = p.id AND cp.collection_id = ?)');
    params.push(collectionId);
  }

  const orderBy = {
    'updated-desc': 'p.updated_at DESC',
    'created-desc': 'p.created_at DESC',
    'title-asc': 'p.title COLLATE NOCASE ASC',
    'title-desc': 'p.title COLLATE NOCASE DESC',
    'price-asc': 'min_price ASC',
    'price-desc': 'min_price DESC',
    manual: 'p.position ASC, p.id ASC',
  }[sort] || 'p.updated_at DESC';

  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const rows = all(
    `SELECT p.*,
            (SELECT MIN(price) FROM variants v WHERE v.product_id = p.id) AS min_price,
            (SELECT MAX(price) FROM variants v WHERE v.product_id = p.id) AS max_price,
            (SELECT COUNT(*) FROM variants v WHERE v.product_id = p.id) AS variant_count,
            (SELECT SUM(inventory_quantity) FROM variants v WHERE v.product_id = p.id) AS inventory_total,
            (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.position, i.id LIMIT 1) AS image_url
       FROM products p
       ${clause}
      ORDER BY ${orderBy}
      LIMIT ? OFFSET ?`,
    [...params, int(limit, { min: 1, max: 250, fallback: 50 }), int(offset, { min: 0, fallback: 0 })],
  );

  const total = get(`SELECT COUNT(*) AS n FROM products p ${clause}`, params)?.n ?? 0;
  return { items: rows.map(decorateSummary), total };
}

function decorateSummary(row) {
  return {
    ...row,
    tags: splitTags(row.tags),
    min_price: row.min_price ?? 0,
    max_price: row.max_price ?? 0,
    inventory_total: row.inventory_total ?? 0,
  };
}

export const splitTags = (value) =>
  String(value || '')
    .split(',')
    .map((t) => t.trim())
    .filter(Boolean);

export function getProduct(id) {
  const product = get('SELECT * FROM products WHERE id = ?', [id]);
  return product ? hydrate(product) : null;
}

export function getProductByHandle(handle) {
  const product = get('SELECT * FROM products WHERE handle = ?', [handle]);
  return product ? hydrate(product) : null;
}

export function hydrate(product) {
  return {
    ...product,
    tags: splitTags(product.tags),
    images: all(
      'SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id',
      [product.id],
    ),
    options: all(
      'SELECT * FROM product_options WHERE product_id = ? ORDER BY position, id',
      [product.id],
    ).map((o) => ({ ...o, values: JSON.parse(o.values_json || '[]') })),
    variants: all(
      'SELECT * FROM variants WHERE product_id = ? ORDER BY position, id',
      [product.id],
    ),
    collections: all(
      `SELECT c.id, c.title, c.handle FROM collections c
         JOIN collection_products cp ON cp.collection_id = c.id
        WHERE cp.product_id = ? ORDER BY c.title`,
      [product.id],
    ),
  };
}

export function getVariant(id) {
  return get('SELECT * FROM variants WHERE id = ?', [id]);
}

/** Variante mit den Produktdaten, die Warenkorb und Bestellung brauchen. */
export function getVariantWithProduct(id) {
  return get(
    `SELECT v.*, p.title AS product_title, p.handle AS product_handle,
            p.status AS product_status,
            COALESCE(NULLIF(v.image_url, ''),
                     (SELECT url FROM product_images i WHERE i.product_id = p.id
                       ORDER BY i.position, i.id LIMIT 1), '') AS display_image
       FROM variants v JOIN products p ON p.id = v.product_id
      WHERE v.id = ?`,
    [id],
  );
}

export const listVendors = () =>
  all("SELECT DISTINCT vendor FROM products WHERE vendor != '' ORDER BY vendor").map((r) => r.vendor);

export const listProductTypes = () =>
  all("SELECT DISTINCT product_type FROM products WHERE product_type != '' ORDER BY product_type")
    .map((r) => r.product_type);

export function listTags() {
  const rows = all("SELECT tags FROM products WHERE tags != ''");
  const set = new Set();
  for (const row of rows) for (const tag of splitTags(row.tags)) set.add(tag);
  return [...set].sort((a, b) => a.localeCompare(b, 'de'));
}

// --- Schreiben --------------------------------------------------------------

function normalizeProductInput(input, { existing = null } = {}) {
  const title = str(input.title ?? existing?.title, { max: 200 });
  if (!title) throw badRequest('Titel ist erforderlich', { fields: ['title'] });

  const body = sanitizeHtml(input.body_html ?? existing?.body_html ?? '');
  const data = {
    title,
    subtitle: str(input.subtitle ?? existing?.subtitle, { max: 250 }),
    body_html: body,
    vendor: str(input.vendor ?? existing?.vendor, { max: 120 }),
    product_type: str(input.product_type ?? existing?.product_type, { max: 120 }),
    tags: Array.isArray(input.tags)
      ? input.tags.map((t) => str(t, { max: 50 })).filter(Boolean).join(', ')
      : str(input.tags ?? existing?.tags, { max: 800 }),
    status: oneOf(str(input.status ?? existing?.status, { fallback: 'draft' }), STATUSES, 'draft'),
    seo_title: str(input.seo_title ?? existing?.seo_title, { max: 200 }),
    seo_description:
      str(input.seo_description ?? existing?.seo_description, { max: 400 }) ||
      stripTags(body, 160),
    position: int(input.position ?? existing?.position, { min: 0, fallback: 0 }),
    updated_at: nowIso(),
  };

  // published_at markiert, seit wann ein Produkt aktiv ist – nützlich für
  // "Neuheiten"-Sortierungen und automatische Kollektionen.
  if (data.status === 'active' && !existing?.published_at) data.published_at = nowIso();
  if (data.status !== 'active' && existing?.published_at) data.published_at = null;

  return data;
}

export function createProduct(input = {}) {
  return transaction(() => {
    const data = normalizeProductInput(input);
    const handle = uniqueSlug(
      input.handle || data.title,
      (candidate) => handleTaken(candidate),
      'produkt',
    );
    const id = insert('products', { ...data, handle, created_at: nowIso() });

    replaceOptions(id, input.options || []);
    const variants = Array.isArray(input.variants) && input.variants.length > 0
      ? input.variants
      : [{ title: 'Standard', price: input.price ?? 0, sku: input.sku ?? '' }];
    replaceVariants(id, variants);
    replaceImages(id, input.images || []);

    return getProduct(id);
  });
}

export function updateProduct(id, input = {}) {
  return transaction(() => {
    const existing = get('SELECT * FROM products WHERE id = ?', [id]);
    if (!existing) throw notFound('Produkt nicht gefunden');

    const data = normalizeProductInput(input, { existing });
    if (input.handle !== undefined && str(input.handle) !== existing.handle) {
      data.handle = uniqueSlug(input.handle || data.title, (c) => handleTaken(c, id), 'produkt');
    }
    update('products', id, data);

    if (input.options !== undefined) replaceOptions(id, input.options);
    if (input.variants !== undefined) replaceVariants(id, input.variants);
    if (input.images !== undefined) replaceImages(id, input.images);

    return getProduct(id);
  });
}

export function deleteProduct(id) {
  return remove('products', id);
}

export function setStatus(id, status) {
  const next = oneOf(status, STATUSES, 'draft');
  const existing = get('SELECT published_at FROM products WHERE id = ?', [id]);
  if (!existing) throw notFound('Produkt nicht gefunden');
  return update('products', id, {
    status: next,
    published_at: next === 'active' ? existing.published_at || nowIso() : null,
    updated_at: nowIso(),
  });
}

/** Ein Duplikat landet immer als Entwurf – ein Klon darf nie live gehen. */
export function duplicateProduct(id) {
  const source = getProduct(id);
  if (!source) throw notFound('Produkt nicht gefunden');
  return createProduct({
    ...source,
    title: `${source.title} (Kopie)`,
    handle: `${source.handle}-kopie`,
    status: 'draft',
    variants: source.variants.map((v) => ({ ...v, id: undefined, sku: '' })),
    images: source.images.map((i) => ({ url: i.url, alt: i.alt })),
    options: source.options,
  });
}

// --- Unterobjekte -----------------------------------------------------------

function replaceOptions(productId, options) {
  run('DELETE FROM product_options WHERE product_id = ?', [productId]);
  options.slice(0, 3).forEach((option, index) => {
    const name = str(option?.name, { max: 60 });
    if (!name) return;
    const values = (Array.isArray(option.values) ? option.values : [])
      .map((v) => str(v, { max: 60 }))
      .filter(Boolean);
    insert('product_options', {
      product_id: productId,
      name,
      values_json: JSON.stringify(values),
      position: index + 1,
    });
  });
}

/**
 * Varianten werden abgeglichen, nicht gelöscht und neu angelegt: bestehende IDs
 * bleiben erhalten, damit Bestellpositionen und Bestandsbewegungen ihre
 * Referenz behalten.
 */
function replaceVariants(productId, variants) {
  const incoming = (Array.isArray(variants) ? variants : []).filter(Boolean);
  if (incoming.length === 0) return;

  const existingIds = all('SELECT id FROM variants WHERE product_id = ?', [productId])
    .map((r) => r.id);
  const keep = new Set();

  incoming.slice(0, 100).forEach((raw, index) => {
    const data = {
      product_id: productId,
      title: str(raw.title, { max: 120, fallback: 'Standard' }) || 'Standard',
      sku: str(raw.sku, { max: 80 }),
      barcode: str(raw.barcode, { max: 80 }),
      price: typeof raw.price === 'number' ? Math.max(0, Math.round(raw.price)) : parseMoney(raw.price),
      compare_at_price:
        raw.compare_at_price === null || raw.compare_at_price === '' || raw.compare_at_price === undefined
          ? null
          : typeof raw.compare_at_price === 'number'
            ? Math.max(0, Math.round(raw.compare_at_price))
            : parseMoney(raw.compare_at_price),
      cost:
        raw.cost === null || raw.cost === '' || raw.cost === undefined
          ? null
          : typeof raw.cost === 'number' ? Math.max(0, Math.round(raw.cost)) : parseMoney(raw.cost),
      option1: str(raw.option1, { max: 60 }),
      option2: str(raw.option2, { max: 60 }),
      option3: str(raw.option3, { max: 60 }),
      inventory_quantity: int(raw.inventory_quantity, { fallback: 0 }),
      track_inventory: bool(raw.track_inventory, true) ? 1 : 0,
      inventory_policy: oneOf(raw.inventory_policy, ['deny', 'continue'], 'deny'),
      weight_grams: int(raw.weight_grams, { min: 0, fallback: 0 }),
      requires_shipping: bool(raw.requires_shipping, true) ? 1 : 0,
      taxable: bool(raw.taxable, true) ? 1 : 0,
      tax_rate_id: raw.tax_rate_id ? int(raw.tax_rate_id) : null,
      image_url: str(raw.image_url, { max: 500 }),
      position: index,
      updated_at: nowIso(),
    };

    const id = int(raw.id, { fallback: 0 });
    if (id && existingIds.includes(id)) {
      update('variants', id, data);
      keep.add(id);
    } else {
      keep.add(insert('variants', { ...data, created_at: nowIso() }));
    }
  });

  for (const id of existingIds) {
    if (!keep.has(id)) remove('variants', id);
  }
}

function replaceImages(productId, images) {
  run('DELETE FROM product_images WHERE product_id = ?', [productId]);
  (Array.isArray(images) ? images : []).slice(0, 30).forEach((image, index) => {
    const url = str(typeof image === 'string' ? image : image?.url, { max: 500 });
    if (!url) return;
    insert('product_images', {
      product_id: productId,
      url,
      alt: str(image?.alt, { max: 200 }),
      position: index,
    });
  });
}

/** Kartesisches Produkt der Optionswerte – die Variantenmatrix im Admin. */
export function buildVariantMatrix(options, base = {}) {
  const axes = options
    .map((o) => (Array.isArray(o.values) ? o.values.filter(Boolean) : []))
    .filter((values) => values.length > 0)
    .slice(0, 3);
  if (axes.length === 0) return [{ ...base, title: 'Standard' }];

  let combos = [[]];
  for (const values of axes) {
    combos = combos.flatMap((combo) => values.map((value) => [...combo, value]));
  }
  return combos.slice(0, 100).map((combo) => ({
    ...base,
    title: combo.join(' / '),
    option1: combo[0] || '',
    option2: combo[1] || '',
    option3: combo[2] || '',
  }));
}
