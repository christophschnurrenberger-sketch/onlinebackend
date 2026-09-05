/*
 * Statischer Export.
 *
 * Erzeugt aus dem veröffentlichten Snapshot einen Ordner mit fertigen
 * HTML-Dateien – hochladbar auf jeden Webspace, Netlify, Vercel oder GitHub
 * Pages. Damit ist die Storefront vom Backend entkoppelt: der Katalog liegt
 * statisch beim Hoster, nur Warenkorb und Kasse sprechen noch mit dem Backend.
 *
 * Der Export ist bewusst optional. Wer das Backend ohnehin öffentlich
 * betreibt, braucht ihn nicht – die Storefront wird dann direkt ausgeliefert.
 */

import { mkdir, writeFile, rm, cp } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import config from '../config.js';
import * as publish from './publish.js';
import * as layout from '../theme/layout.js';
import * as views from '../theme/views.js';
import { all } from '../db/index.js';

export async function exportSite({ baseUrl = '', outDir = '' } = {}) {
  const snapshot = publish.live();
  if (!snapshot) {
    throw new Error('Es ist noch nichts veröffentlicht – bitte zuerst veröffentlichen.');
  }

  const target = resolve(outDir || config.exportDir);
  const siteUrl = (baseUrl || config.baseUrl).replace(/\/+$/, '');
  const written = [];

  await rm(target, { recursive: true, force: true });
  await mkdir(target, { recursive: true });

  const write = async (relativePath, content) => {
    const full = join(target, relativePath);
    await mkdir(join(full, '..'), { recursive: true });
    await writeFile(full, content, 'utf8');
    written.push(relativePath);
  };

  // Der Export kennt keine Session, also auch keinen Warenkorb-Zähler; die
  // Storefront-Skripte holen den Stand nach dem Laden per API nach.
  const renderPage = (body, options) =>
    layout.page(snapshot, body, { ...options, cartCount: 0 });

  const stock = liveStock();
  const soldOut = soldOutSet(snapshot.products, stock);

  // Startseite
  await write(
    'index.html',
    renderPage(views.home(snapshot, { featured: snapshot.products.slice(0, 8), soldOut }), {
      canonical: `${siteUrl}/`,
    }),
  );

  // Kategorien
  await write('collections/index.html', renderPage(views.collectionIndex(snapshot), { title: 'Kategorien' }));

  const byId = new Map(snapshot.products.map((p) => [p.id, p]));
  for (const collection of snapshot.collections) {
    const products = collection.product_ids.map((id) => byId.get(id)).filter(Boolean);
    await write(
      `collections/${collection.handle}/index.html`,
      renderPage(
        views.collection(snapshot, {
          collection,
          products,
          soldOut,
          sort: collection.sort_order,
          page: 1,
          pageCount: 1,
        }),
        {
          title: collection.seo_title || collection.title,
          description: collection.seo_description,
          canonical: `${siteUrl}/collections/${collection.handle}`,
        },
      ),
    );
  }

  // Produkte
  for (const product of snapshot.products) {
    await write(
      `products/${product.handle}/index.html`,
      renderPage(
        views.product(snapshot, {
          product,
          stock,
          relatedProducts: snapshot.products.filter((p) => p.id !== product.id).slice(0, 4),
        }),
        {
          title: product.seo_title || product.title,
          description: product.seo_description,
          canonical: `${siteUrl}/products/${product.handle}`,
          image: product.images[0]?.url,
        },
      ),
    );
  }

  // Inhalte
  for (const page of snapshot.pages) {
    await write(
      `pages/${page.handle}/index.html`,
      renderPage(views.contentPage(snapshot, { page }), {
        title: page.seo_title || page.title,
        description: page.seo_description,
        canonical: `${siteUrl}/pages/${page.handle}`,
      }),
    );
  }

  await write('blog/index.html', renderPage(views.blogIndex(snapshot, { posts: snapshot.posts }), { title: 'Journal' }));
  for (const post of snapshot.posts) {
    await write(
      `blog/${post.handle}/index.html`,
      renderPage(views.blogPost(snapshot, { post }), {
        title: post.seo_title || post.title,
        description: post.seo_description,
        canonical: `${siteUrl}/blog/${post.handle}`,
      }),
    );
  }

  await write('404.html', renderPage(views.notFound(snapshot), { title: 'Nicht gefunden', noindex: true }));

  // Der Katalog als JSON: ein eigenes Frontend kann damit ohne HTML-Parsing
  // arbeiten, und die Suche im Export braucht keine Server-Anfrage.
  await write(
    'catalog.json',
    JSON.stringify(
      {
        version: publish.liveVersion(),
        generated_at: snapshot.generated_at,
        api_base: siteUrl,
        products: snapshot.products,
        collections: snapshot.collections,
      },
      null,
      2,
    ),
  );

  await write(
    'sitemap.xml',
    `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${[
  `${siteUrl}/`,
  `${siteUrl}/collections`,
  ...snapshot.collections.map((c) => `${siteUrl}/collections/${c.handle}`),
  ...snapshot.products.map((p) => `${siteUrl}/products/${p.handle}`),
  ...snapshot.pages.map((p) => `${siteUrl}/pages/${p.handle}`),
  ...snapshot.posts.map((p) => `${siteUrl}/blog/${p.handle}`),
]
  .map((loc) => `  <url><loc>${loc}</loc></url>`)
  .join('\n')}
</urlset>`,
  );

  await write('robots.txt', `User-agent: *\nAllow: /\nSitemap: ${siteUrl}/sitemap.xml\n`);

  // Theme und hochgeladene Bilder mitkopieren, sonst ist der Export nackt.
  const publicDir = resolve(config.uploadDir, '..');
  if (existsSync(join(publicDir, 'theme'))) {
    await cp(join(publicDir, 'theme'), join(target, 'theme'), { recursive: true });
  }
  if (existsSync(config.uploadDir)) {
    await cp(config.uploadDir, join(target, 'uploads'), { recursive: true });
  }

  return {
    directory: target,
    files: written.length,
    version: publish.liveVersion(),
    base_url: siteUrl,
    generated_at: new Date().toISOString(),
  };
}

/** Bestände zum Zeitpunkt des Exports; `null` heißt unbegrenzt. */
function liveStock() {
  const rows = all('SELECT id, inventory_quantity, track_inventory, inventory_policy FROM variants');
  const out = {};
  for (const row of rows) {
    out[row.id] =
      !row.track_inventory || row.inventory_policy === 'continue' ? null : row.inventory_quantity;
  }
  return out;
}

function soldOutSet(products, stock) {
  const soldOut = new Set();
  for (const product of products) {
    const available = product.variants.some((v) => stock[v.id] === null || stock[v.id] > 0);
    if (!available) soldOut.add(product.id);
  }
  return soldOut;
}
