<?php
/**
 * Warenkorb.
 *
 * Der Warenkorb speichert nur Variante und Menge – niemals Preise. Die kommen
 * bei jedem Aufruf frisch aus der Preisberechnung, damit eine Preisänderung im
 * Backend nicht durch alte Warenkörbe unterlaufen wird.
 */
final class Warenkorb
{
    public const COOKIE = 'shop_warenkorb';
    public const MAX_MENGE = 99;

    private static ?array $aktuell = null;

    /* ------------------------------------------------------- Warenkorb holen */

    /** Der Warenkorb dieses Besuchers; legt bei Bedarf einen an. */
    public static function aktueller(bool $anlegen = true): ?array
    {
        if (self::$aktuell !== null) {
            return self::$aktuell;
        }
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($token !== '') {
            $korb = DB::row('SELECT * FROM warenkoerbe WHERE token = ?', [$token]);
            if ($korb !== null) {
                self::$aktuell = $korb;
                return $korb;
            }
        }
        if (!$anlegen) {
            return null;
        }

        $token = Util::token(24);
        $id    = DB::insert('warenkoerbe', [
            'token'    => $token,
            'land'     => Settings::get('land', 'DE'),
            'erstellt' => Util::now(),
            'geaendert' => Util::now(),
        ]);

        if (!headers_sent()) {
            setcookie(self::COOKIE, $token, [
                'expires'  => time() + 30 * 86400,
                'path'     => self::cookiePfad(),
                'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[self::COOKIE] = $token;

        self::$aktuell = DB::row('SELECT * FROM warenkoerbe WHERE id = ?', [$id]);
        return self::$aktuell;
    }

    private static function cookiePfad(): string
    {
        $pfad = parse_url(Config::baseUrl(), PHP_URL_PATH);
        return is_string($pfad) && $pfad !== '' ? rtrim($pfad, '/') . '/' : '/';
    }

    public static function nachToken(string $token): ?array
    {
        return DB::row('SELECT * FROM warenkoerbe WHERE token = ?', [$token]);
    }

    /* ------------------------------------------------------------- Inhalt */

    /**
     * Warenkorb mit allen Beträgen – das, was Shop und Kasse anzeigen.
     *
     * @return array<string,mixed>
     */
    public static function inhalt(?array $korb = null): array
    {
        $korb ??= self::aktueller(false);
        if ($korb === null) {
            return array_merge(
                Preise::rechnen([], []),
                ['token' => '', 'zeilen_probleme' => false, 'email' => '', 'notiz' => '']
            );
        }

        $zeilen   = [];
        $bestaende = [];
        foreach (DB::all('SELECT * FROM warenkorb_zeilen WHERE warenkorb_id = ? ORDER BY id', [(int) $korb['id']]) as $zeile) {
            $variante = Artikel::varianteMitArtikel((int) $zeile['varianten_id']);
            // Verschwundene oder deaktivierte Artikel fallen still heraus,
            // statt den Warenkorb unbenutzbar zu machen.
            if ($variante === null || (string) $variante['artikel_status'] !== 'aktiv') {
                continue;
            }
            $zeilen[] = ['variante' => $variante, 'menge' => (int) $zeile['menge']];
            $bestaende[(int) $variante['id']] = Bestand::verfuegbar($variante);
        }

        $ergebnis = Preise::rechnen($zeilen, [
            'land'          => (string) $korb['land'],
            'rabattcode'    => (string) $korb['rabattcode'],
            'versandart_id' => $korb['versandart_id'] !== null ? (int) $korb['versandart_id'] : 0,
            'kunde_id'      => $korb['kunde_id'] !== null ? (int) $korb['kunde_id'] : null,
            'email'         => (string) $korb['email'],
        ]);

        // Bestandswarnungen an die Positionen hängen: der Kunde soll vor der
        // Kasse sehen, was klemmt, nicht erst beim Bezahlversuch.
        $probleme = false;
        foreach ($ergebnis['positionen'] as &$position) {
            $frei = $bestaende[$position['varianten_id']] ?? null;
            $position['verfuegbar'] = $frei;
            $position['zu_viel']    = $frei !== null && $position['menge'] > $frei;
            if ($position['zu_viel']) {
                $probleme = true;
            }
        }
        unset($position);

        $ergebnis['token']           = (string) $korb['token'];
        $ergebnis['email']           = (string) $korb['email'];
        $ergebnis['notiz']           = (string) $korb['notiz'];
        $ergebnis['zeilen_probleme'] = $probleme;
        return $ergebnis;
    }

    /** Nur die Artikelanzahl – für die Kopfzeile, ohne einen Korb anzulegen. */
    public static function anzahl(): int
    {
        $korb = self::aktueller(false);
        if ($korb === null) {
            return 0;
        }
        return (int) DB::value(
            'SELECT COALESCE(SUM(menge), 0) FROM warenkorb_zeilen WHERE warenkorb_id = ?',
            [(int) $korb['id']],
            0
        );
    }

    /* ----------------------------------------------------------- Verändern */

    /** @throws RuntimeException wenn der Bestand nicht reicht */
    public static function hinzufuegen(int $variantenId, int $menge = 1): void
    {
        $variante = Artikel::varianteMitArtikel($variantenId);
        if ($variante === null) {
            throw new RuntimeException('Dieser Artikel wurde nicht gefunden.');
        }
        if ((string) $variante['artikel_status'] !== 'aktiv') {
            throw new RuntimeException('Dieser Artikel ist derzeit nicht bestellbar.');
        }

        $korb    = self::aktueller();
        $menge   = max(1, min(self::MAX_MENGE, $menge));
        $bisher  = (int) DB::value(
            'SELECT menge FROM warenkorb_zeilen WHERE warenkorb_id = ? AND varianten_id = ?',
            [(int) $korb['id'], $variantenId],
            0
        );
        $gewuenscht = min(self::MAX_MENGE, $bisher + $menge);

        $frei = Bestand::verfuegbar($variante);
        if ($frei !== null && $gewuenscht > $frei) {
            throw new RuntimeException($frei <= 0
                ? 'Dieser Artikel ist leider ausverkauft.'
                : 'Es sind nur noch ' . $frei . ' Stück verfügbar.');
        }

        if ($bisher > 0) {
            DB::run(
                'UPDATE warenkorb_zeilen SET menge = ? WHERE warenkorb_id = ? AND varianten_id = ?',
                [$gewuenscht, (int) $korb['id'], $variantenId]
            );
        } else {
            DB::insert('warenkorb_zeilen', [
                'warenkorb_id' => (int) $korb['id'],
                'varianten_id' => $variantenId,
                'menge'        => $gewuenscht,
            ]);
        }
        self::beruehren((int) $korb['id']);
    }

    public static function mengeSetzen(int $variantenId, int $menge): void
    {
        $korb  = self::aktueller();
        $menge = max(0, min(self::MAX_MENGE, $menge));

        if ($menge === 0) {
            self::entfernen($variantenId);
            return;
        }

        $variante = Artikel::varianteMitArtikel($variantenId);
        if ($variante === null) {
            return;
        }
        $frei = Bestand::verfuegbar($variante);
        if ($frei !== null && $menge > $frei) {
            $menge = max(0, $frei);
            if ($menge === 0) {
                self::entfernen($variantenId);
                return;
            }
        }

        $geaendert = DB::run(
            'UPDATE warenkorb_zeilen SET menge = ? WHERE warenkorb_id = ? AND varianten_id = ?',
            [$menge, (int) $korb['id'], $variantenId]
        )->rowCount();

        if ($geaendert === 0) {
            self::hinzufuegen($variantenId, $menge);
            return;
        }
        self::beruehren((int) $korb['id']);
    }

    public static function entfernen(int $variantenId): void
    {
        $korb = self::aktueller();
        DB::run('DELETE FROM warenkorb_zeilen WHERE warenkorb_id = ? AND varianten_id = ?', [(int) $korb['id'], $variantenId]);
        self::beruehren((int) $korb['id']);
    }

    public static function leeren(?array $korb = null): void
    {
        $korb ??= self::aktueller();
        DB::run('DELETE FROM warenkorb_zeilen WHERE warenkorb_id = ?', [(int) $korb['id']]);
        DB::update('warenkoerbe', (int) $korb['id'], ['rabattcode' => '', 'geaendert' => Util::now()]);
        self::$aktuell = null;
    }

    /** Land, Versandart, Rabattcode, E-Mail oder Notiz setzen. */
    public static function aendern(array $werte, ?array $korb = null): void
    {
        $korb ??= self::aktueller();
        $felder = ['geaendert' => Util::now()];

        if (array_key_exists('land', $werte)) {
            $felder['land'] = mb_strtoupper(mb_substr((string) $werte['land'], 0, 2, 'UTF-8'), 'UTF-8') ?: 'DE';
            // Ein Länderwechsel kann die gewählte Versandart ungültig machen.
            $felder['versandart_id'] = null;
        }
        if (array_key_exists('rabattcode', $werte)) {
            $felder['rabattcode'] = mb_substr(trim((string) $werte['rabattcode']), 0, 60, 'UTF-8');
        }
        if (array_key_exists('versandart_id', $werte)) {
            $id = (int) $werte['versandart_id'];
            $felder['versandart_id'] = $id > 0 ? $id : null;
        }
        if (array_key_exists('email', $werte)) {
            $felder['email'] = mb_substr(Util::normalizeEmail((string) $werte['email']), 0, 190, 'UTF-8');
        }
        if (array_key_exists('notiz', $werte)) {
            $felder['notiz'] = mb_substr((string) $werte['notiz'], 0, 500, 'UTF-8');
        }

        DB::update('warenkoerbe', (int) $korb['id'], $felder);
        self::$aktuell = null;
    }

    private static function beruehren(int $korbId): void
    {
        DB::update('warenkoerbe', $korbId, ['geaendert' => Util::now()]);
        self::$aktuell = null;
    }

    /** Räumt Warenkörbe auf, die seit $tage Tagen niemand angefasst hat. */
    public static function aufraeumen(int $tage = 30): int
    {
        $grenze = date('Y-m-d H:i:s', time() - $tage * 86400);
        $alte   = DB::column('SELECT id FROM warenkoerbe WHERE geaendert < ?', [$grenze]);
        foreach ($alte as $id) {
            DB::run('DELETE FROM warenkorb_zeilen WHERE warenkorb_id = ?', [(int) $id]);
            DB::delete('warenkoerbe', (int) $id);
        }
        return count($alte);
    }
}
