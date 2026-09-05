/*
 * Zahlungsanbieter.
 *
 * Jeder Anbieter erfüllt dieselbe kleine Schnittstelle, damit der Checkout
 * nichts über Stripe, PayPal oder Rechnungskauf wissen muss:
 *
 *   id, label, available()      – wird der Anbieter im Checkout angeboten?
 *   start(order, ctx)           – { action: 'redirect'|'complete', url?, reference? }
 *   confirm(order, payload)     – Rückkehr aus dem Anbieter-Flow verarbeiten
 *   refund(order, amountCents)  – Erstattung auslösen (optional)
 *   webhook(req, rawBody)       – eingehendes Ereignis verarbeiten (optional)
 *
 * `start` gibt entweder 'redirect' (Kunde geht zum Anbieter) oder 'complete'
 * (Bestellung kann sofort abgeschlossen werden) zurück. Mehr Fälle braucht es
 * nicht, und der Checkout-Code bleibt dadurch geradlinig.
 */

import config from '../config.js';
import { getGroup } from '../models/settings.js';
import mock from './mock.js';
import offline from './offline.js';
import stripe from './stripe.js';
import paypal from './paypal.js';

const ALL = [mock, stripe, paypal, ...offline];

export const allProviders = () => ALL;

export const getProvider = (id) => ALL.find((p) => p.id === id) || null;

/**
 * Die im Checkout anzubietenden Zahlarten: in den Einstellungen aktiviert und
 * technisch einsatzbereit (Schlüssel vorhanden). Beides muss stimmen – sonst
 * landet der Kunde in einem Flow, den der Shop gar nicht bedienen kann.
 */
export function availableProviders() {
  const settings = getGroup('payments');
  const enabled = new Set([...(settings.enabled || []), ...config.payments.enabled]);
  return ALL.filter((provider) => enabled.has(provider.id) && provider.available()).map((provider) => ({
    id: provider.id,
    label: settings[provider.id]?.label || provider.label,
    instructions: settings[provider.id]?.instructions || provider.instructions || '',
    surcharge: Number(settings[provider.id]?.surcharge || 0),
    redirects: provider.redirects === true,
  }));
}

export const isAvailable = (id) => availableProviders().some((p) => p.id === id);
