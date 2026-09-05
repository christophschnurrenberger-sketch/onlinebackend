<?php
/**
 * CSV-Export für Artikel, Bestellungen und Kunden.
 *
 * Läuft vor dem Seitenkopf, weil hier keine HTML-Seite entsteht, sondern eine
 * Datei zum Herunterladen.
 */

require dirname(__DIR__) . '/lib/bootstrap.php';
Auth::verlangen('lesen');

$was = Util::einesVon(Util::get('was'), ['artikel', 'bestellungen', 'kunden'], 'artikel');

[$kopf, $zeilen, $dateiname] = match ($was) {
    'bestellungen' => (static function (): array {
        $daten = Bestellungen::liste(['limit' => 250, 'suche' => Util::get('suche'), 'status' => Util::get('status')]);
        $zeilen = [];
        foreach ($daten['zeilen'] as $b) {
            $a = $b['lieferadresse_daten'];
            $zeilen[] = [
                $b['nummer'], $b['erstellt'], $b['email'],
                trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? '')),
                $a['strasse'] ?? '', $a['plz'] ?? '', $a['ort'] ?? '', $a['land'] ?? '',
                Bestellungen::ZAHLSTATUS[(string) $b['zahlstatus']] ?? '',
                Bestellungen::VERSANDSTATUS[(string) $b['versandstatus']] ?? '',
                Util::geldFeld((int) $b['zwischensumme']), Util::geldFeld((int) $b['rabatt']),
                Util::geldFeld((int) $b['versandkosten']), Util::geldFeld((int) $b['steuer']),
                Util::geldFeld((int) $b['gesamt']),
            ];
        }
        return [
            ['Nummer', 'Datum', 'E-Mail', 'Name', 'Straße', 'PLZ', 'Ort', 'Land',
             'Zahlung', 'Versand', 'Zwischensumme', 'Rabatt', 'Versandkosten', 'MwSt', 'Gesamt'],
            $zeilen,
            'bestellungen',
        ];
    })(),

    'kunden' => (static function (): array {
        $daten = Kunden::liste(Util::get('suche'), 'neueste', 250);
        $zeilen = [];
        foreach ($daten['zeilen'] as $k) {
            $zeilen[] = [
                $k['email'], $k['vorname'], $k['nachname'], $k['firma'], $k['telefon'],
                $k['bestellungen'], Util::geldFeld((int) $k['umsatz']),
                (int) $k['newsletter'] === 1 ? 'ja' : 'nein', $k['erstellt'],
            ];
        }
        return [
            ['E-Mail', 'Vorname', 'Nachname', 'Firma', 'Telefon', 'Bestellungen', 'Umsatz', 'Newsletter', 'Kunde seit'],
            $zeilen,
            'kunden',
        ];
    })(),

    default => (static function (): array {
        $daten = Artikel::liste(['limit' => 250, 'suche' => Util::get('suche'), 'status' => Util::get('status')]);
        $zeilen = [];
        foreach ($daten['zeilen'] as $a) {
            $voll = Artikel::holen((int) $a['id']);
            foreach ($voll['varianten'] as $v) {
                $zeilen[] = [
                    $voll['titel'], $voll['handle'], Artikel::STATUS[(string) $voll['status']] ?? '',
                    $voll['typ'], $voll['hersteller'], $v['titel'], $v['artikelnummer'],
                    Util::geldFeld((int) $v['preis']),
                    $v['streichpreis'] !== null ? Util::geldFeld((int) $v['streichpreis']) : '',
                    $v['bestand'],
                ];
            }
        }
        return [
            ['Titel', 'Adresse', 'Status', 'Typ', 'Hersteller', 'Variante', 'Artikelnummer',
             'Preis', 'Streichpreis', 'Bestand'],
            $zeilen,
            'artikel',
        ];
    })(),
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '-' . date('Y-m-d') . '.csv"');

$aus = fopen('php://output', 'wb');
// Byte Order Mark, damit Excel die Umlaute richtig liest.
fwrite($aus, "\xEF\xBB\xBF");
fputcsv($aus, $kopf, ';', '"', '\\');
foreach ($zeilen as $zeile) {
    fputcsv($aus, $zeile, ';', '"', '\\');
}
fclose($aus);
