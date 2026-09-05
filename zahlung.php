<?php
/**
 * Rückkehr vom Zahlungsanbieter.
 *
 * Hier wird der Zahlungsstatus beim Anbieter nachgefragt – die bloße Rückkehr
 * gilt nicht als Zahlungsnachweis, sonst könnte jeder diese Adresse aufrufen
 * und so tun, als hätte er bezahlt.
 */

require __DIR__ . '/lib/bootstrap.php';

$token = Util::get('token');
if ($token === '') {
    Util::redirect(Config::url());
}

/* Abbruch: Kunde hat beim Anbieter abgebrochen. */
if (Util::get('abbruch') !== '') {
    $bestellung = Bestellungen::nachToken($token);
    if ($bestellung !== null && (string) $bestellung['zahlstatus'] !== 'bezahlt') {
        Kasse::abbrechen((int) $bestellung['id'], 'Zahlung vom Kunden abgebrochen');
    }
    Util::redirect(Config::url('warenkorb.php'));
}

Kasse::bestaetigen($token);
Util::redirect(Config::url('bestellung.php?t=' . rawurlencode($token) . '&neu=1'));
