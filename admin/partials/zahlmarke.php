<?php
/**
 * Statusmarke für den Zahlungsstatus einer Bestellung.
 * Erwartet $bestellung im Gültigkeitsbereich.
 */
$status = (string) $bestellung['zahlstatus'];
$klasse = match ($status) {
    'bezahlt'                    => 'bk-marke-gruen',
    'offen'                      => 'bk-marke-gelb',
    'autorisiert'                => 'bk-marke-blau',
    'erstattet', 'verfallen'     => 'bk-marke-rot',
    'teilerstattet'              => 'bk-marke-gelb',
    default                      => '',
};
?>
<span class="bk-marke <?= $klasse ?>"><i></i><?= Util::e(Bestellungen::ZAHLSTATUS[$status] ?? $status) ?></span>
