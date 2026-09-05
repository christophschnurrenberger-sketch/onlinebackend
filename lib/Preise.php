<?php
/**
 * Preise – die Preisberechnung.
 *
 * Diese Klasse ist die einzige Wahrheit über Beträge: Warenkorb, Kasse und die
 * gespeicherte Bestellung laufen alle hier durch. Gäbe es zwei Rechenwege,
 * würden sie irgendwann um einen Cent auseinanderlaufen – und genau das sieht
 * der Kunde zuerst.
 *
 * Modell: Bruttopreise, wie im EU-Handel an Endkunden üblich. Die ausgewiesene
 * Steuer ist der im Preis enthaltene Anteil, kein Aufschlag.
 */
final class Preise
{
    /**
     * @param array<int,array{variante:array<string,mixed>,menge:int}> $zeilen
     * @param array<string,mixed> $optionen land, rabattcode, versandart_id, kunde_id, email
     * @return array<string,mixed>
     */
    public static function rechnen(array $zeilen, array $optionen = []): array
    {
        $waehrung = Settings::get('waehrung', 'EUR');
        $land     = mb_strtoupper((string) ($optionen['land'] ?? Settings::get('land', 'DE')), 'UTF-8');

        /* --- Positionen ---------------------------------------------------- */

        $positionen = [];
        foreach ($zeilen as $zeile) {
            $variante = $zeile['variante'] ?? null;
            $menge    = max(0, (int) ($zeile['menge'] ?? 0));
            if (!is_array($variante) || $menge <= 0) {
                continue;
            }
            $einzel = (int) $variante['preis'];
            $positionen[] = [
                'varianten_id'   => (int) $variante['id'],
                'artikel_id'     => (int) $variante['artikel_id'],
                'titel'          => (string) ($variante['artikel_titel'] ?? $variante['titel']),
                'variante'       => (string) $variante['titel'],
                'handle'         => (string) ($variante['artikel_handle'] ?? ''),
                'artikelnummer'  => (string) ($variante['artikelnummer'] ?? ''),
                'bild_url'       => (string) ($variante['anzeigebild'] ?? $variante['bild_url'] ?? ''),
                'menge'          => $menge,
                'preis'          => $einzel,
                'streichpreis'   => $variante['streichpreis'] !== null ? (int) $variante['streichpreis'] : null,
                'zeilenwert'     => $einzel * $menge,
                'rabatt'         => 0,
                'gesamt'         => $einzel * $menge,
                'versandpflicht' => (int) ($variante['versandpflicht'] ?? 1) === 1,
                'steuer_id'      => $variante['steuer_id'] !== null ? (int) $variante['steuer_id'] : null,
                'steuersatz_bp'  => 0,
                'steuer'         => 0,
                'gewicht_g'      => (int) ($variante['gewicht_g'] ?? 0) * $menge,
            ];
        }

        $zwischensumme = array_sum(array_column($positionen, 'zeilenwert'));

        /* --- Rabatt -------------------------------------------------------- */

        $rabatt = self::rabattAnwenden($positionen, $zwischensumme, $optionen);

        /* --- Versand ------------------------------------------------------- */

        $versandnoetig = false;
        foreach ($positionen as $position) {
            if ($position['versandpflicht']) {
                $versandnoetig = true;
                break;
            }
        }

        $versandbasis  = $zwischensumme - $rabatt['betrag'];
        $versandarten  = Versand::arten($land, $versandbasis, $versandnoetig);

        $gewaehlt = null;
        $wunschId = (int) ($optionen['versandart_id'] ?? 0);
        foreach ($versandarten as $art) {
            if ((int) $art['id'] === $wunschId) {
                $gewaehlt = $art;
                break;
            }
        }
        $gewaehlt ??= ($versandarten[0] ?? null);

        $versandkosten = $gewaehlt !== null ? (int) $gewaehlt['endpreis'] : 0;
        if ($rabatt['gratisversand']) {
            $versandkosten = 0;
        }

        /* --- Steuer -------------------------------------------------------- */

        $saetze = [];
        foreach (Steuern::liste() as $satz) {
            $saetze[(int) $satz['id']] = (int) $satz['satz_bp'];
        }
        $standardSatz = Steuern::standardSatz();

        $steuerZeilen = [];
        foreach ($positionen as &$position) {
            $bp = $position['steuer_id'] !== null && isset($saetze[$position['steuer_id']])
                ? $saetze[$position['steuer_id']]
                : $standardSatz;

            $position['steuersatz_bp'] = $bp;
            $position['steuer']        = Util::steuerAusBrutto($position['gesamt'], $bp);

            if ($bp > 0) {
                $steuerZeilen[$bp] ??= ['satz_bp' => $bp, 'basis' => 0, 'betrag' => 0];
                $steuerZeilen[$bp]['basis']  += $position['gesamt'];
                $steuerZeilen[$bp]['betrag'] += $position['steuer'];
            }
        }
        unset($position);

        // Versandkosten werden mit dem höchsten im Korb vorkommenden Satz
        // besteuert – die in Deutschland übliche Vereinfachung für gemischte
        // Warenkörbe.
        $versandSatz = $positionen === []
            ? $standardSatz
            : max(array_column($positionen, 'steuersatz_bp'));
        $versandSteuer = Util::steuerAusBrutto($versandkosten, $versandSatz);
        if ($versandSteuer > 0) {
            $steuerZeilen[$versandSatz] ??= ['satz_bp' => $versandSatz, 'basis' => 0, 'betrag' => 0];
            $steuerZeilen[$versandSatz]['basis']  += $versandkosten;
            $steuerZeilen[$versandSatz]['betrag'] += $versandSteuer;
        }

        krsort($steuerZeilen);
        $steuerGesamt = array_sum(array_column($steuerZeilen, 'betrag'));

        /* --- Summe --------------------------------------------------------- */

        $gesamt      = $zwischensumme - $rabatt['betrag'] + $versandkosten;
        $mindestwert = Settings::int('mindestbestellwert', 0);

        return [
            'waehrung'       => $waehrung,
            'land'           => $land,
            'positionen'     => $positionen,
            'anzahl'         => array_sum(array_column($positionen, 'menge')),
            'zwischensumme'  => $zwischensumme,
            'rabatt'         => $rabatt['betrag'],
            'rabattcode'     => $rabatt['code'],
            'rabattname'     => $rabatt['name'],
            'rabattfehler'   => $rabatt['fehler'],
            'versandkosten'  => $versandkosten,
            'versandart'     => $gewaehlt,
            'versandarten'   => $versandarten,
            'versandnoetig'  => $versandnoetig,
            'gewicht_g'      => array_sum(array_column($positionen, 'gewicht_g')),
            'steuer'         => $steuerGesamt,
            'steuerzeilen'   => array_values($steuerZeilen),
            'gesamt'         => $gesamt,
            'mindestwert'    => $mindestwert,
            'unter_mindest'  => $mindestwert > 0 && $gesamt < $mindestwert,
        ];
    }

