<?php
/**
 * Kunden und ihre Adressen.
 *
 * Die Kennzahlen "bestellungen" und "umsatz" werden bei jeder Bestellung
 * fortgeschrieben statt bei jedem Listenaufruf berechnet – die Kundenliste ist
 * eine der am häufigsten geöffneten Seiten im Backend.
 */
final class Kunden
{
    /** @return array{zeilen:array<int,array<string,mixed>>,gesamt:int} */
    public static function liste(string $suche = '', string $sortierung = 'neueste', int $limit = 50, int $offset = 0): array
    {
        $where  = '';
        $params = [];
        if ($suche !== '') {
            $such   = '%' . $suche . '%';
            $where  = 'WHERE email LIKE ? OR vorname LIKE ? OR nachname LIKE ? OR firma LIKE ?';
            $params = [$such, $such, $such, $such];
        }

        $order = match ($sortierung) {
            'umsatz'       => 'umsatz DESC',
            'bestellungen' => 'bestellungen DESC',
            'name'         => 'nachname ASC, vorname ASC',
            default        => 'id DESC',
        };

        return [
            'zeilen' => DB::all(
                'SELECT * FROM kunden ' . $where . ' ORDER BY ' . $order
                . ' LIMIT ' . max(1, min(250, $limit)) . ' OFFSET ' . max(0, $offset),
                $params
            ),
            'gesamt' => (int) DB::value('SELECT COUNT(*) FROM kunden ' . $where, $params, 0),
        ];
    }

    public static function holen(int $id): ?array
    {
        $kunde = DB::row('SELECT * FROM kunden WHERE id = ?', [$id]);
        if ($kunde === null) {
            return null;
        }
        $kunde['adressen'] = DB::all('SELECT * FROM adressen WHERE kunde_id = ? ORDER BY standard DESC, id', [$id]);
        $kunde['bestellliste'] = DB::all(
            'SELECT id, nummer, erstellt, gesamt, zahlstatus, versandstatus, waehrung, status
               FROM bestellungen WHERE kunde_id = ? ORDER BY id DESC LIMIT 50',
            [$id]
        );
        return $kunde;
    }

    public static function nachEmail(string $email): ?array
    {
        return DB::row('SELECT * FROM kunden WHERE email = ?', [Util::normalizeEmail($email)]);
    }

    /** @param array<string,mixed> $daten */
    public static function anlegen(array $daten): int
    {
        $felder = self::felderPruefen($daten);
        if (self::nachEmail($felder['email']) !== null) {
            throw new InvalidArgumentException('Diese E-Mail-Adresse ist bereits vergeben.');
        }
        $felder['erstellt'] = Util::now();
        return DB::insert('kunden', $felder);
    }

    /** @param array<string,mixed> $daten */
    public static function speichern(int $id, array $daten): void
    {
        $vorher = DB::row('SELECT * FROM kunden WHERE id = ?', [$id]);
        if ($vorher === null) {
            throw new RuntimeException('Kunde nicht gefunden.');
        }
        $felder    = self::felderPruefen($daten, $vorher);
        $kollision = self::nachEmail($felder['email']);
        if ($kollision !== null && (int) $kollision['id'] !== $id) {
            throw new InvalidArgumentException('Diese E-Mail-Adresse ist bereits vergeben.');
        }
        DB::update('kunden', $id, $felder);
    }

    private static function felderPruefen(array $daten, ?array $vorher = null): array
    {
        $email = Util::normalizeEmail((string) ($daten['email'] ?? $vorher['email'] ?? ''));
        if (!Util::isEmail($email)) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        return [
            'email'      => mb_substr($email, 0, 190, 'UTF-8'),
            'vorname'    => mb_substr((string) ($daten['vorname'] ?? $vorher['vorname'] ?? ''), 0, 120, 'UTF-8'),
            'nachname'   => mb_substr((string) ($daten['nachname'] ?? $vorher['nachname'] ?? ''), 0, 120, 'UTF-8'),
            'telefon'    => mb_substr((string) ($daten['telefon'] ?? $vorher['telefon'] ?? ''), 0, 60, 'UTF-8'),
            'firma'      => mb_substr((string) ($daten['firma'] ?? $vorher['firma'] ?? ''), 0, 150, 'UTF-8'),
            'newsletter' => !empty($daten['newsletter']) ? 1 : 0,
            'notiz'      => mb_substr((string) ($daten['notiz'] ?? $vorher['notiz'] ?? ''), 0, 2000, 'UTF-8'),
            'geaendert'  => Util::now(),
        ];
    }

    public static function loeschen(int $id): void
    {
        DB::transaction(static function () use ($id): void {
            DB::run('DELETE FROM adressen WHERE kunde_id = ?', [$id]);
            // Bestellungen bleiben erhalten, verlieren aber die Verknüpfung –
            // Belege dürfen nicht verschwinden, weil ein Konto gelöscht wird.
            DB::run('UPDATE bestellungen SET kunde_id = NULL WHERE kunde_id = ?', [$id]);
            DB::delete('kunden', $id);
        });
    }

