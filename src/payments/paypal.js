/*
 * PayPal – Orders v2.
 *
 * Ablauf: Order anlegen -> Kunde bestätigt bei PayPal -> Rückkehr in den Shop
 * -> wir "capturen" das Geld. Erst der erfolgreiche Capture setzt die
 * Bestellung auf bezahlt; die bloße Rückkehr des Kunden reicht nicht.
 */

import config from '../config.js';

const HOSTS = {
  sandbox: 'https://api-m.sandbox.paypal.com',
  live: 'https://api-m.paypal.com',
};

const host = () => HOSTS[config.payments.paypal.env] || HOSTS.sandbox;

// Access-Token gilt mehrere Stunden – zwischenspeichern spart je Checkout
// einen zusätzlichen Roundtrip.
let cachedToken = { value: '', expiresAt: 0 };

async function accessToken() {
  const { clientId, clientSecret } = config.payments.paypal;
  if (!clientId || !clientSecret) throw new Error('PAYPAL_CLIENT_ID/SECRET fehlen');
  if (cachedToken.value && cachedToken.expiresAt > Date.now() + 60_000) return cachedToken.value;

  const auth = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const response = await fetch(`${host()}/v1/oauth2/token`, {
    method: 'POST',
    headers: {
      Authorization: `Basic ${auth}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'grant_type=client_credentials',
  });
  const json = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(json?.error_description || 'PayPal-Anmeldung fehlgeschlagen');

  cachedToken = {
    value: json.access_token,
    expiresAt: Date.now() + (json.expires_in || 3000) * 1000,
  };
  return cachedToken.value;
}

async function call(path, { method = 'POST', body = null } = {}) {
  const token = await accessToken();
  const response = await fetch(`${host()}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(json?.message || json?.details?.[0]?.description || `PayPal-Fehler ${response.status}`);
  }
  return json;
}

const amount = (cents, currency) => ({
  currency_code: currency,
  value: (cents / 100).toFixed(2),
});

export default {
  id: 'paypal',
  label: 'PayPal',
  redirects: true,
  available: () =>
    Boolean(config.payments.paypal.clientId && config.payments.paypal.clientSecret),

  async start(order, { returnUrl, cancelUrl }) {
    const created = await call('/v2/checkout/orders', {
      body: {
        intent: 'CAPTURE',
        purchase_units: [
          {
            reference_id: String(order.id),
            custom_id: String(order.id),
            invoice_id: `${order.number}-${Date.now()}`,
            description: `Bestellung ${order.number}`,
            amount: amount(order.total, order.currency),
          },
        ],
        payment_source: {
          paypal: {
            experience_context: {
              user_action: 'PAY_NOW',
              return_url: returnUrl,
              cancel_url: cancelUrl,
            },
          },
        },
      },
    });

    const approve = created.links?.find((l) => l.rel === 'approve' || l.rel === 'payer-action');
    if (!approve) throw new Error('PayPal hat keine Weiterleitungs-URL geliefert');

    return { action: 'redirect', url: approve.href, reference: created.id, status: 'pending' };
  },

  async confirm(order) {
    const reference = order.payment_reference;
    if (!reference) return { status: 'pending' };

    const current = await call(`/v2/checkout/orders/${reference}`, { method: 'GET' });
    if (current.status === 'COMPLETED') {
      return { status: 'paid', reference, raw: current };
    }
    if (current.status !== 'APPROVED') {
      return { status: current.status === 'VOIDED' ? 'voided' : 'pending', reference, raw: current };
    }

    const captured = await call(`/v2/checkout/orders/${reference}/capture`);
    const capture = captured.purchase_units?.[0]?.payments?.captures?.[0];
    return {
      status: captured.status === 'COMPLETED' ? 'paid' : 'pending',
      reference: capture?.id || reference,
      raw: captured,
    };
  },

  async refund(order, refundAmount) {
    if (!order.payment_reference) throw new Error('Keine PayPal-Referenz an der Bestellung');
    const result = await call(`/v2/payments/captures/${order.payment_reference}/refund`, {
      body: { amount: amount(refundAmount, order.currency) },
    });
    return { ok: result.status === 'COMPLETED', reference: result.id, amount: refundAmount };
  },

  /**
   * PayPal signiert Webhooks asymmetrisch; die Prüfung läuft deshalb über einen
   * API-Aufruf zurück an PayPal statt lokal.
   */
  async verifyWebhook(rawBody, headers) {
    const webhookId = config.payments.paypal.webhookId;
    if (!webhookId) return { ok: false, reason: 'PAYPAL_WEBHOOK_ID fehlt' };
    try {
      const result = await call('/v1/notifications/verify-webhook-signature', {
        body: {
          auth_algo: headers['paypal-auth-algo'],
          cert_url: headers['paypal-cert-url'],
          transmission_id: headers['paypal-transmission-id'],
          transmission_sig: headers['paypal-transmission-sig'],
          transmission_time: headers['paypal-transmission-time'],
          webhook_id: webhookId,
          webhook_event: JSON.parse(rawBody.toString('utf8')),
        },
      });
      return result.verification_status === 'SUCCESS'
        ? { ok: true }
        : { ok: false, reason: 'Signatur ungültig' };
    } catch (error) {
      return { ok: false, reason: error.message };
    }
  },

  parseEvent(event) {
    const resource = event?.resource || {};
    const orderId = Number(
      resource.custom_id ||
        resource.purchase_units?.[0]?.custom_id ||
        resource.supplementary_data?.related_ids?.order_id ||
        0,
    );

    switch (event?.event_type) {
      case 'CHECKOUT.ORDER.APPROVED':
        return { eventId: event.id, orderId, status: 'pending', reference: resource.id };
      case 'PAYMENT.CAPTURE.COMPLETED':
        return { eventId: event.id, orderId, status: 'paid', reference: resource.id };
      case 'PAYMENT.CAPTURE.DENIED':
        return { eventId: event.id, orderId, status: 'voided', reference: resource.id };
      case 'PAYMENT.CAPTURE.REFUNDED':
        return { eventId: event.id, orderId, status: 'refunded', reference: resource.id };
      default:
        return { eventId: event?.id, orderId, status: null };
    }
  },
};
