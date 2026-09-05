/*
 * Admin-API.
 *
 * Alle Endpunkte hinter /api/admin verlangen eine gültige Session; schreibende
 * zusätzlich das CSRF-Token. Die Prüfung passiert zentral in `guard`, damit
 * kein neuer Endpunkt sie versehentlich auslassen kann.
 */

import { Router, sendJson, readJson, unauthorized, forbidden, badRequest, notFound } from '../lib/http.js';
import { all, get, run, transaction, nowIso } from '../db/index.js';
import * as auth from '../lib/auth.js';
import * as productModel from '../models/products.js';
import * as collectionModel from '../models/collections.js';
import * as contentModel from '../models/content.js';
import * as customerModel from '../models/customers.js';
import * as orderModel from '../models/orders.js';
import * as discountModel from '../models/discounts.js';
import * as shippingModel from '../models/shipping.js';
import * as settingsModel from '../models/settings.js';
import * as inventory from '../services/inventory.js';
import * as publish from '../services/publish.js';
import * as checkoutService from '../services/checkout.js';
import { exportSite } from '../services/export.js';
import { availableProviders, allProviders } from '../payments/index.js';
import { int, str, bool } from '../lib/validate.js';

/** Nur diese Rollen dürfen Einstellungen und Benutzer ändern. */
const ADMIN_ROLES = ['owner', 'admin'];

