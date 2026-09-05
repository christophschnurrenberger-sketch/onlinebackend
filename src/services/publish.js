/*
 * Veröffentlichen.
 *
 * Das ist der "Push ins Frontend": Ein Klick friert den kompletten
 * veröffentlichungsfähigen Zustand als JSON-Snapshot ein. Die Storefront liest
 * ausschließlich aus dem aktuell live geschalteten Snapshot.
 *
 * Warum ein Snapshot und keine Live-Abfrage auf die Arbeitstabellen?
 *   * Entwürfe können nie versehentlich im Shop auftauchen – man kann einen
 *     Artikel in Ruhe über Tage bearbeiten, ohne dass Kunden Halbfertiges sehen.
 *   * Veröffentlichen wird zum Ereignis mit Datum, Version und Urheber.
 *   * Ein Rollback ist ein einziges UPDATE, kein Rückwärts-Migrieren von Daten.
 *   * Die Storefront liest aus dem Arbeitsspeicher – schnell und ohne dass ein
 *     langsamer Katalog-Query die Kasse ausbremst.
 *
 * Preise, Bestände und Bestellungen laufen NICHT über den Snapshot: die müssen
 * sofort wirken. Der Snapshot ist der Katalog, nicht der Kassenzustand.
 */

import { all, get, run, insert, parseJson, transaction, nowIso } from '../db/index.js';
import * as productModel from '../models/products.js';
import * as collectionModel from '../models/collections.js';
import * as contentModel from '../models/content.js';
import * as settingsModel from '../models/settings.js';
import { stripTags } from '../lib/html.js';
import { notFound } from '../lib/http.js';

// Der aktive Snapshot liegt im Speicher; die DB ist nur die Ablage. Nach einem
// Neustart wird beim ersten Zugriff nachgeladen.
let cached = null;

/** Baut den Snapshot aus dem aktuellen Backend-Zustand – ohne ihn zu speichern. */
export function buildSnapshot() {
  const settings = settingsModel.getAll();

  const products = productModel
    .listProducts({ status: 'active', limit: 250, sort: 'manual' })
    .items.map((summary) => {
      const full = productModel.getProduct(summary.id);
      return {
        id: full.id,
        handle: full.handle,
        title: full.title,
        subtitle: full.subtitle,
        body_html: full.body_html,
        vendor: full.vendor,
        product_type: full.product_type,
        tags: full.tags,
        seo_title: full.seo_title,
        seo_description: full.seo_description || stripTags(full.body_html, 160),
        published_at: full.published_at,
        images: full.images.map((i) => ({ url: i.url, alt: i.alt })),
        options: full.options.map((o) => ({ name: o.name, values: o.values })),
        variants: full.variants.map((v) => ({
          id: v.id,
          title: v.title,
          sku: v.sku,
          price: v.price,
          compare_at_price: v.compare_at_price,
          option1: v.option1,
          option2: v.option2,
          option3: v.option3,
          image_url: v.image_url,
          requires_shipping: v.requires_shipping,
          // Bestände wandern bewusst NICHT in den Snapshot – die Verfügbarkeit
          // liest die Storefront direkt aus der Datenbank.
        })),
        min_price: full.variants.length ? Math.min(...full.variants.map((v) => v.price)) : 0,
        max_price: full.variants.length ? Math.max(...full.variants.map((v) => v.price)) : 0,
        max_compare_at: full.variants.reduce((max, v) => Math.max(max, v.compare_at_price || 0), 0),
      };
    });

  const productIds = new Set(products.map((p) => p.id));

  const collections = collectionModel.listCollections({ published: true }).map((collection) => ({
    id: collection.id,
    handle: collection.handle,
    title: collection.title,
    body_html: collection.body_html,
    image_url: collection.image_url,
    sort_order: collection.sort_order,
    seo_title: collection.seo_title,
    seo_description: collection.seo_description,
    // Die Regeln werden beim Veröffentlichen ausgewertet und als feste Liste
    // abgelegt: die Storefront muss nie Regeln interpretieren, und der Betreiber
    // sieht genau das im Shop, was er beim Veröffentlichen in der Vorschau hatte.
    product_ids: collectionModel
      .collectionProducts(collection, { onlyActive: true, limit: 1000 })
      .map((p) => p.id)
      .filter((id) => productIds.has(id)),
  }));

  return {
    generated_at: nowIso(),
    store: settings.store,
    theme: settings.theme,
    checkout: settings.checkout,
    legal: settings.legal,
    products,
    collections,
    pages: contentModel.pages.list({ published: true }).map(pickContent),
    posts: contentModel.posts
      .list({ published: true })
      .map((post) => ({ ...pickContent(post), excerpt: post.excerpt, image_url: post.image_url, author: post.author, published_at: post.published_at })),
    menus: Object.fromEntries(
      contentModel.listMenus().map((menu) => [menu.handle, menu.items.map(pickMenuItem)]),
    ),
  };
}

const pickContent = (row) => ({
  id: row.id,
  handle: row.handle,
  title: row.title,
  body_html: row.body_html,
  seo_title: row.seo_title,
  seo_description: row.seo_description,
  updated_at: row.updated_at,
});

