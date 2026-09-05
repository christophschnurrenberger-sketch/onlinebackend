<?php
/**
 * Kasse – der Bestellabschluss.
 *
 * Der heikelste Ablauf im Shop, deshalb in drei klare Schritte zerlegt:
 *   abschliessen() – prüfen, Bestand buchen, Bestellung anlegen, Zahlung starten
 *   bestaetigen()  – Rückkehr vom Zahlungsanbieter verarbeiten
 *   abbrechen()    – Bestand zurückgeben, Bestellung stornieren
 *
 * Der Bestand wird bewusst schon beim Anlegen gebucht: sonst könnten zwei
 * Kunden im Zahlungsfenster dasselbe letzte Stück kaufen. Bricht die Zahlung
 * ab, gibt abbrechen() die Ware wieder frei.
 */
final class Kasse
{
    /**
     * @param array<string,mixed> $eingabe
     * @return array{aktion:string,url:string,bestellung:array<string,mixed>}
     * @throws RuntimeException|InvalidArgumentException mit lesbarer Meldung
     */
    public static function abschliessen(array $eingabe): array
    {
        $korb = Warenkorb::aktueller(false);
        if ($korb === null) {
            throw new RuntimeException('Dein Warenkorb ist leer.');
        }

        /* --- 1. Eingaben prüfen ------------------------------------------- */

        $email = Util::normalizeEmail((string) ($eingabe['email'] ?? ''));
        if (!Util::isEmail($email)) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse angeben.');
        }

        $telefon = trim((string) ($eingabe['telefon'] ?? ''));
        if (Settings::bool('telefon_pflicht') && $telefon === '') {
            throw new InvalidArgumentException('Bitte eine Telefonnummer angeben.');
        }
        if (Settings::bool('agb_pflicht') && empty($eingabe['agb'])) {
            throw new InvalidArgumentException('Bitte die AGB und die Widerrufsbelehrung bestätigen.');
        }

        $zahlart = (string) ($eingabe['zahlart'] ?? '');
        if (!Zahlung::istVerfuegbar($zahlart)) {
            throw new InvalidArgumentException('Bitte eine Zahlart auswählen.');
        }

        // Adresse prüfen, bevor neu bepreist wird – das Land bestimmt den
        // Versandpreis.
        $lieferadresse = Kunden::adressePruefen($eingabe['lieferadresse'] ?? []);
        $rechnungsadresse = !empty($eingabe['abweichende_rechnung'])
            ? Kunden::adressePruefen($eingabe['rechnungsadresse'] ?? [])
            : $lieferadresse;

        Warenkorb::aendern([
            'land'  => $lieferadresse['land'],
            'email' => $email,
        ], $korb);
        if (!empty($eingabe['versandart_id'])) {
            Warenkorb::aendern(['versandart_id' => (int) $eingabe['versandart_id']]);
        }

        /* --- 2. Neu bepreisen und letzte Prüfungen ------------------------ */

        $inhalt = Warenkorb::inhalt(Warenkorb::nachToken((string) $korb['token']));

        if ($inhalt['positionen'] === []) {
            throw new RuntimeException('Dein Warenkorb ist leer.');
        }
        if ($inhalt['zeilen_probleme']) {
            throw new RuntimeException('Nicht alle Artikel sind noch in der gewünschten Menge verfügbar. '
                . 'Bitte den Warenkorb prüfen.');
        }
        if ($inhalt['rabattfehler'] !== '') {
            throw new RuntimeException($inhalt['rabattfehler']);
        }
        if ($inhalt['unter_mindest']) {
            throw new RuntimeException('Der Mindestbestellwert von ' . Util::geld($inhalt['mindestwert']) . ' ist nicht erreicht.');
        }
        if ($inhalt['versandnoetig'] && $inhalt['versandart'] === null) {
            throw new RuntimeException('In dieses Land können wir derzeit nicht liefern.');
        }

        /* --- 3. Bestellung anlegen ---------------------------------------- */

        $bestellungId = DB::transaction(static function () use ($inhalt, $email, $telefon, $lieferadresse, $rechnungsadresse, $zahlart, $eingabe): int {
            $kundeId = Kunden::ausBestellung($email, $lieferadresse, $telefon, !empty($eingabe['newsletter']));

            $id = Bestellungen::anlegen($inhalt, [
                'kunde_id'         => $kundeId,
                'email'            => $email,
                'telefon'          => $telefon,
                'lieferadresse'    => $lieferadresse,
                'rechnungsadresse' => $rechnungsadresse,
                'zahlart'          => $zahlart,
                'kundennotiz'      => (string) ($eingabe['notiz'] ?? ''),
            ]);

            // Erst nach dem Anlegen buchen, damit die Bestandsbewegungen die
            // Bestellung referenzieren. War jemand schneller, wirft reservieren()
            // und die Transaktion nimmt die eben angelegte Bestellung zurück.
            Bestand::reservieren(
                array_map(
                    static fn($p) => ['varianten_id' => $p['varianten_id'], 'menge' => $p['menge'], 'titel' => $p['titel']],
                    $inhalt['positionen']
                ),
                $id
            );

            if ($inhalt['rabattcode'] !== '') {
                $rabatt = Rabatte::nachCode($inhalt['rabattcode']);
                if ($rabatt !== null) {
                    Rabatte::nutzungZaehlen((int) $rabatt['id']);
                }
            }
            Kunden::bestellungZaehlen($kundeId, (int) $inhalt['gesamt']);
            return $id;
        });

