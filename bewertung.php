<?php
/**
 * Nimmt eine Kundenbewertung entgegen.
 *
 * Eigene Datei statt eines Zweigs in artikel.php, aus demselben Grund wie bei
 * warenkorb.php: Ein POST, der auf der Artikelseite selbst landet, führt beim
 * Neuladen zur erneuten Abgabe. So endet jeder Weg mit einer Umleitung.
 */

require __DIR__ . '/lib/bootstrap.php';

Theme::fassung();

$handle = Util::post('handle');
$ziel   = Config::url('artikel.php?h=' . rawurlencode($handle));

if (!Util::isPost() || !Theme::bewertungenAn()) {
    Util::redirect($ziel);
}

[$erfolg, $meldung] = Bewertungen::abgeben([
    'artikel_id' => Util::postInt('artikel_id'),
    'sterne'     => Util::postInt('sterne'),
    'name'       => Util::post('name'),
    'email'      => Util::post('email'),
    'titel'      => Util::post('titel'),
    'text'       => Util::postRaw('text'),
]);

if ($erfolg) {
    Util::redirect($ziel . '&bmeldung=' . rawurlencode($meldung) . '#bewertungen');
}

/*
 * Bei einem Fehler die Eingaben zurückgeben, damit niemand alles noch einmal
 * tippen muss. Der Fließtext bleibt bewusst außen vor: Er wäre lang, und eine
 * Adresszeile mit 800 Zeichen darin schneiden manche Server ab.
 */
Util::redirect($ziel
    . '&bfehler=' . rawurlencode($meldung)
    . '&bsterne=' . rawurlencode((string) Util::postInt('sterne'))
    . '&bname='   . rawurlencode(mb_substr(Util::post('name'), 0, 120, 'UTF-8'))
    . '&bemail='  . rawurlencode(mb_substr(Util::post('email'), 0, 190, 'UTF-8'))
    . '&btitel='  . rawurlencode(mb_substr(Util::post('titel'), 0, 200, 'UTF-8'))
    . '&bewerten=1#bewertung-schreiben');
