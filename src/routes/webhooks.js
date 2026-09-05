/*
 * Webhooks der Zahlungsanbieter.
 *
 * Der Kunde kann den Browser nach dem Bezahlen schließen – dann ist der
 * Webhook der einzige Weg, von dem Geldeingang zu erfahren. Deshalb ist er
 * kein Extra, sondern der verlässliche Pfad; die Rückkehr im Browser ist nur
 * der schnelle.
 *
 * Zwei Regeln gelten für jeden Anbieter:
 *   1. Signatur prüfen, bevor irgendetwas gebucht wird.
 *   2. Jede event_id nur einmal verarbeiten (Anbieter liefern doppelt aus).
 */

import { Router, readBody, sendJson } from '../lib/http.js';
import { get, insert, update, nowIso } from '../db/index.js';
import * as checkoutService from '../services/checkout.js';
import stripe from '../payments/stripe.js';
import paypal from '../payments/paypal.js';

export function webhookRouter() {
  const router = new Router();

  router.post('/stripe', async (ctx) => {
    const raw = await readBody(ctx.req);
    const verification = stripe.verifyWebhook(raw, ctx.req.headers['stripe-signature']);
    if (!verification.ok) {
      return sendJson(ctx.res, 400, { error: `Webhook abgelehnt: ${verification.reason}` });
    }

    let event;
    try {
      event = JSON.parse(raw.toString('utf8'));
    } catch {
      return sendJson(ctx.res, 400, { error: 'Ungültiges JSON' });
    }

    handle('stripe', event.id, event.type, event, () => checkoutService.applyPaymentEvent(stripe.parseEvent(event)));
    sendJson(ctx.res, 200, { received: true });
  });

  router.post('/paypal', async (ctx) => {
    const raw = await readBody(ctx.req);
    const verification = await paypal.verifyWebhook(raw, ctx.req.headers);
    if (!verification.ok) {
      return sendJson(ctx.res, 400, { error: `Webhook abgelehnt: ${verification.reason}` });
    }

    let event;
    try {
      event = JSON.parse(raw.toString('utf8'));
    } catch {
      return sendJson(ctx.res, 400, { error: 'Ungültiges JSON' });
    }

    handle('paypal', event.id, event.event_type, event, () =>
      checkoutService.applyPaymentEvent(paypal.parseEvent(event)),
    );
    sendJson(ctx.res, 200, { received: true });
  });

  return router;
}

/**
 * Speichert das Ereignis und verarbeitet es genau einmal. Der Eintrag entsteht
 * vor der Verarbeitung: schlägt sie fehl, ist das Ereignis trotzdem
 * dokumentiert und lässt sich im Backend nachvollziehen.
 */
function handle(provider, eventId, type, payload, work) {
  if (!eventId) return;

  const existing = get('SELECT id, processed_at FROM webhook_events WHERE provider = ? AND event_id = ?', [
    provider,
    eventId,
  ]);
  if (existing?.processed_at) return;

  const id =
    existing?.id ??
    insert('webhook_events', {
      provider,
      event_id: eventId,
      type: type || '',
      payload_json: JSON.stringify(payload).slice(0, 200_000),
      created_at: nowIso(),
    });

  try {
    work();
    update('webhook_events', id, { processed_at: nowIso(), error: '' });
  } catch (error) {
    update('webhook_events', id, { error: String(error.message).slice(0, 500) });
  }
}
