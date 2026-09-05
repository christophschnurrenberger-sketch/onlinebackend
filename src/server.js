/*
 * HTTP-Server.
 *
 * Ein Prozess bedient drei Dinge:
 *   /admin        das Backend (Single-Page-Anwendung)
 *   /api/admin    dessen API
 *   alles andere  die Storefront samt /api (öffentlich) und /webhooks
 *
 * Ein Prozess statt drei, weil sich Backend und Shop dieselbe Datenbank teilen
 * und ein einzelner Node-Prozess für einen eigenen Shop völlig ausreicht.
 * Skaliert werden müsste hier nur der Storefront-Teil – und der liest aus dem
 * Snapshot im Speicher.
 */

import { createServer } from 'node:http';
import { resolve } from 'node:path';
import { randomUUID } from 'node:crypto';
import config, { ROOT } from './config.js';
import { getDb, migrate } from './db/index.js';
import {
  Router, HttpError, sendJson, sendHtml, sendFile, parseCookies,
  serializeCookie, setCookie, redirect,
} from './lib/http.js';
import * as auth from './lib/auth.js';
import { adminApiRouter } from './routes/admin-api.js';
import { storefrontApiRouter } from './routes/storefront-api.js';
import { storefrontRouter, attachCart } from './routes/storefront.js';
import { webhookRouter } from './routes/webhooks.js';
import { uploadRouter } from './routes/uploads.js';
import { purgeStaleCarts } from './services/cart.js';
import * as publish from './services/publish.js';

const PUBLIC_DIR = resolve(ROOT, 'public');
const ADMIN_DIR = resolve(ROOT, 'admin');

export function createApp() {
  const router = new Router();
  router.mount('/api/admin', adminApiRouter());
  router.mount('/api', storefrontApiRouter());
  router.mount('/webhooks', webhookRouter());
  router.mount('/admin/upload', uploadRouter());
  router.mount('', storefrontRouter());
  return router;
}

export function createShopServer() {
  migrate(getDb());
  const router = createApp();

  return createServer(async (req, res) => {
    const started = Date.now();
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const pathname = decodeURIComponent(url.pathname);

    // Sicherheits-Header für alle Antworten. Der Shop lädt keine fremden
    // Skripte, deshalb ist eine strenge CSP hier ohne Reibung möglich.
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-Frame-Options', 'SAMEORIGIN');
    res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    res.setHeader(
      'Content-Security-Policy',
      "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; " +
        "script-src 'self'; frame-ancestors 'self'; form-action 'self' https://checkout.stripe.com https://www.paypal.com https://www.sandbox.paypal.com; base-uri 'self'",
    );

    try {
      // --- Statische Dateien -------------------------------------------------
      if (pathname.startsWith('/theme/') || pathname.startsWith('/uploads/')) {
        if (sendFile(res, PUBLIC_DIR, pathname.slice(1))) return;
      }
      if (pathname.startsWith('/admin/assets/')) {
        if (sendFile(res, ADMIN_DIR, pathname.replace('/admin/assets/', ''))) return;
      }

      // --- Anfragekontext ----------------------------------------------------
      const cookies = parseCookies(req);
      const sessionToken = cookies[auth.SESSION_COOKIE] || '';
      const ctx = {
        req,
        res,
        url,
        query: url.searchParams,
        params: {},
        sessionToken,
        user: auth.findSessionUser(sessionToken),
        preview: false,
        setSessionCookie(session) {
          setCookie(
            res,
            serializeCookie(auth.SESSION_COOKIE, session.token, {
              expires: session.expiresAt,
              secure: config.secureCookies,
              sameSite: 'Lax',
            }),
          );
        },
        clearSessionCookie() {
          setCookie(res, serializeCookie(auth.SESSION_COOKIE, '', { maxAge: 0 }));
        },
      };
      attachCart(ctx);

      // --- Backend-Oberfläche ------------------------------------------------
      // Die SPA wird für jeden /admin-Pfad ausgeliefert; das Routing macht der
      // Browser. Die Daten dahinter sind trotzdem geschützt – die API prüft.
      if (pathname === '/admin' || pathname.startsWith('/admin/')) {
        if (!pathname.startsWith('/admin/upload') && !pathname.startsWith('/admin/assets')) {
          if (sendFile(res, ADMIN_DIR, 'index.html')) return;
          return sendHtml(res, 500, 'Backend-Oberfläche fehlt (admin/index.html).');
        }
      }

      // --- Routing -----------------------------------------------------------
      const match = router.match(req.method, pathname);
      if (!match) {
        if (pathname.startsWith('/api')) return sendJson(res, 404, { error: 'Endpunkt nicht gefunden' });
        return notFoundPage(ctx);
      }

      ctx.params = match.params;
      await match.handler(ctx);
    } catch (error) {
      handleError(req, res, error, pathname);
    } finally {
      if (config.env !== 'test') {
        const duration = Date.now() - started;
        if (duration > 500) {
          console.warn(`[langsam] ${req.method} ${pathname} – ${duration} ms`);
        }
      }
    }
  });
}

