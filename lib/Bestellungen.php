<?php
/**
 * Bestellungen.
 *
 * Eine Bestellung ist ein Dokument, keine Sicht auf den Katalog: Titel,
 * Artikelnummer und Preis werden in die Zeilen kopiert. Ändert der Betreiber
 * später Preis oder Artikelnamen, bleibt die Bestellung so, wie der Kunde sie
 * abgeschlossen hat – das ist handelsrechtlich nötig und macht Belege
 * reproduzierbar.
 */
final class Bestellungen
{
    public const ZAHLSTATUS = [
        'offen'         => 'Zahlung offen',
        'autorisiert'   => 'Autorisiert',
        'bezahlt'       => 'Bezahlt',
        'teilerstattet' => 'Teilweise erstattet',
        'erstattet'     => 'Erstattet',
        'verfallen'     => 'Verfallen',
    ];

    public const VERSANDSTATUS = [
        'offen'     => 'Noch nicht versendet',
        'teilweise' => 'Teilweise versendet',
        'versendet' => 'Versendet',
    ];

    public const STATUS = [
        'offen'      => 'Offen',
        'archiviert' => 'Archiviert',
        'storniert'  => 'Storniert',
    ];

    /* ---------------------------------------------------------------- Lesen */

    /**
     * @param array<string,mixed> $filter
     * @return array{zeilen:array<int,array<string,mixed>>,gesamt:int,summe:int}
     */
    public static function liste(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['suche'])) {
            $such    = '%' . $filter['suche'] . '%';
            $where[] = '(b.email LIKE ? OR b.nummer LIKE ? OR b.lieferadresse LIKE ?)';
            $params  = array_merge($params, [$such, $such, $such]);
        }
        foreach (['status' => 'b.status', 'zahlstatus' => 'b.zahlstatus', 'versandstatus' => 'b.versandstatus'] as $key => $spalte) {
            if (!empty($filter[$key])) {
                $where[]  = $spalte . ' = ?';
                $params[] = $filter[$key];
            }
        }
        if (!empty($filter['kunde_id'])) {
            $where[]  = 'b.kunde_id = ?';
            $params[] = (int) $filter['kunde_id'];
        }
        if (!empty($filter['von'])) {
            $where[]  = 'b.erstellt >= ?';
            $params[] = $filter['von'] . ' 00:00:00';
        }
        if (!empty($filter['bis'])) {
            $where[]  = 'b.erstellt <= ?';
            $params[] = $filter['bis'] . ' 23:59:59';
        }

        $bedingung = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit     = max(1, min(250, (int) ($filter['limit'] ?? 50)));
        $offset    = max(0, (int) ($filter['offset'] ?? 0));

        $zeilen = DB::all(
            'SELECT b.*,
                    (SELECT COALESCE(SUM(menge),0) FROM bestellzeilen z WHERE z.bestellung_id = b.id) AS artikelzahl
               FROM bestellungen b ' . $bedingung . '
              ORDER BY b.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        foreach ($zeilen as &$zeile) {
            $zeile['lieferadresse_daten'] = json_decode((string) $zeile['lieferadresse'], true) ?: [];
        }
        unset($zeile);

        $summen = DB::row(
            'SELECT COUNT(*) AS anzahl, COALESCE(SUM(gesamt),0) AS summe FROM bestellungen b ' . $bedingung,
            $params
        );

        return [
            'zeilen' => $zeilen,
            'gesamt' => (int) ($summen['anzahl'] ?? 0),
            'summe'  => (int) ($summen['summe'] ?? 0),
        ];
    }

    public static function holen(int $id): ?array
    {
        $bestellung = DB::row('SELECT * FROM bestellungen WHERE id = ?', [$id]);
        return $bestellung === null ? null : self::anreichern($bestellung);
    }

    public static function nachToken(string $token): ?array
    {
        $bestellung = DB::row('SELECT * FROM bestellungen WHERE token = ?', [$token]);
        return $bestellung === null ? null : self::anreichern($bestellung);
    }

    private static function anreichern(array $b): array
    {
        $id = (int) $b['id'];
        $b['lieferadresse_daten']    = json_decode((string) $b['lieferadresse'], true) ?: [];
        $b['rechnungsadresse_daten'] = json_decode((string) $b['rechnungsadresse'], true) ?: [];
        $b['zeilen']     = DB::all('SELECT * FROM bestellzeilen WHERE bestellung_id = ? ORDER BY id', [$id]);
        $b['ereignisse'] = DB::all(
            'SELECT e.*, u.name AS benutzer_name FROM bestellereignisse e
               LEFT JOIN benutzer u ON u.id = e.benutzer_id
              WHERE e.bestellung_id = ? ORDER BY e.id DESC',
            [$id]
        );
        $b['sendungen']    = DB::all('SELECT * FROM sendungen WHERE bestellung_id = ? ORDER BY id', [$id]);
        $b['erstattungen'] = DB::all('SELECT * FROM erstattungen WHERE bestellung_id = ? ORDER BY id', [$id]);
        $b['zahlungen']    = DB::all('SELECT * FROM zahlungen WHERE bestellung_id = ? ORDER BY id', [$id]);
        return $b;
    }

    /* -------------------------------------------------------------- Anlegen */

    /** Fortlaufende Bestellnummer; Startwert kommt aus den Einstellungen. */
    private static function naechsteNummer(): int
    {
        $start = Settings::int('bestellnummer_start', 1000);
        $max   = (int) DB::value('SELECT MAX(nummer) FROM bestellungen', [], 0);
        return max($start, $max + 1);
    }

    /**
     * Schreibt eine Bestellung aus einem bepreisten Warenkorb.
     * Bestand und Zahlung hat der Aufrufer (Kasse) bereits geklärt.
     *
     * @param array<string,mixed> $preise Ergebnis von Preise::rechnen()
     * @return int Die neue Bestell-ID
     */
    public static function anlegen(array $preise, array $daten): int
    {
        return DB::transaction(static function () use ($preise, $daten): int {
            $jetzt = Util::now();
            $id = DB::insert('bestellungen', [
                'nummer'           => self::naechsteNummer(),
                'token'            => Util::token(20),
                'kunde_id'         => $daten['kunde_id'] ?? null,
                'email'            => Util::normalizeEmail((string) ($daten['email'] ?? '')),
                'telefon'          => mb_substr((string) ($daten['telefon'] ?? ''), 0, 60, 'UTF-8'),
                'status'           => 'offen',
                'zahlstatus'       => Util::einesVon((string) ($daten['zahlstatus'] ?? 'offen'), array_keys(self::ZAHLSTATUS), 'offen'),
                'versandstatus'    => 'offen',
                'waehrung'         => (string) $preise['waehrung'],
                'zwischensumme'    => (int) $preise['zwischensumme'],
                'rabatt'           => (int) $preise['rabatt'],
                'versandkosten'    => (int) $preise['versandkosten'],
                'steuer'           => (int) $preise['steuer'],
                'gesamt'           => (int) $preise['gesamt'],
                'rabattcode'       => (string) $preise['rabattcode'],
                'versandart'       => (string) ($preise['versandart']['name'] ?? ''),
                'lieferadresse'    => json_encode($daten['lieferadresse'] ?? [], JSON_UNESCAPED_UNICODE),
                'rechnungsadresse' => json_encode($daten['rechnungsadresse'] ?? ($daten['lieferadresse'] ?? []), JSON_UNESCAPED_UNICODE),
                'zahlart'          => (string) ($daten['zahlart'] ?? ''),
                'kundennotiz'      => mb_substr((string) ($daten['kundennotiz'] ?? ''), 0, 2000, 'UTF-8'),
                'erstellt'         => $jetzt,
                'geaendert'        => $jetzt,
            ]);

            foreach ($preise['positionen'] as $position) {
                DB::insert('bestellzeilen', [
                    'bestellung_id' => $id,
                    'varianten_id'  => $position['varianten_id'],
                    'artikel_id'    => $position['artikel_id'],
                    'titel'         => $position['titel'],
                    'variante'      => $position['variante'],
                    'artikelnummer' => $position['artikelnummer'],
                    'bild_url'      => $position['bild_url'],
                    'menge'         => $position['menge'],
                    'preis'         => $position['preis'],
                    'rabatt'        => $position['rabatt'],
                    'gesamt'        => $position['gesamt'],
                    'steuersatz_bp' => $position['steuersatz_bp'],
                    'steuer'        => $position['steuer'],
                    'versandpflicht' => $position['versandpflicht'] ? 1 : 0,
                ]);
            }

            self::ereignis($id, 'angelegt', 'Bestellung eingegangen'
                . ((string) ($daten['zahlart'] ?? '') !== '' ? ' (' . Zahlung::name((string) $daten['zahlart']) . ')' : ''));

            return $id;
        });
    }

    public static function ereignis(int $bestellungId, string $art, string $text, ?int $benutzerId = null): void
    {
        DB::insert('bestellereignisse', [
            'bestellung_id' => $bestellungId,
            'art'           => mb_substr($art, 0, 40, 'UTF-8'),
            'text'          => mb_substr($text, 0, 1000, 'UTF-8'),
            'benutzer_id'   => $benutzerId,
            'erstellt'      => Util::now(),
        ]);
    }

    /* ------------------------------------------------------- Status ändern */

    public static function alsBezahltMarkieren(int $id, string $referenz = '', ?int $benutzerId = null): void
    {
        $bestellung = DB::row('SELECT * FROM bestellungen WHERE id = ?', [$id]);
        if ($bestellung === null || (string) $bestellung['zahlstatus'] === 'bezahlt') {
            return;
        }
        DB::update('bestellungen', $id, [
            'zahlstatus'   => 'bezahlt',
            'bezahlt_am'   => Util::now(),
            'zahlreferenz' => $referenz !== '' ? mb_substr($referenz, 0, 190, 'UTF-8') : (string) $bestellung['zahlreferenz'],
            'geaendert'    => Util::now(),
        ]);
        self::ereignis($id, 'bezahlt', 'Zahlung eingegangen' . ($referenz !== '' ? ' (' . $referenz . ')' : ''), $benutzerId);
        Mail::bestellungBezahlt($id);
    }

    public static function zahlstatusSetzen(int $id, string $status, string $text = '', ?int $benutzerId = null): void
    {
        $status = Util::einesVon($status, array_keys(self::ZAHLSTATUS), 'offen');
        DB::update('bestellungen', $id, ['zahlstatus' => $status, 'geaendert' => Util::now()]);
        self::ereignis($id, 'zahlstatus', $text !== '' ? $text : 'Zahlungsstatus: ' . self::ZAHLSTATUS[$status], $benutzerId);
    }

    public static function notizSetzen(int $id, string $notiz, ?int $benutzerId = null): void
    {
        DB::update('bestellungen', $id, ['notiz' => mb_substr($notiz, 0, 4000, 'UTF-8'), 'geaendert' => Util::now()]);
        self::ereignis($id, 'notiz', 'Interne Notiz aktualisiert', $benutzerId);
    }

    public static function archivieren(int $id, bool $archivieren = true, ?int $benutzerId = null): void
    {
        DB::update('bestellungen', $id, [
            'status'    => $archivieren ? 'archiviert' : 'offen',
            'geaendert' => Util::now(),
        ]);
        self::ereignis($id, 'status', $archivieren ? 'Archiviert' : 'Wieder geöffnet', $benutzerId);
    }

    /* -------------------------------------------------------------- Versand */

    /**
     * Legt eine Sendung an und aktualisiert daraus den Versandstatus.
     * $zeilen = [zeilen_id => menge]; ohne Angabe wird alles Offene versendet.
     *
     * @param array<int,int> $zeilen
     */
    public static function versenden(int $id, array $zeilen = [], string $dienstleister = '', string $nummer = '', string $url = '', ?int $benutzerId = null): void
    {
        DB::transaction(static function () use ($id, $zeilen, $dienstleister, $nummer, $url, $benutzerId): void {
            $bestellung = self::holen($id);
            if ($bestellung === null) {
                throw new RuntimeException('Bestellung nicht gefunden.');
            }
            if ((string) $bestellung['status'] === 'storniert') {
                throw new RuntimeException('Stornierte Bestellungen können nicht versendet werden.');
            }

            $auswahl = [];
            foreach ($bestellung['zeilen'] as $zeile) {
                if ((int) $zeile['versandpflicht'] !== 1) {
                    continue;
                }
                $offen = (int) $zeile['menge'] - (int) $zeile['versendet'];
                if ($offen <= 0) {
                    continue;
                }
                $menge = $zeilen === [] ? $offen : min($offen, (int) ($zeilen[(int) $zeile['id']] ?? 0));
                if ($menge > 0) {
                    $auswahl[(int) $zeile['id']] = $menge;
                }
            }
            if ($auswahl === []) {
                throw new RuntimeException('Es gibt keine offenen Positionen zum Versenden.');
            }

            foreach ($auswahl as $zeilenId => $menge) {
                DB::run('UPDATE bestellzeilen SET versendet = versendet + ? WHERE id = ?', [$menge, $zeilenId]);
            }

            DB::insert('sendungen', [
                'bestellung_id'  => $id,
                'dienstleister'  => mb_substr($dienstleister, 0, 80, 'UTF-8'),
                'sendungsnummer' => mb_substr($nummer, 0, 120, 'UTF-8'),
                'sendung_url'    => mb_substr($url, 0, 255, 'UTF-8'),
                'zeilen'         => json_encode($auswahl),
                'erstellt'       => Util::now(),
            ]);

            DB::update('bestellungen', $id, [
                'versandstatus' => self::versandstatusBerechnen($id),
                'geaendert'     => Util::now(),
            ]);
            self::ereignis($id, 'versendet',
                $nummer !== '' ? 'Versendet (' . trim($dienstleister . ' ' . $nummer) . ')' : 'Versendet',
                $benutzerId);
        });

        Mail::bestellungVersendet($id, $dienstleister, $nummer, $url);
    }

    private static function versandstatusBerechnen(int $id): string
    {
        $zeilen = DB::all('SELECT menge, versendet FROM bestellzeilen WHERE bestellung_id = ? AND versandpflicht = 1', [$id]);
        if ($zeilen === []) {
            return 'versendet';
        }
        $alles = true;
        $etwas = false;
        foreach ($zeilen as $zeile) {
            if ((int) $zeile['versendet'] < (int) $zeile['menge']) {
                $alles = false;
            }
            if ((int) $zeile['versendet'] > 0) {
                $etwas = true;
            }
        }
        return $alles ? 'versendet' : ($etwas ? 'teilweise' : 'offen');
    }

    /* -------------------------------------------------- Erstattung & Storno */

    public static function erstatten(int $id, int $betrag, string $grund = '', string $referenz = '', ?int $benutzerId = null): void
    {
        DB::transaction(static function () use ($id, $betrag, $grund, $referenz, $benutzerId): void {
            $bestellung = DB::row('SELECT * FROM bestellungen WHERE id = ?', [$id]);
            if ($bestellung === null) {
                throw new RuntimeException('Bestellung nicht gefunden.');
            }
            $offen = (int) $bestellung['gesamt'] - (int) $bestellung['erstattet'];
            if ($betrag <= 0) {
                throw new InvalidArgumentException('Der Betrag muss größer als 0 sein.');
            }
            if ($betrag > $offen) {
                throw new InvalidArgumentException('Höchstens ' . Util::geld($offen) . ' sind noch erstattbar.');
            }

            DB::insert('erstattungen', [
                'bestellung_id' => $id,
                'betrag'        => $betrag,
                'grund'         => mb_substr($grund, 0, 500, 'UTF-8'),
                'referenz'      => mb_substr($referenz, 0, 190, 'UTF-8'),
                'benutzer_id'   => $benutzerId,
                'erstellt'      => Util::now(),
            ]);

            $erstattet = (int) $bestellung['erstattet'] + $betrag;
            DB::update('bestellungen', $id, [
                'erstattet'  => $erstattet,
                'zahlstatus' => $erstattet >= (int) $bestellung['gesamt'] ? 'erstattet' : 'teilerstattet',
                'geaendert'  => Util::now(),
            ]);
            self::ereignis($id, 'erstattung', 'Erstattung über ' . Util::geld($betrag)
                . ($grund !== '' ? ' – ' . $grund : ''), $benutzerId);
        });
    }

    public static function stornieren(int $id, string $grund = '', bool $bestandZurueck = true, ?int $benutzerId = null): void
    {
        DB::transaction(static function () use ($id, $grund, $bestandZurueck, $benutzerId): void {
            $bestellung = self::holen($id);
            if ($bestellung === null || (string) $bestellung['status'] === 'storniert') {
                return;
            }
            if ($bestandZurueck) {
                Bestand::freigeben($bestellung['zeilen'], $id, 'storno', $benutzerId);
            }
            DB::update('bestellungen', $id, [
                'status'       => 'storniert',
                'storniert_am' => Util::now(),
                'zahlstatus'   => (string) $bestellung['zahlstatus'] === 'bezahlt' ? 'bezahlt' : 'verfallen',
                'geaendert'    => Util::now(),
            ]);
            self::ereignis($id, 'storniert', 'Storniert' . ($grund !== '' ? ': ' . $grund : ''), $benutzerId);
        });
    }

    /* ------------------------------------------------------------ Kennzahlen */

    /** @return array<string,mixed> Zahlen für die Startseite des Backends. */
    public static function kennzahlen(int $tage = 30): array
    {
        $seit   = date('Y-m-d H:i:s', time() - $tage * 86400);
        $davor  = date('Y-m-d H:i:s', time() - $tage * 2 * 86400);
        $aktiv  = "status != 'storniert'";

        $jetzt = DB::row(
            'SELECT COUNT(*) AS anzahl, COALESCE(SUM(gesamt),0) AS umsatz,
                    COALESCE(SUM(gesamt - erstattet),0) AS netto
               FROM bestellungen WHERE erstellt >= ? AND ' . $aktiv,
            [$seit]
        ) ?? [];

        $vorher = DB::row(
            'SELECT COUNT(*) AS anzahl, COALESCE(SUM(gesamt),0) AS umsatz
               FROM bestellungen WHERE erstellt >= ? AND erstellt < ? AND ' . $aktiv,
            [$davor, $seit]
        ) ?? [];

        $anzahl = (int) ($jetzt['anzahl'] ?? 0);
        $umsatz = (int) ($jetzt['umsatz'] ?? 0);

        return [
            'tage'           => $tage,
            'anzahl'         => $anzahl,
            'umsatz'         => $umsatz,
            'netto'          => (int) ($jetzt['netto'] ?? 0),
            'schnitt'        => $anzahl > 0 ? (int) round($umsatz / $anzahl) : 0,
            'anzahl_vorher'  => (int) ($vorher['anzahl'] ?? 0),
            'umsatz_vorher'  => (int) ($vorher['umsatz'] ?? 0),
            'offen'          => (int) DB::value("SELECT COUNT(*) FROM bestellungen WHERE status = 'offen'", [], 0),
            'unversendet'    => (int) DB::value("SELECT COUNT(*) FROM bestellungen WHERE status = 'offen' AND versandstatus != 'versendet'", [], 0),
            'unbezahlt'      => (int) DB::value("SELECT COUNT(*) FROM bestellungen WHERE status = 'offen' AND zahlstatus = 'offen'", [], 0),
            'taeglich'       => DB::all(
                'SELECT SUBSTR(erstellt, 1, 10) AS tag, COUNT(*) AS anzahl, COALESCE(SUM(gesamt),0) AS umsatz
                   FROM bestellungen WHERE erstellt >= ? AND ' . $aktiv . '
                  GROUP BY tag ORDER BY tag',
                [$seit]
            ),
            'top_artikel'    => DB::all(
                'SELECT z.artikel_id, z.titel, SUM(z.menge) AS menge, SUM(z.gesamt) AS umsatz
                   FROM bestellzeilen z JOIN bestellungen b ON b.id = z.bestellung_id
                  WHERE b.erstellt >= ? AND b.status != \'storniert\'
                  GROUP BY z.artikel_id, z.titel ORDER BY umsatz DESC LIMIT 8',
                [$seit]
            ),
        ];
    }
}
