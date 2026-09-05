<?php
/**
 * Webhooks der Zahlungsanbieter.
 *
 * Der Kunde kann den Browser nach dem Bezahlen schließen – dann ist der Webhook
 * der einzige Weg, vom Geldeingang zu erfahren. Er ist deshalb kein Extra,
 * sondern der verlässliche Pfad; die Rückkehr im Browser ist nur der schnelle.
 *
 * Zwei Regeln: Signatur prüfen, bevor irgendetwas gebucht wird, und jedes
 * Ereignis nur einmal verarbeiten.
 *
 * Adressen für die Einrichtung beim Anbieter:
 *   .../webhook.php?anbieter=stripe
 *   .../webhook.php?anbieter=paypal
 */

require __DIR__ . '/lib/bootstrap.php';

$anbieter = Util::get('anbieter');
$rohdaten = (string) file_get_contents('php://input');

if (!in_array($anbieter, ['stripe', 'paypal'], true) || $rohdaten === '') {
    http_response_code(400);
    exit;
}

/* --- Signatur prüfen ------------------------------------------------------ */

$kopfzeilen = [];
foreach ($_SERVER as $schluessel => $wert) {
    if (str_starts_with($schluessel, 'HTTP_')) {
        $kopfzeilen[strtolower(str_replace('_', '-', substr($schluessel, 5)))] = (string) $wert;
    }
}

$gueltig = $anbieter === 'stripe'
    ? Zahlung::stripeSignaturPruefen($rohdaten, $kopfzeilen['stripe-signature'] ?? '')
    : Zahlung::paypalSignaturPruefen($rohdaten, $kopfzeilen);

if (!$gueltig) {
    Log::warn('webhook', $anbieter . ': Meldung mit ungültiger Signatur abgewiesen (' . Util::ip() . ')');
    http_response_code(400);
    echo 'Signatur ungültig';
    exit;
}

$ereignis = json_decode($rohdaten, true);
if (!is_array($ereignis)) {
    http_response_code(400);
    exit;
}

/* --- Ereignis deuten ------------------------------------------------------ */

$ereignisId = (string) ($ereignis['id'] ?? '');
$art        = (string) ($ereignis['type'] ?? $ereignis['event_type'] ?? '');
$bestellungId = 0;
$status     = '';
$referenz   = '';

if ($anbieter === 'stripe') {
    $objekt       = $ereignis['data']['object'] ?? [];
    $bestellungId = (int) ($objekt['metadata']['bestellung_id'] ?? $objekt['client_reference_id'] ?? 0);
    $referenz     = (string) ($objekt['payment_intent'] ?? $objekt['id'] ?? '');

    $status = match ($art) {
        'checkout.session.completed', 'checkout.session.async_payment_succeeded'
            => ($objekt['payment_status'] ?? '') === 'paid' ? 'bezahlt' : '',
        'checkout.session.expired', 'checkout.session.async_payment_failed' => 'verfallen',
        'charge.refunded' => 'erstattet',
        default => '',
    };
} else {
    $quelle       = $ereignis['resource'] ?? [];
    $bestellungId = (int) ($quelle['custom_id']
        ?? $quelle['purchase_units'][0]['custom_id']
        ?? 0);
    $referenz     = (string) ($quelle['id'] ?? '');

    $status = match ($art) {
        'PAYMENT.CAPTURE.COMPLETED' => 'bezahlt',
        'PAYMENT.CAPTURE.DENIED'    => 'verfallen',
        'PAYMENT.CAPTURE.REFUNDED'  => 'erstattet',
        default => '',
    };
}

/* --- Genau einmal verarbeiten --------------------------------------------- */

if ($ereignisId === '') {
    http_response_code(200);
    echo 'ok';
    exit;
}

$vorhanden = DB::row('SELECT id, verarbeitet FROM webhooks WHERE anbieter = ? AND ereignis_id = ?', [$anbieter, $ereignisId]);
if ($vorhanden !== null && $vorhanden['verarbeitet'] !== null) {
    http_response_code(200);
    echo 'bereits verarbeitet';
    exit;
}

$eintragId = $vorhanden !== null
    ? (int) $vorhanden['id']
    : DB::insert('webhooks', [
        'anbieter'    => $anbieter,
        'ereignis_id' => mb_substr($ereignisId, 0, 190, 'UTF-8'),
        'art'         => mb_substr($art, 0, 80, 'UTF-8'),
        'rohdaten'    => mb_substr($rohdaten, 0, 100000, 'UTF-8'),
        'erstellt'    => Util::now(),
    ]);

try {
    if ($status !== '' && $bestellungId > 0) {
        Kasse::ereignisVerarbeiten($bestellungId, $status, $referenz);
    }
    DB::update('webhooks', $eintragId, ['verarbeitet' => Util::now(), 'fehler' => '']);
} catch (Throwable $e) {
    DB::update('webhooks', $eintragId, ['fehler' => mb_substr($e->getMessage(), 0, 500, 'UTF-8')]);
    Log::error('webhook', $anbieter . ' ' . $art . ': ' . $e->getMessage());
}

http_response_code(200);
echo 'ok';