function notFoundPage(ctx) {
  const snapshot = publish.live();
  if (!snapshot) return sendHtml(ctx.res, 404, 'Nicht gefunden');
  // Verzögert geladen, damit der Server ohne Theme-Modul startbar bleibt.
  return import('./theme/layout.js').then(async (layout) => {
    const views = await import('./theme/views.js');
    sendHtml(
      ctx.res,
      404,
      layout.page(snapshot, views.notFound(snapshot), {
        title: 'Nicht gefunden',
        noindex: true,
        cartCount: ctx.cartSummary().item_count,
      }),
    );
  });
}

function handleError(req, res, error, pathname) {
  const status = error instanceof HttpError ? error.status : 500;

  if (status >= 500) {
    // Interne Fehler bekommen eine ID: der Betreiber findet sie im Log wieder,
    // der Kunde sieht keine Interna.
    const errorId = randomUUID().slice(0, 8);
    console.error(`[${errorId}] ${req.method} ${pathname}:`, error);
    if (res.headersSent) return res.end();
    return pathname.startsWith('/api')
      ? sendJson(res, 500, { error: 'Interner Serverfehler', error_id: errorId })
      : sendHtml(res, 500, `Interner Serverfehler (Referenz ${errorId})`);
  }

  if (res.headersSent) return res.end();
  if (pathname.startsWith('/api')) {
    return sendJson(res, status, { error: error.message, details: error.details ?? undefined });
  }
  return sendHtml(res, status, `<!doctype html><meta charset="utf-8"><p>${error.message}</p>`);
}

// --- Start ------------------------------------------------------------------

const isMain = process.argv[1] && import.meta.url === `file://${process.argv[1]}`;

if (isMain) {
  const server = createShopServer();

  server.listen(config.port, config.host, () => {
    console.log(`Shop:    ${config.baseUrl}`);
    console.log(`Backend: ${config.baseUrl}/admin`);
    if (!publish.live()) {
      console.log('Hinweis: Noch nichts veröffentlicht – im Backend auf „Veröffentlichen“ klicken.');
    }
    if (config.isProduction && !config.sessionSecretProvided) {
      console.warn('WARNUNG: SESSION_SECRET ist nicht gesetzt – alle Anmeldungen enden beim nächsten Neustart.');
    }
  });

  // Aufräumen: abgelaufene Sessions und vergessene Warenkörbe.
  const cleanup = setInterval(() => {
    try {
      auth.purgeExpiredSessions();
      purgeStaleCarts(30);
    } catch (error) {
      console.error('Aufräumen fehlgeschlagen:', error.message);
    }
  }, 6 * 3600_000);
  cleanup.unref();

  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
      console.log('\nServer wird beendet …');
      server.close(() => process.exit(0));
      setTimeout(() => process.exit(0), 3000).unref();
    });
  }
}