    /**
     * Verteilt den Rabatt auf die Positionen. Die Aufteilung ist nötig, damit
     * Teilerstattungen und die Steuer je Satz korrekt bleiben – ein Rabatt, der
     * nur als Summe existiert, lässt sich später nicht mehr zuordnen.
     *
     * @param array<int,array<string,mixed>> $positionen
     * @return array{betrag:int,code:string,name:string,fehler:string,gratisversand:bool}
     */
    private static function rabattAnwenden(array &$positionen, int $zwischensumme, array $optionen): array
    {
        $leer = ['betrag' => 0, 'code' => '', 'name' => '', 'fehler' => '', 'gratisversand' => false];

        $code = trim((string) ($optionen['rabattcode'] ?? ''));
        if ($code === '' || $positionen === []) {
            return $leer;
        }

        $pruefung = Rabatte::pruefen(
            $code,
            $zwischensumme,
            isset($optionen['kunde_id']) ? (int) $optionen['kunde_id'] : null,
            (string) ($optionen['email'] ?? '')
        );
        if (!$pruefung['ok']) {
            return array_merge($leer, ['fehler' => $pruefung['grund']]);
        }

        $rabatt = $pruefung['rabatt'];
        if ((string) $rabatt['art'] === 'gratisversand') {
            return [
                'betrag' => 0, 'code' => (string) $rabatt['code'], 'name' => (string) $rabatt['name'],
                'fehler' => '', 'gratisversand' => true,
            ];
        }

        $betroffen = self::betroffenePositionen($positionen, $rabatt);
        $basis     = 0;
        foreach ($betroffen as $index) {
            $basis += $positionen[$index]['zeilenwert'];
        }
        if ($basis <= 0) {
            return array_merge($leer, ['fehler' => 'Dieser Rabattcode gilt für keinen Artikel im Warenkorb.']);
        }

        $betrag = (string) $rabatt['art'] === 'prozent'
            ? (int) round($basis * (int) $rabatt['wert'] / 10000)
            : min((int) $rabatt['wert'], $basis);

        $gewichte = [];
        foreach ($betroffen as $index) {
            $gewichte[] = $positionen[$index]['zeilenwert'];
        }
        $anteile = Util::verteilen($betrag, $gewichte);

        foreach ($betroffen as $i => $index) {
            $positionen[$index]['rabatt'] = $anteile[$i];
            $positionen[$index]['gesamt'] = $positionen[$index]['zeilenwert'] - $anteile[$i];
        }

        return [
            'betrag' => $betrag, 'code' => (string) $rabatt['code'], 'name' => (string) $rabatt['name'],
            'fehler' => '', 'gratisversand' => false,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $positionen
     * @return array<int,int> Indizes der betroffenen Positionen
     */
    private static function betroffenePositionen(array $positionen, array $rabatt): array
    {
        $giltFuer = (string) $rabatt['gilt_fuer'];
        if ($giltFuer === 'alles') {
            return array_keys($positionen);
        }

        $ziele = json_decode((string) ($rabatt['ziele'] ?? '[]'), true) ?: [];
        if ($ziele === []) {
            return array_keys($positionen);
        }

        if ($giltFuer === 'artikel') {
            $out = [];
            foreach ($positionen as $index => $position) {
                if (in_array((int) $position['artikel_id'], array_map('intval', $ziele), true)) {
                    $out[] = $index;
                }
            }
            return $out;
        }

        // gilt_fuer === 'kategorie'
        $artikelIds = array_values(array_unique(array_map(static fn($p) => (int) $p['artikel_id'], $positionen)));
        if ($artikelIds === []) {
            return [];
        }
        $platzA = implode(',', array_fill(0, count($artikelIds), '?'));
        $platzK = implode(',', array_fill(0, count($ziele), '?'));
        $erlaubt = array_map('intval', DB::column(
            'SELECT DISTINCT artikel_id FROM kategorie_artikel
              WHERE artikel_id IN (' . $platzA . ') AND kategorie_id IN (' . $platzK . ')',
            array_merge($artikelIds, array_map('intval', $ziele))
        ));

        $out = [];
        foreach ($positionen as $index => $position) {
            if (in_array((int) $position['artikel_id'], $erlaubt, true)) {
                $out[] = $index;
            }
        }
        return $out;
    }
}
