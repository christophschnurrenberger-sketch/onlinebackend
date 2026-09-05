/*
 * Zahlarten ohne Online-Abwicklung: Rechnung, Vorkasse, Nachnahme.
 *
 * Die Bestellung entsteht sofort und bleibt auf "offen", bis der Betreiber im
 * Backend "Als bezahlt markieren" klickt. Gerade im deutschsprachigen Handel
 * sind das die meistgenutzten Zahlarten – sie brauchen keinen Anbieter, aber
 * sehr wohl klare Hinweise für den Kunden.
 */

const make = (id, label, instructions, financialStatus = 'pending') => ({
  id,
  label,
  instructions,
  redirects: false,
  available: () => true,

  async start() {
    return {
      action: 'complete',
      status: financialStatus,
      reference: '',
      message: instructions,
    };
  },

  async confirm(order) {
    return { status: order.financial_status, reference: order.payment_reference };
  },
});

export default [
  make(
    'invoice',
    'Kauf auf Rechnung',
    'Du erhältst die Rechnung mit der Ware. Zahlbar innerhalb von 14 Tagen.',
  ),
  make(
    'prepayment',
    'Vorkasse / Überweisung',
    'Wir senden dir die Bankverbindung per E-Mail. Die Ware geht nach Zahlungseingang raus.',
  ),
  make(
    'cod',
    'Nachnahme',
    'Du zahlst bei Lieferung an den Zusteller, zzgl. Nachnahmegebühr.',
  ),
];
