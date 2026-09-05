/*
 * Routen der Storefront.
 *
 * Alle Katalogdaten kommen aus dem veröffentlichten Snapshot; Bestände,
 * Warenkörbe und Bestellungen kommen live aus der Datenbank. Diese Trennung ist
 * die Regel, an der sich hier alles orientiert.
 */

import { Router, sendHtml, redirect, readForm, parseCookies, serializeCookie, setCookie } from '../lib/http.js';
import { all } from '../db/index.js';
import * as publish from '../services/publish.js';
import * as cartService from '../services/cart.js';
import * as checkoutService from '../services/checkout.js';
import * as orders from '../models/orders.js';
import * as layout from '../theme/layout.js';
import * as views from '../theme/views.js';
import { availableProviders } from '../payments/index.js';
import { getGroup } from '../models/settings.js';
import config from '../config.js';

const CART_COOKIE = 'shop_cart';
const PAGE_SIZE = 24;

export function storefrontRouter() {
  const router = new Router();

  router.get('/', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;
    const featured = snapshot.products.slice(0, 8);
    render(ctx, snapshot, views.home(snapshot, { featured, soldOut: soldOutSet(featured) }), {
      canonical: `${config.baseUrl}/`,
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: snapshot.store.name,
        url: config.baseUrl,
        email: snapshot.store.email,
      },
    });
  });

  router.get('/collections', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;
    render(ctx, snapshot, views.collectionIndex(snapshot), { title: 'Kategorien' });
  });

  router.get('/collections/:handle', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;

    const collection = snapshot.collections.find((c) => c.handle === ctx.params.handle);
    if (!collection) return render404(ctx, snapshot);

    const byId = new Map(snapshot.products.map((p) => [p.id, p]));
    let products = collection.product_ids.map((id) => byId.get(id)).filter(Boolean);

    const sort = ctx.query.get('sort') || collection.sort_order || 'manual';
    products = sortProducts(products, sort);

    const pageNumber = Math.max(1, Number(ctx.query.get('page') || 1));
    const pageCount = Math.max(1, Math.ceil(products.length / PAGE_SIZE));
    const visible = products.slice((pageNumber - 1) * PAGE_SIZE, pageNumber * PAGE_SIZE);

    render(
      ctx,
      snapshot,
      views.collection(snapshot, {
        collection,
        products: visible,
        soldOut: soldOutSet(visible),
        sort,
        page: pageNumber,
        pageCount,
      }),
      {
        title: collection.seo_title || collection.title,
        description: collection.seo_description,
        canonical: `${config.baseUrl}/collections/${collection.handle}`,
        image: collection.image_url,
      },
    );
  });

  router.get('/products/:handle', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;

    const product = snapshot.products.find((p) => p.handle === ctx.params.handle);
    if (!product) return render404(ctx, snapshot);

    const stock = stockFor(product.variants.map((v) => v.id));
    const related = snapshot.products
      .filter((p) => p.id !== product.id && (p.product_type === product.product_type || p.vendor === product.vendor))
      .slice(0, 4);

    render(ctx, snapshot, views.product(snapshot, { product, stock, relatedProducts: related }), {
      title: product.seo_title || product.title,
      description: product.seo_description,
      canonical: `${config.baseUrl}/products/${product.handle}`,
      image: product.images[0]?.url,
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'Product',
        name: product.title,
        description: product.seo_description,
        image: product.images.map((i) => i.url),
        brand: product.vendor || undefined,
        offers: product.variants.map((v) => ({
          '@type': 'Offer',
          sku: v.sku || undefined,
          price: (v.price / 100).toFixed(2),
          priceCurrency: snapshot.store.currency,
          availability:
            stock[v.id] === null || stock[v.id] > 0
              ? 'https://schema.org/InStock'
              : 'https://schema.org/OutOfStock',
          url: `${config.baseUrl}/products/${product.handle}`,
        })),
      },
    });
  });

  // --- Warenkorb ------------------------------------------------------------

  router.get('/cart', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;
    const summary = cartService.summarize(ctx.cart());
    render(ctx, snapshot, views.cart(snapshot, { cart: summary, message: ctx.query.get('msg') || '' }), {
      title: 'Warenkorb',
      noindex: true,
    });
  });

  router.post('/cart/add', async (ctx) => {
    const form = await readForm(ctx.req);
    try {
      cartService.addLine(ctx.cart(), Number(form.variant_id), Number(form.quantity || 1));
      redirect(ctx.res, '/cart');
    } catch (error) {
      redirect(ctx.res, `/cart?msg=${encodeURIComponent(error.message)}`);
    }
  });

  router.post('/cart/update', async (ctx) => {
    const form = await readForm(ctx.req);
    try {
      cartService.setLineQuantity(ctx.cart(), Number(form.variant_id), Number(form.quantity));
    } catch {
      /* Über die Bestandsgrenze hinaus wird einfach nicht erhöht. */
    }
    redirect(ctx.res, '/cart');
  });

  router.post('/cart/remove', async (ctx) => {
    const form = await readForm(ctx.req);
    cartService.removeLine(ctx.cart(), Number(form.variant_id));
    redirect(ctx.res, '/cart');
  });

  router.post('/cart/discount', async (ctx) => {
    const form = await readForm(ctx.req);
    cartService.updateCart(ctx.cart(), { discount_code: form.code || '' });
    redirect(ctx.res, '/cart');
  });

  // --- Kasse ----------------------------------------------------------------

  router.get('/checkout', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;

    const summary = cartService.summarize(ctx.cart());
    if (summary.lines.length === 0) return redirect(ctx.res, '/cart');

    render(
      ctx,
      snapshot,
      views.checkout(snapshot, {
        cart: summary,
        providers: availableProviders(),
        checkout: getGroup('checkout'),
        error: ctx.query.get('error') || '',
      }),
      { title: 'Kasse', noindex: true },
    );
  });

  router.post('/checkout', async (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;

    const form = await readForm(ctx.req);
    const cart = ctx.cart();

    if (form.shipping_rate_id) {
      cartService.updateCart(cart, { shipping_rate_id: form.shipping_rate_id });
    }

    try {
      const result = await checkoutService.begin(cartService.getCartByToken(cart.token), {
        email: form.email,
        phone: form.phone,
        payment_provider: form.payment_provider,
        accept_terms: form.accept_terms,
        accepts_marketing: form.accepts_marketing,
        note: form.note,
        shipping_address: {
          first_name: form.shipping_first_name,
          last_name: form.shipping_last_name,
          company: form.shipping_company,
          address1: form.shipping_address1,
          address2: form.shipping_address2,
          zip: form.shipping_zip,
          city: form.shipping_city,
          country: form.shipping_country,
          phone: form.phone,
        },
      });

      if (result.action === 'redirect' && result.redirect_url) {
        return redirect(ctx.res, result.redirect_url);
      }
      return redirect(ctx.res, `${result.status_url}?placed=1`);
    } catch (error) {
      // Eingaben gehen nicht verloren: das Formular wird mit den Werten und der
      // Fehlermeldung neu gerendert.
      const summary = cartService.summarize(cartService.getCartByToken(cart.token));
      return render(
        ctx,
        snapshot,
        views.checkout(snapshot, {
          cart: summary,
          providers: availableProviders(),
          checkout: getGroup('checkout'),
          error: error.message,
          values: form,
        }),
        { title: 'Kasse', noindex: true },
        error.status || 400,
      );
    }
  });

  router.get('/checkout/return/:token', async (ctx) => {
    await checkoutService.complete(ctx.params.token);
    redirect(ctx.res, `/order/${ctx.params.token}?placed=1`);
  });

  router.get('/checkout/cancel/:token', (ctx) => {
    const order = orders.getOrderByToken(ctx.params.token);
    if (order && order.financial_status !== 'paid') {
      checkoutService.fail(order.id, 'Zahlung vom Kunden abgebrochen');
    }
    redirect(ctx.res, '/cart?msg=Zahlung%20abgebrochen');
  });

  router.get('/order/:token', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;

    const order = orders.getOrderByToken(ctx.params.token);
    if (!order) return render404(ctx, snapshot);

    render(ctx, snapshot, views.orderStatus(snapshot, { order, justPlaced: ctx.query.has('placed') }), {
      title: `Bestellung ${order.number}`,
      noindex: true,
    });
  });

  // --- Inhalte --------------------------------------------------------------

  router.get('/pages/:handle', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;
    const page = snapshot.pages.find((p) => p.handle === ctx.params.handle);
    if (!page) return render404(ctx, snapshot);

    render(ctx, snapshot, views.contentPage(snapshot, { page }), {
      title: page.seo_title || page.title,
      description: page.seo_description,
      canonical: `${config.baseUrl}/pages/${page.handle}`,
    });
  });

  router.get('/blog', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;
    render(ctx, snapshot, views.blogIndex(snapshot, { posts: snapshot.posts }), { title: 'Journal' });
  });

  router.get('/blog/:handle', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;
    const post = snapshot.posts.find((p) => p.handle === ctx.params.handle);
    if (!post) return render404(ctx, snapshot);

    render(ctx, snapshot, views.blogPost(snapshot, { post }), {
      title: post.seo_title || post.title,
      description: post.seo_description,
      canonical: `${config.baseUrl}/blog/${post.handle}`,
      image: post.image_url,
    });
  });

  router.get('/search', (ctx) => {
    const snapshot = requireSnapshot(ctx);
    if (!snapshot) return;

    const query = (ctx.query.get('q') || '').trim().slice(0, 100);
    const products = query ? searchProducts(snapshot, query) : [];
    render(ctx, snapshot, views.search(snapshot, { query, products, soldOut: soldOutSet(products) }), {
      title: query ? `Suche: ${query}` : 'Suche',
      noindex: true,
    });
  });

  // --- Maschinenlesbares ----------------------------------------------------

  router.get('/sitemap.xml', (ctx) => {
    const snapshot = publish.live();
    if (!snapshot) return sendHtml(ctx.res, 404, 'Nicht veröffentlicht');

    const urls = [
      { loc: `${config.baseUrl}/`, priority: '1.0' },
      { loc: `${config.baseUrl}/collections`, priority: '0.7' },
      ...snapshot.collections.map((c) => ({ loc: `${config.baseUrl}/collections/${c.handle}`, priority: '0.8' })),
      ...snapshot.products.map((p) => ({ loc: `${config.baseUrl}/products/${p.handle}`, priority: '0.9' })),
      ...snapshot.pages.map((p) => ({ loc: `${config.baseUrl}/pages/${p.handle}`, priority: '0.5' })),
      ...snapshot.posts.map((p) => ({ loc: `${config.baseUrl}/blog/${p.handle}`, priority: '0.6' })),
    ];

    const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.map((u) => `  <url><loc>${u.loc}</loc><priority>${u.priority}</priority></url>`).join('\n')}
</urlset>`;
    ctx.res.writeHead(200, { 'Content-Type': 'application/xml; charset=utf-8' });
    ctx.res.end(xml);
  });

  // Browser fragen /favicon.ico immer an. Ohne eigene Datei liefern wir das im
  // Backend hinterlegte Icon aus – und sonst eine leere Antwort statt einer 404.
  router.get('/favicon.ico', (ctx) => {
    const favicon = getGroup('store').favicon_url;
    if (favicon && favicon !== '/favicon.ico') return redirect(ctx.res, favicon, 302);
    ctx.res.writeHead(204).end();
  });

  router.get('/robots.txt', (ctx) => {
    ctx.res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
    ctx.res.end(`User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /order\nSitemap: ${config.baseUrl}/sitemap.xml\n`);
  });

  return router;
}

// --- Hilfsfunktionen --------------------------------------------------------

function requireSnapshot(ctx) {
  // Mit ?preview=1 sieht ein angemeldeter Betreiber den unveröffentlichten Stand.
  if (ctx.query.has('preview') && ctx.user) {
    ctx.preview = true;
    return publish.buildSnapshot();
  }
  const snapshot = publish.live();
  if (!snapshot) {
    sendHtml(ctx.res, 503, views.notPublished(getGroup('store')));
    return null;
  }
  return snapshot;
}

function render(ctx, snapshot, body, options = {}, status = 200) {
  const summary = ctx.cartSummary();
  sendHtml(
    ctx.res,
    status,
    layout.page(snapshot, body, { ...options, cartCount: summary.item_count, preview: ctx.preview }),
  );
}

function render404(ctx, snapshot) {
  render(ctx, snapshot, views.notFound(snapshot), { title: 'Nicht gefunden', noindex: true }, 404);
}

/** Bestände zu Varianten-IDs; `null` heißt unbegrenzt verfügbar. */
function stockFor(variantIds) {
  if (variantIds.length === 0) return {};
  const rows = all(
    `SELECT id, inventory_quantity, track_inventory, inventory_policy
       FROM variants WHERE id IN (${variantIds.map(() => '?').join(',')})`,
    variantIds,
  );
  const out = {};
  for (const row of rows) {
    out[row.id] =
      !row.track_inventory || row.inventory_policy === 'continue' ? null : row.inventory_quantity;
  }
  return out;
}

/** IDs der Produkte, deren Varianten alle ausverkauft sind. */
function soldOutSet(products) {
  const ids = products.flatMap((p) => p.variants.map((v) => v.id));
  const stock = stockFor(ids);
  const soldOut = new Set();
  for (const product of products) {
    const available = product.variants.some((v) => stock[v.id] === null || stock[v.id] > 0);
    if (!available) soldOut.add(product.id);
  }
  return soldOut;
}

function sortProducts(products, sort) {
  const copy = [...products];
  switch (sort) {
    case 'title-asc':
      return copy.sort((a, b) => a.title.localeCompare(b.title, 'de'));
    case 'title-desc':
      return copy.sort((a, b) => b.title.localeCompare(a.title, 'de'));
    case 'price-asc':
      return copy.sort((a, b) => a.min_price - b.min_price);
    case 'price-desc':
      return copy.sort((a, b) => b.min_price - a.min_price);
    case 'created-desc':
      return copy.sort((a, b) => String(b.published_at || '').localeCompare(String(a.published_at || '')));
    default:
      return copy;
  }
}

/** Einfache Volltextsuche über den Snapshot – ohne Index, aber sofort korrekt. */
function searchProducts(snapshot, query) {
  const needle = query.toLowerCase();
  return snapshot.products
    .map((product) => {
      const haystack = [product.title, product.vendor, product.product_type, product.tags.join(' ')]
        .join(' ')
        .toLowerCase();
      if (!haystack.includes(needle)) {
        // SKU-Suche als Zweitchance: Kunden mit Katalog suchen nach Artikelnummer.
        if (!product.variants.some((v) => v.sku.toLowerCase().includes(needle))) return null;
      }
      // Treffer im Titel wiegen schwerer als Treffer in Tags.
      const score = product.title.toLowerCase().includes(needle) ? 2 : 1;
      return { product, score };
    })
    .filter(Boolean)
    .sort((a, b) => b.score - a.score)
    .slice(0, 60)
    .map((entry) => entry.product);
}

/** Warenkorb-Kontext für eine Anfrage: Cookie lesen, notfalls neu anlegen. */
export function attachCart(ctx) {
  let cached = null;

  ctx.cart = () => {
    if (cached) return cached;
    const cookies = parseCookies(ctx.req);
    cached = cartService.getCartByToken(cookies[CART_COOKIE]);
    if (!cached) {
      cached = cartService.createCart();
      setCookie(
        ctx.res,
        serializeCookie(CART_COOKIE, cached.token, {
          maxAge: 60 * 60 * 24 * 30,
          secure: config.secureCookies,
          sameSite: 'Lax',
        }),
      );
    }
    return cached;
  };

  // Nur lesend: legt keinen Warenkorb an, damit jeder Bot-Aufruf der Startseite
  // keine Zeile in der Datenbank erzeugt.
  ctx.cartSummary = () => {
    const cookies = parseCookies(ctx.req);
    const existing = cookies[CART_COOKIE] ? cartService.getCartByToken(cookies[CART_COOKIE]) : null;
    if (!existing) return { item_count: 0, lines: [], total: 0 };
    return cartService.summarize(existing);
  };
}