const pickMenuItem = (item) => ({
  label: item.label,
  url: item.url,
  children: (item.children || []).map(pickMenuItem),
});

/** Veröffentlicht den aktuellen Stand als neue Version und schaltet sie live. */
export function publish({ note = '', userId = null } = {}) {
  return transaction(() => {
    const snapshot = buildSnapshot();
    const stats = {
      products: snapshot.products.length,
      collections: snapshot.collections.length,
      pages: snapshot.pages.length,
      posts: snapshot.posts.length,
    };
    const version = (get('SELECT MAX(version) AS v FROM publications')?.v || 0) + 1;

    run('UPDATE publications SET live = 0 WHERE live = 1');
    const id = insert('publications', {
      version,
      note: String(note || '').slice(0, 500),
      snapshot_json: JSON.stringify(snapshot),
      stats_json: JSON.stringify(stats),
      live: 1,
      user_id: userId,
      created_at: nowIso(),
    });

    settingsModel.setGroup('publishing', {
      last_published_at: snapshot.generated_at,
      last_version: version,
    });

    cached = { id, version, snapshot };
    return { id, version, stats, created_at: snapshot.generated_at };
  });
}

/** Der aktuell live geschaltete Snapshot. */
export function live() {
  if (cached) return cached.snapshot;

  const row = get('SELECT * FROM publications WHERE live = 1 ORDER BY version DESC LIMIT 1');
  if (!row) return null;

  cached = { id: row.id, version: row.version, snapshot: parseJson(row.snapshot_json, null) };
  return cached.snapshot;
}

export const liveVersion = () => {
  live();
  return cached?.version || 0;
};

/** Für Vorschau und Storefront: live, sonst der ungespeicherte Arbeitsstand. */
export function current({ preview = false } = {}) {
  if (preview) return buildSnapshot();
  return live();
}

export function listVersions(limit = 30) {
  return all(
    `SELECT p.id, p.version, p.note, p.stats_json, p.live, p.created_at, u.name AS user_name
       FROM publications p LEFT JOIN users u ON u.id = p.user_id
      ORDER BY p.version DESC LIMIT ?`,
    [limit],
  ).map((row) => ({ ...row, stats: parseJson(row.stats_json, {}) }));
}

/** Rollback: eine frühere Version wieder live schalten. */
export function rollback(version) {
  return transaction(() => {
    const row = get('SELECT * FROM publications WHERE version = ?', [version]);
    if (!row) throw notFound('Diese Version existiert nicht');

    run('UPDATE publications SET live = 0 WHERE live = 1');
    run('UPDATE publications SET live = 1 WHERE id = ?', [row.id]);
    settingsModel.setGroup('publishing', { last_version: row.version });

    cached = { id: row.id, version: row.version, snapshot: parseJson(row.snapshot_json, null) };
    return { version: row.version, created_at: row.created_at };
  });
}

/**
 * Was hat sich seit der letzten Veröffentlichung geändert? Das Backend zeigt
 * das vor dem Klick an, damit niemand blind veröffentlicht.
 */
export function pendingChanges() {
  const snapshot = live();
  const draft = buildSnapshot();
  if (!snapshot) {
    return {
      never_published: true,
      changes: [{ type: 'initial', label: 'Der Shop wurde noch nie veröffentlicht.' }],
      count: draft.products.length + draft.collections.length + draft.pages.length,
    };
  }

  const changes = [];
  const compare = (kind, label, before, after) => {
    const beforeMap = new Map(before.map((item) => [item.id, item]));
    const afterMap = new Map(after.map((item) => [item.id, item]));

    for (const [id, item] of afterMap) {
      if (!beforeMap.has(id)) changes.push({ type: 'added', kind, label, title: item.title });
      else if (JSON.stringify(beforeMap.get(id)) !== JSON.stringify(item)) {
        changes.push({ type: 'changed', kind, label, title: item.title });
      }
    }
    for (const [id, item] of beforeMap) {
      if (!afterMap.has(id)) changes.push({ type: 'removed', kind, label, title: item.title });
    }
  };

  compare('product', 'Produkt', snapshot.products, draft.products);
  compare('collection', 'Kategorie', snapshot.collections, draft.collections);
  compare('page', 'Seite', snapshot.pages, draft.pages);
  compare('post', 'Beitrag', snapshot.posts, draft.posts);

  if (JSON.stringify(snapshot.theme) !== JSON.stringify(draft.theme)) {
    changes.push({ type: 'changed', kind: 'theme', label: 'Design', title: 'Theme-Einstellungen' });
  }
  if (JSON.stringify(snapshot.store) !== JSON.stringify(draft.store)) {
    changes.push({ type: 'changed', kind: 'store', label: 'Shop', title: 'Stammdaten' });
  }
  if (JSON.stringify(snapshot.menus) !== JSON.stringify(draft.menus)) {
    changes.push({ type: 'changed', kind: 'menu', label: 'Navigation', title: 'Menüs' });
  }

  return { never_published: false, changes, count: changes.length };
}

/** Nach Datenänderungen im laufenden Betrieb (Import, Rollback aus Tests). */
export const invalidate = () => {
  cached = null;
};
