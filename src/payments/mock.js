/*
 * Testzahlung.
 *
 * Bezahlt sofort, ohne einen echten Anbieter. Damit lassen sich Checkout,
 * Bestellverwaltung und Versand vollständig durchspielen, bevor Stripe- oder
 * PayPal-Zugangsdaten existieren. In Produktion sollte die Zahlart aus sein.
 */

import { randomUUID } from 'node:crypto';

export default {
  id: 'mock',
  label: 'Testzahlung',
  instructions: 'Nur für Tests – es wird kein Geld bewegt.',
  redirects: false,
  available: () => true,

  async start(order) {
    return {
      action: 'complete',
      status: 'paid',
      reference: `mock_${randomUUID()}`,
      message: 'Testzahlung erfolgreich',
    };
  },

  async confirm(order) {
    return { status: 'paid', reference: order.payment_reference };
  },

  async refund(order, amount) {
    return { ok: true, reference: `mock_refund_${randomUUID()}`, amount };
  },
};