        $bestellung = Bestellungen::holen($bestellungId);

        /* --- 4. Zahlung starten -------------------------------------------- */
        // Läuft außerhalb der Transaktion: ein HTTP-Aufruf zu Stripe oder PayPal
        // darf keine offene Datenbanktransaktion blockieren.

        try {
            $zahlung = Zahlung::starten($bestellung, $zahlart);
        } catch (Throwable $e) {
            self::abbrechen($bestellungId, 'Zahlung konnte nicht gestartet werden: ' . $e->getMessage());
            throw new RuntimeException('Die Zahlung konnte nicht gestartet werden: ' . $e->getMessage());
        }

        Zahlung::zahlungNotieren($bestellungId, $zahlart, $zahlung['referenz'], (int) $bestellung['gesamt'], $zahlung['status']);

        if ($zahlung['referenz'] !== '') {
            DB::update('bestellungen', $bestellungId, ['zahlreferenz' => $zahlung['referenz'], 'geaendert' => Util::now()]);
        }
        if ($zahlung['status'] === 'bezahlt') {
            Bestellungen::alsBezahltMarkieren($bestellungId, $zahlung['referenz']);
        } elseif ($zahlung['text'] !== '') {
            Bestellungen::ereignis($bestellungId, 'zahlung', $zahlung['text']);
        }

        // Der Warenkorb wird erst geleert, wenn die Bestellung wirklich steht.
        Warenkorb::leeren($korb);

        $bestellung = Bestellungen::holen($bestellungId);
        if ($zahlung['status'] !== 'bezahlt' || $zahlart !== 'test') {
            Mail::bestellbestaetigung($bestellungId);
        }

        return [
            'aktion'     => $zahlung['aktion'],
            'url'        => $zahlung['url'],
            'bestellung' => $bestellung,
        ];
    }

    /**
     * Rückkehr vom Zahlungsanbieter: Status beim Anbieter erfragen und buchen.
     */
    public static function bestaetigen(string $token): ?array
    {
        $bestellung = Bestellungen::nachToken($token);
        if ($bestellung === null) {
            return null;
        }
        if ((string) $bestellung['zahlstatus'] === 'bezahlt') {
            return $bestellung;
        }

        try {
            $ergebnis = Zahlung::bestaetigen($bestellung);
        } catch (Throwable $e) {
            Bestellungen::ereignis((int) $bestellung['id'], 'zahlfehler', 'Zahlungsprüfung fehlgeschlagen: ' . $e->getMessage());
            Log::warn('kasse', 'Zahlungsprüfung Bestellung ' . $bestellung['nummer'] . ': ' . $e->getMessage());
            return Bestellungen::holen((int) $bestellung['id']);
        }

        if ($ergebnis['status'] === 'bezahlt') {
            Zahlung::zahlungNotieren((int) $bestellung['id'], (string) $bestellung['zahlart'], $ergebnis['referenz'], (int) $bestellung['gesamt'], 'bezahlt');
            Bestellungen::alsBezahltMarkieren((int) $bestellung['id'], $ergebnis['referenz']);
        } elseif ($ergebnis['status'] === 'verfallen') {
            self::abbrechen((int) $bestellung['id'], 'Zahlung wurde abgebrochen oder abgelehnt.');
        }

        return Bestellungen::holen((int) $bestellung['id']);
    }

    /**
     * Abbruch: Ware zurück in den Bestand, Bestellung storniert. Bereits
     * gebuchte Zahlungen bleiben unangetastet – die gehören ins Backend, nicht
     * in einen automatischen Ablauf.
     */
    public static function abbrechen(int $bestellungId, string $grund = 'Zahlung fehlgeschlagen'): void
    {
        $bestellung = Bestellungen::holen($bestellungId);
        if ($bestellung === null || (string) $bestellung['status'] === 'storniert') {
            return;
        }
        Bestellungen::ereignis($bestellungId, 'zahlfehler', $grund);
        Bestellungen::stornieren($bestellungId, $grund, true);
    }

    /**
     * Zahlungsereignis aus einem Webhook verarbeiten. Idempotent: doppelte
     * Zustellungen verändern nichts.
     */
    public static function ereignisVerarbeiten(int $bestellungId, string $status, string $referenz = ''): void
    {
        $bestellung = Bestellungen::holen($bestellungId);
        if ($bestellung === null) {
            return;
        }

        if ($status === 'bezahlt' && (string) $bestellung['zahlstatus'] !== 'bezahlt') {
            Bestellungen::alsBezahltMarkieren($bestellungId, $referenz);
            return;
        }
        if ($status === 'verfallen'
            && (string) $bestellung['status'] !== 'storniert'
            && (string) $bestellung['zahlstatus'] !== 'bezahlt') {
            self::abbrechen($bestellungId, 'Zahlung abgelehnt (Meldung des Anbieters)');
            return;
        }
        if ($status === 'erstattet' && (int) $bestellung['erstattet'] < (int) $bestellung['gesamt']) {
            Bestellungen::erstatten(
                $bestellungId,
                (int) $bestellung['gesamt'] - (int) $bestellung['erstattet'],
                'Erstattung beim Zahlungsanbieter ausgelöst',
                $referenz
            );
        }
    }
}
