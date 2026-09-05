/*
 * JSON-API der Storefront.
 *
 * Bedient das Frontend-JavaScript (Warenkorb ohne Seitenwechsel) und steht
 * zugleich einem entkoppelten Frontend zur Verfügung: wer die Storefront
 * später als eigene App oder als statischen Export betreibt, spricht genau
 * diese Endpunkte an.
 */

import { Router, readJson, sendJson, badRequest } from '../lib/http.js';
import * as cartService from '../services/cart.js';
import * as checkoutService from '../services/checkout.js';
import * as orders from '../models/orders.js';
import * as publish from '../services/publish.js';
import { availableProviders } from '../payments/index.js';
import { validateForCart } from '../models/discounts.js';
import { ratesFor } from '../models/shipping.js';

export function storefrontApiRouter() {
  const router = new Router();

  // --- Katalog (aus dem veröffentlichten Snapshot) --------------------------

  router.get('/catalog', (ctx) => {
    const snapshot = publish.live();
    if (!snapshot) return sendJson(ctx.res, 503, { error: 'Noch nicht veröffentlicht' });
    sendJson(ctx.res, 200, {
      version: publish.liveVersion(),
      generated_at: snapshot.generated_at,
      store: snapshot.store,
      theme: snapshot.theme,
      menus: snapshot.menus,
      collections: snapshot.collections,
      products: snapshot.products,
      pages: snapshot.pages.map((p) => ({ handle: p.handle, title: p.title })),
    });
  });

  router.get('/products/:handle', (ctx) => {
    const snapshot = publish.live();
    const product = snapshot?.products.find((p) => p.handle === ctx.params.handle);
    if (!product) return sendJson(ctx.res, 404, { error: 'Produkt nicht gefunden' });
    sendJson(ctx.res, 200, { product });
  });

  // --- Warenkorb ------------------------------------------------------------

  router.get('/cart', (ctx) => sendJson(ctx.res, 200, cartService.summarize(ctx.cart())));

  router.post('/cart/add', async (ctx) => {
    const body = await readJson(ctx.req);
    const summary = cartService.addLine(ctx.cart(), Number(body.variant_id), Number(body.quantity || 1));
    sendJson(ctx.res, 200, summary);
  });

  router.post('/cart/update', async (ctx) => {
    const body = await readJson(ctx.req);
    const summary = cartService.setLineQuantity(ctx.cart(), Number(body.variant_id), Number(body.quantity));
    sendJson(ctx.res, 200, summary);
  });

  router.post('/cart/remove', async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, cartService.removeLine(ctx.cart(), Number(body.variant_id)));
  });

  router.patch('/cart', async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, cartService.updateCart(ctx.cart(), body));
  });

  router.post('/cart/clear', (ctx) => sendJson(ctx.res, 200, cartService.clear(ctx.cart())));

  // --- Rabatt & Versand vorab prüfen ---------------------------------------

  router.post('/discount/check', async (ctx) => {
    const body = await readJson(ctx.req);
    const summary = cartService.summarize(ctx.cart());
    const result = validateForCart(body.code, {
      subtotal: summary.subtotal,
      email: summary.email,
    });
    sendJson(ctx.res, result.ok ? 200 : 422, {
      ok: result.ok,
      reason: result.reason || '',
      discount: result.ok ? { code: result.discount.code, type: result.discount.type } : null,
    });
  });

  router.get('/shipping/rates', (ctx) => {
    const summary = cartService.summarize(ctx.cart());
    const country = ctx.query.get('country') || summary.country;
    sendJson(ctx.res, 200, {
      country,
      rates: ratesFor(country, summary.subtotal - summary.discount_total, summary.requires_shipping),
    });
  });

  // --- Kasse ----------------------------------------------------------------

  router.get('/checkout', (ctx) => sendJson(ctx.res, 200, checkoutService.preview(ctx.cart())));

  router.get('/payment-providers', (ctx) => sendJson(ctx.res, 200, { providers: availableProviders() }));

  router.post('/checkout', async (ctx) => {
    const body = await readJson(ctx.req);
    const result = await checkoutService.begin(ctx.cart(), body);
    sendJson(ctx.res, 200, {
      order: {
        id: result.order.id,
        number: result.order.number,
        token: result.order.token,
        total: result.order.total,
        currency: result.order.currency,
        financial_status: result.order.financial_status,
      },
      action: result.action,
      redirect_url: result.redirect_url,
      status_url: result.status_url,
    });
  });

  router.get('/orders/:token', (ctx) => {
    const order = orders.getOrderByToken(ctx.params.token);
    if (!order) return sendJson(ctx.res, 404, { error: 'Bestellung nicht gefunden' });
    // Interne Notizen und Zahlungsrohdaten gehören nicht in die Kundenansicht.
    const { note, payments, events, ...visible } = order;
    sendJson(ctx.res, 200, { order: visible });
  });

  router.post('/orders/:token/confirm', async (ctx) => {
    const order = await checkoutService.complete(ctx.params.token);
    if (!order) throw badRequest('Bestellung nicht gefunden');
    sendJson(ctx.res, 200, {
      order: { number: order.number, financial_status: order.financial_status, status: order.status },
    });
  });

  return router;
}
