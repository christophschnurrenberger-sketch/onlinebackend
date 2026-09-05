/*
 * Zahlungsanbieter.
 *
 * Die Webhook-Signatur ist sicherheitskritisch: ohne sie könnte jeder mit
 * einem POST auf die Webhook-Adresse Bestellungen als bezahlt markieren.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import stripe from '../src/payments/stripe.js';
import paypal from '../src/payments/paypal.js';
import mock from '../src/payments/mock.js';
import offline from '../src/payments/offline.js';
import config from '../src/config.js';

test('Testzahlung schließt sofort ab', async () => {
  const result = await mock.start({ id: 1, total: 1000, currency: 'EUR' });
  assert.equal(result.action, 'complete');
  assert.equal(result.status, 'paid');
  assert.ok(result.reference.startsWith('mock_'));
});

test('Offline-Zahlarten lassen die Bestellung offen', async () => {
  for (const provider of offline) {
    const result = await provider.start();
    assert.equal(result.action, 'complete');
    assert.equal(result.status, 'pending', `${provider.id} bleibt offen`);
    assert.ok(provider.instructions.length > 0, `${provider.id} erklärt sich dem Kunden`);
  }
  assert.deepEqual(offline.map((p) => p.id), ['invoice', 'prepayment', 'cod']);
});

test('Anbieter ohne Zugangsdaten melden sich als nicht verfügbar', () => {
  assert.equal(stripe.available(), Boolean(config.payments.stripe.secretKey));
  assert.equal(paypal.available(), Boolean(config.payments.paypal.clientId && config.payments.paypal.clientSecret));
  assert.equal(mock.available(), true);
});

test('Stripe-Webhook ohne gültige Signatur wird abgelehnt', () => {
  const body = Buffer.from(JSON.stringify({ id: 'evt_1', type: 'checkout.session.completed' }));

  assert.equal(stripe.verifyWebhook(body, '').ok, false, 'ohne Signaturkopf');
  assert.equal(stripe.verifyWebhook(body, 't=123,v1=abc').ok, false, 'mit erfundener Signatur');
});

test('Stripe-Webhook mit gültiger Signatur wird angenommen', () => {
  const secret = 'whsec_testgeheimnis';
  const original = config.payments.stripe.webhookSecret;
  config.payments.stripe.webhookSecret = secret;

  try {
    const timestamp = Math.floor(Date.now() / 1000);
    const body = Buffer.from(JSON.stringify({ id: 'evt_1', type: 'checkout.session.completed' }));
    const signature = createHmac('sha256', secret)
      .update(`${timestamp}.${body.toString('utf8')}`)
      .digest('hex');

    assert.equal(stripe.verifyWebhook(body, `t=${timestamp},v1=${signature}`).ok, true);

    // Ein alter Zeitstempel ist ein Replay und wird abgelehnt.
    const alt = timestamp - 3600;
    const alteSignatur = createHmac('sha256', secret)
      .update(`${alt}.${body.toString('utf8')}`)
      .digest('hex');
    const result = stripe.verifyWebhook(body, `t=${alt},v1=${alteSignatur}`);
    assert.equal(result.ok, false);
    assert.match(result.reason, /Zeitstempel/);
  } finally {
    config.payments.stripe.webhookSecret = original;
  }
});

test('Stripe-Ereignisse werden in unsere Begriffe übersetzt', () => {
  const bezahlt = stripe.parseEvent({
    id: 'evt_1',
    type: 'checkout.session.completed',
    data: { object: { payment_status: 'paid', payment_intent: 'pi_1', metadata: { order_id: '42' } } },
  });
  assert.deepEqual(bezahlt, { eventId: 'evt_1', orderId: 42, status: 'paid', reference: 'pi_1' });

  const abgelaufen = stripe.parseEvent({
    id: 'evt_2', type: 'checkout.session.expired',
    data: { object: { id: 'cs_1', metadata: { order_id: '42' } } },
  });
  assert.equal(abgelaufen.status, 'voided');

  const unbekannt = stripe.parseEvent({ id: 'evt_3', type: 'customer.created', data: { object: {} } });
  assert.equal(unbekannt.status, null, 'unbekannte Ereignisse lösen nichts aus');
});

test('PayPal-Ereignisse werden übersetzt', () => {
  const bezahlt = paypal.parseEvent({
    id: 'WH-1', event_type: 'PAYMENT.CAPTURE.COMPLETED',
    resource: { id: 'CAP-1', custom_id: '7' },
  });
  assert.deepEqual(bezahlt, { eventId: 'WH-1', orderId: 7, status: 'paid', reference: 'CAP-1' });

  const erstattet = paypal.parseEvent({
    id: 'WH-2', event_type: 'PAYMENT.CAPTURE.REFUNDED',
    resource: { id: 'REF-1', custom_id: '7' },
  });
  assert.equal(erstattet.status, 'refunded');
});

test('PayPal lehnt Webhooks ohne konfigurierte Webhook-ID ab', async () => {
  const result = await paypal.verifyWebhook(Buffer.from('{}'), {});
  assert.equal(result.ok, false);
});