export function adminApiRouter() {
  const router = new Router();
  const q = (ctx, key, fallback = '') => ctx.query.get(key) ?? fallback;

  // --- Anmeldung ------------------------------------------------------------

  router.post('/login', async (ctx) => {
    const body = await readJson(ctx.req);
    const user = auth.authenticate(body.email, body.password);
    if (!user) return sendJson(ctx.res, 401, { error: 'E-Mail oder Passwort ist falsch' });

    const session = auth.createSession(user.id, ctx.req.headers['user-agent'] || '');
    ctx.setSessionCookie(session);
    sendJson(ctx.res, 200, { user, csrf_token: auth.csrfToken(session.token) });
  });

  router.post('/logout', (ctx) => {
    auth.destroySession(ctx.sessionToken);
    ctx.clearSessionCookie();
    sendJson(ctx.res, 200, { ok: true });
  });

  router.get('/me', (ctx) => {
    if (!ctx.user) return sendJson(ctx.res, 401, { error: 'Nicht angemeldet' });
    sendJson(ctx.res, 200, {
      user: ctx.user,
      csrf_token: auth.csrfToken(ctx.sessionToken),
      store: settingsModel.getGroup('store'),
      publishing: settingsModel.getGroup('publishing'),
    });
  });

  // --- Übersicht ------------------------------------------------------------

  router.get('/dashboard', guard((ctx) => {
    const days = int(q(ctx, 'days', '30'), { min: 1, max: 365, fallback: 30 });
    sendJson(ctx.res, 200, {
      stats: orderModel.stats({ days }),
      low_stock: inventory.lowStock(5, 10),
      recent_orders: orderModel.listOrders({ limit: 8 }).items,
      counts: {
        products: get('SELECT COUNT(*) AS n FROM products')?.n ?? 0,
        active_products: get("SELECT COUNT(*) AS n FROM products WHERE status = 'active'")?.n ?? 0,
        draft_products: get("SELECT COUNT(*) AS n FROM products WHERE status = 'draft'")?.n ?? 0,
        collections: get('SELECT COUNT(*) AS n FROM collections')?.n ?? 0,
        customers: get('SELECT COUNT(*) AS n FROM customers')?.n ?? 0,
      },
      pending_changes: publish.pendingChanges(),
    });
  }));

  // --- Produkte -------------------------------------------------------------

  router.get('/products', guard((ctx) => {
    sendJson(ctx.res, 200, productModel.listProducts({
      status: q(ctx, 'status'),
      search: q(ctx, 'search'),
      productType: q(ctx, 'type'),
      vendor: q(ctx, 'vendor'),
      tag: q(ctx, 'tag'),
      collectionId: int(q(ctx, 'collection_id', '0')),
      sort: q(ctx, 'sort', 'updated-desc'),
      limit: int(q(ctx, 'limit', '50'), { min: 1, max: 250, fallback: 50 }),
      offset: int(q(ctx, 'offset', '0'), { min: 0, fallback: 0 }),
    }));
  }));

  router.get('/products/meta', guard((ctx) => {
    sendJson(ctx.res, 200, {
      vendors: productModel.listVendors(),
      types: productModel.listProductTypes(),
      tags: productModel.listTags(),
      tax_rates: shippingModel.listTaxRates(),
      collections: collectionModel.listCollections().map((c) => ({ id: c.id, title: c.title, rule_type: c.rule_type })),
    });
  }));

  router.get('/products/:id', guard((ctx) => {
    const product = productModel.getProduct(int(ctx.params.id));
    if (!product) throw notFound('Produkt nicht gefunden');
    sendJson(ctx.res, 200, {
      product,
      inventory_moves: product.variants.flatMap((v) => inventory.movesForVariant(v.id, 10)),
    });
  }));

  router.post('/products', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 201, { product: productModel.createProduct(body) });
  }, { write: true }));

  router.put('/products/:id', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, { product: productModel.updateProduct(int(ctx.params.id), body) });
  }, { write: true }));

  router.post('/products/:id/duplicate', guard((ctx) => {
    sendJson(ctx.res, 201, { product: productModel.duplicateProduct(int(ctx.params.id)) });
  }, { write: true }));

  router.post('/products/:id/status', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    productModel.setStatus(int(ctx.params.id), body.status);
    sendJson(ctx.res, 200, { product: productModel.getProduct(int(ctx.params.id)) });
  }, { write: true }));

  router.delete('/products/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: productModel.deleteProduct(int(ctx.params.id)) });
  }, { write: true }));

  /** Massenaktion aus der Produktliste (Status setzen, löschen, taggen). */
  router.post('/products/bulk', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    const ids = (Array.isArray(body.ids) ? body.ids : []).map((id) => int(id)).filter(Boolean);
    if (ids.length === 0) throw badRequest('Keine Produkte ausgewählt');

    const result = transaction(() => {
      let affected = 0;
      for (const id of ids) {
        switch (body.action) {
          case 'status':
            productModel.setStatus(id, body.status);
            affected += 1;
            break;
          case 'delete':
            affected += productModel.deleteProduct(id);
            break;
          case 'add_tag': {
            const product = productModel.getProduct(id);
            if (!product) break;
            const tags = new Set([...product.tags, str(body.tag, { max: 50 })].filter(Boolean));
            productModel.updateProduct(id, { tags: [...tags] });
            affected += 1;
            break;
          }
          case 'add_to_collection':
            collectionModel.addProduct(int(body.collection_id), id);
            affected += 1;
            break;
          default:
            throw badRequest(`Unbekannte Aktion: ${body.action}`);
        }
      }
      return affected;
    });
    sendJson(ctx.res, 200, { affected: result });
  }, { write: true }));

  // --- Bestand --------------------------------------------------------------

  router.get('/inventory', guard((ctx) => {
    const search = q(ctx, 'search');
    const where = search ? 'AND (p.title LIKE ? OR v.sku LIKE ?)' : '';
    const params = search ? [`%${search}%`, `%${search}%`] : [];
    sendJson(ctx.res, 200, {
      items: all(
        `SELECT v.id, v.sku, v.title AS variant_title, v.inventory_quantity, v.track_inventory,
                v.inventory_policy, v.price, p.id AS product_id, p.title AS product_title, p.status
           FROM variants v JOIN products p ON p.id = v.product_id
          WHERE p.status != 'archived' ${where}
          ORDER BY p.title, v.position LIMIT 300`,
        params,
      ),
    });
  }));

  router.post('/inventory/:variantId', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    const variantId = int(ctx.params.variantId);
    const quantity = body.set !== undefined
      ? inventory.setQuantity(variantId, int(body.set), { userId: ctx.user.id })
      : inventory.adjust(variantId, int(body.delta), { reason: body.reason || 'correction', userId: ctx.user.id });
    if (quantity === null) throw notFound('Variante nicht gefunden');
    sendJson(ctx.res, 200, { inventory_quantity: quantity });
  }, { write: true }));

  router.get('/inventory/:variantId/moves', guard((ctx) => {
    sendJson(ctx.res, 200, { moves: inventory.movesForVariant(int(ctx.params.variantId), 100) });
  }));

  // --- Kategorien -----------------------------------------------------------

  router.get('/collections', guard((ctx) => {
    sendJson(ctx.res, 200, { items: collectionModel.listCollections({ search: q(ctx, 'search') }) });
  }));

  router.get('/collections/:id', guard((ctx) => {
    const collection = collectionModel.getCollection(int(ctx.params.id));
    if (!collection) throw notFound('Kategorie nicht gefunden');
    sendJson(ctx.res, 200, {
      collection,
      products: collectionModel.collectionProducts(collection, { onlyActive: false }),
    });
  }));

  router.post('/collections', guard(async (ctx) => {
    sendJson(ctx.res, 201, { collection: collectionModel.createCollection(await readJson(ctx.req)) });
  }, { write: true }));

  router.put('/collections/:id', guard(async (ctx) => {
    sendJson(ctx.res, 200, {
      collection: collectionModel.updateCollection(int(ctx.params.id), await readJson(ctx.req)),
    });
  }, { write: true }));

  router.delete('/collections/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: collectionModel.deleteCollection(int(ctx.params.id)) });
  }, { write: true }));

  // --- Bestellungen ---------------------------------------------------------

  router.get('/orders', guard((ctx) => {
    sendJson(ctx.res, 200, orderModel.listOrders({
      search: q(ctx, 'search'),
      status: q(ctx, 'status'),
      financialStatus: q(ctx, 'financial_status'),
      fulfillmentStatus: q(ctx, 'fulfillment_status'),
      customerId: int(q(ctx, 'customer_id', '0')),
      from: q(ctx, 'from'),
      to: q(ctx, 'to'),
      sort: q(ctx, 'sort', 'created-desc'),
      limit: int(q(ctx, 'limit', '50'), { min: 1, max: 250, fallback: 50 }),
      offset: int(q(ctx, 'offset', '0'), { min: 0, fallback: 0 }),
    }));
  }));

  router.get('/orders/:id', guard((ctx) => {
    const order = orderModel.getOrder(int(ctx.params.id));
    if (!order) throw notFound('Bestellung nicht gefunden');
    sendJson(ctx.res, 200, { order });
  }));

  router.post('/orders/:id/paid', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, {
      order: orderModel.markPaid(int(ctx.params.id), {
        reference: str(body.reference, { max: 200 }),
        userId: ctx.user.id,
      }),
    });
  }, { write: true }));

  router.post('/orders/:id/fulfill', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, {
      order: orderModel.fulfill(int(ctx.params.id), {
        lines: body.lines,
        carrier: body.carrier,
        trackingNumber: body.tracking_number,
        trackingUrl: body.tracking_url,
        userId: ctx.user.id,
      }),
    });
  }, { write: true }));

  router.post('/orders/:id/refund', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    const order = await checkoutService.refundPayment(int(ctx.params.id), int(body.amount), {
      reason: str(body.reason, { max: 500 }),
      userId: ctx.user.id,
    });
    // Retoure: Ware zurück in den Bestand, wenn der Betreiber das will.
    if (bool(body.restock, false)) {
      const full = orderModel.getOrder(int(ctx.params.id));
      inventory.releaseForOrder(full.lines, { orderId: full.id, userId: ctx.user.id, reason: 'refund' });
    }
    sendJson(ctx.res, 200, { order });
  }, { write: true }));

  router.post('/orders/:id/cancel', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    const order = orderModel.getOrder(int(ctx.params.id));
    if (!order) throw notFound('Bestellung nicht gefunden');
    if (bool(body.restock, true)) {
      inventory.releaseForOrder(order.lines, { orderId: order.id, userId: ctx.user.id, reason: 'cancel' });
    }
    sendJson(ctx.res, 200, {
      order: orderModel.cancel(order.id, { reason: str(body.reason, { max: 500 }), userId: ctx.user.id }),
    });
  }, { write: true }));

  router.post('/orders/:id/archive', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, {
      order: orderModel.archive(int(ctx.params.id), bool(body.archived, true), ctx.user.id),
    });
  }, { write: true }));

  router.post('/orders/:id/note', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, { order: orderModel.setNote(int(ctx.params.id), body.note, ctx.user.id) });
  }, { write: true }));

  // --- Kunden ---------------------------------------------------------------

  router.get('/customers', guard((ctx) => {
    sendJson(ctx.res, 200, customerModel.listCustomers({
      search: q(ctx, 'search'),
      sort: q(ctx, 'sort', 'created-desc'),
      limit: int(q(ctx, 'limit', '50'), { min: 1, max: 250, fallback: 50 }),
      offset: int(q(ctx, 'offset', '0'), { min: 0, fallback: 0 }),
    }));
  }));

  router.get('/customers/:id', guard((ctx) => {
    const customer = customerModel.getCustomer(int(ctx.params.id));
    if (!customer) throw notFound('Kunde nicht gefunden');
    sendJson(ctx.res, 200, { customer });
  }));

  router.post('/customers', guard(async (ctx) => {
    sendJson(ctx.res, 201, { customer: customerModel.createCustomer(await readJson(ctx.req)) });
  }, { write: true }));

  router.put('/customers/:id', guard(async (ctx) => {
    sendJson(ctx.res, 200, {
      customer: customerModel.updateCustomer(int(ctx.params.id), await readJson(ctx.req)),
    });
  }, { write: true }));

  router.delete('/customers/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: customerModel.deleteCustomer(int(ctx.params.id)) });
  }, { write: true }));

  // --- Rabatte --------------------------------------------------------------

  router.get('/discounts', guard((ctx) => {
    sendJson(ctx.res, 200, { items: discountModel.listDiscounts({ search: q(ctx, 'search') }) });
  }));

  router.post('/discounts', guard(async (ctx) => {
    sendJson(ctx.res, 201, { discount: discountModel.createDiscount(await readJson(ctx.req)) });
  }, { write: true }));

  router.put('/discounts/:id', guard(async (ctx) => {
    sendJson(ctx.res, 200, {
      discount: discountModel.updateDiscount(int(ctx.params.id), await readJson(ctx.req)),
    });
  }, { write: true }));

  router.delete('/discounts/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: discountModel.deleteDiscount(int(ctx.params.id)) });
  }, { write: true }));

  // --- Inhalte --------------------------------------------------------------

  for (const [path, model] of [['pages', contentModel.pages], ['posts', contentModel.posts]]) {
    router.get(`/${path}`, guard((ctx) => {
      sendJson(ctx.res, 200, { items: model.list({ search: q(ctx, 'search') }) });
    }));
    router.get(`/${path}/:id`, guard((ctx) => {
      const item = model.get(int(ctx.params.id));
      if (!item) throw notFound('Eintrag nicht gefunden');
      sendJson(ctx.res, 200, { item });
    }));
    router.post(`/${path}`, guard(async (ctx) => {
      sendJson(ctx.res, 201, { item: model.create(await readJson(ctx.req)) });
    }, { write: true }));
    router.put(`/${path}/:id`, guard(async (ctx) => {
      sendJson(ctx.res, 200, { item: model.update(int(ctx.params.id), await readJson(ctx.req)) });
    }, { write: true }));
    router.delete(`/${path}/:id`, guard((ctx) => {
      sendJson(ctx.res, 200, { deleted: model.remove(int(ctx.params.id)) });
    }, { write: true }));
  }

  router.get('/menus', guard((ctx) => sendJson(ctx.res, 200, { items: contentModel.listMenus() })));

  router.put('/menus/:handle', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    const menuId = contentModel.ensureMenu(ctx.params.handle, body.title || ctx.params.handle);
    sendJson(ctx.res, 200, { items: contentModel.setMenuItems(menuId, body.items || []) });
  }, { write: true }));

  router.get('/media', guard((ctx) => sendJson(ctx.res, 200, { items: contentModel.listMedia() })));

  router.delete('/media/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: contentModel.deleteMedia(int(ctx.params.id)) });
  }, { write: true }));

  // --- Einstellungen --------------------------------------------------------

  router.get('/settings', guard((ctx) => {
    sendJson(ctx.res, 200, {
      settings: settingsModel.getAll(),
      shipping_zones: shippingModel.listZones(),
      tax_rates: shippingModel.listTaxRates(),
      payment_providers: {
        available: availableProviders(),
        all: allProviders().map((p) => ({ id: p.id, label: p.label, configured: p.available() })),
      },
    });
  }));

  router.put('/settings/:group', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, { settings: settingsModel.setGroup(ctx.params.group, body) });
  }, { write: true, roles: ADMIN_ROLES }));

  router.post('/shipping/zones', guard(async (ctx) => {
    sendJson(ctx.res, 201, { id: shippingModel.createZone(await readJson(ctx.req)) });
  }, { write: true, roles: ADMIN_ROLES }));

  router.put('/shipping/zones/:id', guard(async (ctx) => {
    shippingModel.updateZone(int(ctx.params.id), await readJson(ctx.req));
    sendJson(ctx.res, 200, { zones: shippingModel.listZones() });
  }, { write: true, roles: ADMIN_ROLES }));

  router.delete('/shipping/zones/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: shippingModel.deleteZone(int(ctx.params.id)) });
  }, { write: true, roles: ADMIN_ROLES }));

  router.post('/tax-rates', guard(async (ctx) => {
    sendJson(ctx.res, 201, { id: shippingModel.createTaxRate(await readJson(ctx.req)) });
  }, { write: true, roles: ADMIN_ROLES }));

  router.put('/tax-rates/:id', guard(async (ctx) => {
    shippingModel.updateTaxRate(int(ctx.params.id), await readJson(ctx.req));
    sendJson(ctx.res, 200, { tax_rates: shippingModel.listTaxRates() });
  }, { write: true, roles: ADMIN_ROLES }));

  router.delete('/tax-rates/:id', guard((ctx) => {
    sendJson(ctx.res, 200, { deleted: shippingModel.deleteTaxRate(int(ctx.params.id)) });
  }, { write: true, roles: ADMIN_ROLES }));

  // --- Benutzer -------------------------------------------------------------

  router.get('/users', guard((ctx) => sendJson(ctx.res, 200, { items: auth.listUsers() }), { roles: ADMIN_ROLES }));

  router.post('/users', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    if (!body.email || !body.password || String(body.password).length < 8) {
      throw badRequest('E-Mail und ein Passwort mit mindestens 8 Zeichen sind erforderlich');
    }
    if (get('SELECT id FROM users WHERE email = ?', [String(body.email).toLowerCase()])) {
      throw badRequest('Diese E-Mail-Adresse ist bereits vergeben');
    }
    sendJson(ctx.res, 201, { id: auth.createUser(body) });
  }, { write: true, roles: ADMIN_ROLES }));

  router.post('/users/:id/password', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    const id = int(ctx.params.id);
    // Das eigene Passwort darf jeder ändern, fremde nur Owner und Admins.
    if (id !== ctx.user.id && !ADMIN_ROLES.includes(ctx.user.role)) throw forbidden();
    if (String(body.password || '').length < 8) throw badRequest('Mindestens 8 Zeichen');
    run('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [
      auth.hashPassword(body.password),
      nowIso(),
      id,
    ]);
    sendJson(ctx.res, 200, { ok: true });
  }, { write: true }));

  router.delete('/users/:id', guard((ctx) => {
    const id = int(ctx.params.id);
    if (id === ctx.user.id) throw badRequest('Der eigene Zugang kann nicht gelöscht werden');
    const remaining = get("SELECT COUNT(*) AS n FROM users WHERE active = 1 AND id != ?", [id])?.n ?? 0;
    if (remaining === 0) throw badRequest('Der letzte Zugang kann nicht gelöscht werden');
    sendJson(ctx.res, 200, { deleted: run('DELETE FROM users WHERE id = ?', [id]).changes });
  }, { write: true, roles: ADMIN_ROLES }));

  // --- Veröffentlichen ------------------------------------------------------

  router.get('/publish', guard((ctx) => {
    sendJson(ctx.res, 200, {
      pending: publish.pendingChanges(),
      versions: publish.listVersions(20),
      live_version: publish.liveVersion(),
    });
  }));

  router.post('/publish', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, { publication: publish.publish({ note: body.note, userId: ctx.user.id }) });
  }, { write: true }));

  router.post('/publish/rollback', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, { publication: publish.rollback(int(body.version)) });
  }, { write: true, roles: ADMIN_ROLES }));

  router.post('/publish/export', guard(async (ctx) => {
    const body = await readJson(ctx.req);
    sendJson(ctx.res, 200, await exportSite({ baseUrl: body.base_url }));
  }, { write: true, roles: ADMIN_ROLES }));

  return router;
}

/**
 * Legt Authentifizierung, Rolle und CSRF-Schutz um einen Handler.
 * Schreibende Endpunkte verlangen zusätzlich das Session-CSRF-Token.
 */
function guard(handler, { write = false, roles = null } = {}) {
  return async (ctx) => {
    if (!ctx.user) throw unauthorized();
    if (roles && !roles.includes(ctx.user.role)) {
      throw forbidden('Für diese Aktion fehlt die Berechtigung');
    }
    if (write && !auth.checkCsrf(ctx.sessionToken, ctx.req.headers['x-csrf-token'])) {
      throw forbidden('CSRF-Token fehlt oder ist ungültig');
    }
    return handler(ctx);
  };
}