    /**
     * Findet oder legt den Kunden zu einer Bestellung an (Bestellung ohne Konto).
     * Vorhandene Daten werden nur ergänzt, nie mit Leerwerten überschrieben.
     */
    public static function ausBestellung(string $email, array $adresse, string $telefon = '', bool $newsletter = false): ?int
    {
        $email = Util::normalizeEmail($email);
        if (!Util::isEmail($email)) {
            return null;
        }

        $vorhanden = self::nachEmail($email);
        if ($vorhanden !== null) {
            $patch = ['geaendert' => Util::now()];
            foreach ([
                'vorname'  => $adresse['vorname'] ?? '',
                'nachname' => $adresse['nachname'] ?? '',
                'firma'    => $adresse['firma'] ?? '',
                'telefon'  => $telefon,
            ] as $feld => $wert) {
                if (trim((string) $vorhanden[$feld]) === '' && trim((string) $wert) !== '') {
                    $patch[$feld] = mb_substr((string) $wert, 0, 150, 'UTF-8');
                }
            }
            if ($newsletter) {
                $patch['newsletter'] = 1;
            }
            DB::update('kunden', (int) $vorhanden['id'], $patch);
            return (int) $vorhanden['id'];
        }

        return DB::insert('kunden', [
            'email'      => mb_substr($email, 0, 190, 'UTF-8'),
            'vorname'    => mb_substr((string) ($adresse['vorname'] ?? ''), 0, 120, 'UTF-8'),
            'nachname'   => mb_substr((string) ($adresse['nachname'] ?? ''), 0, 120, 'UTF-8'),
            'firma'      => mb_substr((string) ($adresse['firma'] ?? ''), 0, 150, 'UTF-8'),
            'telefon'    => mb_substr($telefon, 0, 60, 'UTF-8'),
            'newsletter' => $newsletter ? 1 : 0,
            'erstellt'   => Util::now(),
            'geaendert'  => Util::now(),
        ]);
    }

    public static function bestellungZaehlen(?int $kundeId, int $betrag): void
    {
        if ($kundeId === null) {
            return;
        }
        DB::run(
            'UPDATE kunden SET bestellungen = bestellungen + 1, umsatz = umsatz + ?, geaendert = ? WHERE id = ?',
            [$betrag, Util::now(), $kundeId]
        );
    }

    /**
     * Adressprüfung für Kasse und Bestellbearbeitung.
     *
     * @param array<string,mixed> $eingabe
     * @return array<string,string>
     * @throws InvalidArgumentException mit einer für Kunden lesbaren Meldung
     */
    public static function adressePruefen(array $eingabe, bool $nameNoetig = true): array
    {
        $adresse = [
            'vorname'  => mb_substr(trim((string) ($eingabe['vorname'] ?? '')), 0, 120, 'UTF-8'),
            'nachname' => mb_substr(trim((string) ($eingabe['nachname'] ?? '')), 0, 120, 'UTF-8'),
            'firma'    => mb_substr(trim((string) ($eingabe['firma'] ?? '')), 0, 150, 'UTF-8'),
            'strasse'  => mb_substr(trim((string) ($eingabe['strasse'] ?? '')), 0, 200, 'UTF-8'),
            'zusatz'   => mb_substr(trim((string) ($eingabe['zusatz'] ?? '')), 0, 200, 'UTF-8'),
            'plz'      => mb_substr(trim((string) ($eingabe['plz'] ?? '')), 0, 20, 'UTF-8'),
            'ort'      => mb_substr(trim((string) ($eingabe['ort'] ?? '')), 0, 120, 'UTF-8'),
            'land'     => mb_strtoupper(mb_substr(trim((string) ($eingabe['land'] ?? 'DE')), 0, 2, 'UTF-8'), 'UTF-8'),
            'telefon'  => mb_substr(trim((string) ($eingabe['telefon'] ?? '')), 0, 60, 'UTF-8'),
        ];

        $fehlt = [];
        if ($nameNoetig && $adresse['vorname'] === '')  $fehlt[] = 'Vorname';
        if ($nameNoetig && $adresse['nachname'] === '') $fehlt[] = 'Nachname';
        if ($adresse['strasse'] === '') $fehlt[] = 'Straße';
        if ($adresse['plz'] === '')     $fehlt[] = 'PLZ';
        if ($adresse['ort'] === '')     $fehlt[] = 'Ort';
        if (!preg_match('/^[A-Z]{2}$/', $adresse['land'])) $fehlt[] = 'Land';

        if ($fehlt !== []) {
            throw new InvalidArgumentException('Bitte noch ausfüllen: ' . implode(', ', $fehlt) . '.');
        }
        return $adresse;
    }

    /** Adresse als mehrzeiliger Text. */
    public static function adresseText(array $adresse, string $trenner = "\n"): string
    {
        $zeilen = array_filter([
            trim(($adresse['vorname'] ?? '') . ' ' . ($adresse['nachname'] ?? '')),
            $adresse['firma'] ?? '',
            $adresse['strasse'] ?? '',
            $adresse['zusatz'] ?? '',
            trim(($adresse['plz'] ?? '') . ' ' . ($adresse['ort'] ?? '')),
            Versand::landName((string) ($adresse['land'] ?? '')),
        ], static fn($z) => trim((string) $z) !== '');
        return implode($trenner, $zeilen);
    }

    public static function adresseSpeichern(int $kundeId, array $adresse, bool $standard = false): int
    {
        $geprueft = self::adressePruefen($adresse, false);
        if ($standard) {
            DB::run('UPDATE adressen SET standard = 0 WHERE kunde_id = ?', [$kundeId]);
        }
        return DB::insert('adressen', array_merge($geprueft, [
            'kunde_id' => $kundeId,
            'standard' => $standard ? 1 : 0,
        ]));
    }
}
