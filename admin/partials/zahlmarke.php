<?php
/**
 * Statusmarke für den Zahlungsstatus einer Bestellung.
 * Erwartet $bestellung im Gültigkeitsbereich.
 */
$status = (string) $bestellung['zahlstatus'];
$klasse = match ($status) {
    'bezahlt'                    => 'ad-marke-gruen',
    'offen'                      => 'ad-marke-gelb',
    'autorisiert'                => 'ad-marke-blau',
    'erstattet', 'verfallen'     => 'ad-marke-rot',
    'teilerstattet'              => 'ad-marke-gelb',
    default                      => '',
};
?>
<span class="ad-marke <?= $klasse ?>"><i></i><?= Util::e(Bestellungen::ZAHLSTATUS[$status] ?? $status) ?></span>
