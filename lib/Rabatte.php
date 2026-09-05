<?php
/**
 * Rabatte – Gutscheincodes.
 *
 * Drei Arten decken praktisch alles ab: Prozent, fester Betrag, Gratisversand.
 * Die Gültigkeitsprüfung liegt hier und nicht an der Kasse, damit Warenkorb und
 * Bestellabschluss dieselbe Antwort geben.
 */
final class Rabatte
{
    public const ARTEN = [
        'prozent'      => 'Prozent',
        'betrag'       => 'Fester Betrag',
        'gratisversand' => 'Gratisversand',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function liste(string $suche = ''): array
    {
        $where  = $suche !== '' ? 'WHERE code LIKE ? OR name LIKE ?' : '';
        $params = $suche !== '' ? ['%' . $suche . '%', '%' . $suche . '%'] : [];
        return DB::all('SELECT * FROM rabatte ' . $where . ' ORDER BY id DESC', $params);
    }

    public static function holen(int $id): ?array
    {
        return DB::row('SELECT * FROM rabatte WHERE id = ?', [$id]);
    }

    public static function nachCode(string $code): ?array
    {
        $code = mb_strtoupper(trim($code), 'UTF-8');
        if ($code === '') {
            return null;
        }
        return DB::row('SELECT * FROM rabatte WHERE UPPER(code) = ?', [$code]);
    }

    /** @param array<string,mixed> $daten */
    public static function anlegen(array $daten): int
    {
        $felder = self::felderPruefen($daten);
        if (self::nachCode($felder['code']) !== null) {
            throw new InvalidArgumentException('Diesen Code gibt es bereits.');
        }
        $felder['erstellt'] = Util::now();
        return DB::insert('rabatte', $felder);
    }

    /** @param array<string,mixed> $daten */
    public static function speichern(int $id, array $daten): void
    {
        $vorher = self::holen($id);
        if ($vorher === null) {
            throw new RuntimeException('Rabatt nicht gefunden.');
        }
        $felder   = self::felderPruefen($daten, $vorher);
        $kollision = self::nachCode($felder['code']);
        if ($kollision !== null && (int) $kollision['id'] !== $id) {
            throw new InvalidArgumentException('Diesen Code gibt es bereits.');
        }
        DB::update('rabatte', $id, $felder);
    }

    private static function felderPruefen(array $daten, ?array $vorher = null): array
    {
        $code = mb_strtoupper(preg_replace('/\s+/', '', (string) ($daten['code'] ?? $vorher['code'] ?? '')) ?? '', 'UTF-8');
        if ($code === '') {
            throw new InvalidArgumentException('Bitte einen Rabattcode angeben.');
        }

        $art = Util::einesVon((string) ($daten['art'] ?? $vorher['art'] ?? 'prozent'), array_keys(self::ARTEN), 'prozent');

        // Prozente liegen als Hundertstel-Prozent vor (1250 = 12,5 %), damit
        // auch krumme Sätze exakt gespeichert werden.
        $wert = 0;
        if ($art === 'prozent') {
            $eingabe = (string) ($daten['wert'] ?? '');
            $wert = $eingabe === ''
                ? (int) ($vorher['wert'] ?? 0)
                : (int) round(((float) str_replace(',', '.', $eingabe)) * 100);
            $wert = max(0, min(10000, $wert));
        } elseif ($art === 'betrag') {
            $wert = max(0, Util::centAus($daten['wert'] ?? ($vorher['wert'] ?? 0)));
        }

        $limit = $daten['max_nutzungen'] ?? null;
        $limit = ($limit === null || $limit === '') ? ($vorher['max_nutzungen'] ?? null) : (int) $limit;
        if ($limit !== null && $limit <= 0) {
            $limit = null;
        }

        return [
            'code'             => mb_substr($code, 0, 60, 'UTF-8'),
            'name'             => mb_substr((string) ($daten['name'] ?? $vorher['name'] ?? ''), 0, 200, 'UTF-8'),
            'art'              => $art,
            'wert'             => $wert,
            'gilt_fuer'        => Util::einesVon((string) ($daten['gilt_fuer'] ?? $vorher['gilt_fuer'] ?? 'alles'), ['alles', 'kategorie', 'artikel'], 'alles'),
            'ziele'            => json_encode(array_values(array_filter(array_map('intval', (array) ($daten['ziele'] ?? json_decode((string) ($vorher['ziele'] ?? '[]'), true) ?: []))))),
            'mindestwert'      => max(0, Util::centAus($daten['mindestwert'] ?? ($vorher['mindestwert'] ?? 0))),
            'max_nutzungen'    => $limit,
            'einmal_pro_kunde' => !empty($daten['einmal_pro_kunde']) ? 1 : 0,
            'gilt_ab'          => self::datum($daten['gilt_ab'] ?? null) ?? ($vorher['gilt_ab'] ?? null),
            'gilt_bis'         => self::datum($daten['gilt_bis'] ?? null, true) ?? ($vorher['gilt_bis'] ?? null),
            'aktiv'            => !empty($daten['aktiv']) ? 1 : 0,
            'geaendert'        => Util::now(),
        ];
    }

    private static function datum($wert, bool $tagesende = false): ?string
    {
        $wert = trim((string) $wert);
        if ($wert === '') {
            return null;
        }
        $ts = strtotime($wert);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts) . ($tagesende ? ' 23:59:59' : ' 00:00:00');
    }

    public static function loeschen(int $id): void
    {
        DB::delete('rabatte', $id);
    }

    public static function nutzungZaehlen(int $id): void
    {
        DB::run('UPDATE rabatte SET genutzt = genutzt + 1, geaendert = ? WHERE id = ?', [Util::now(), $id]);
    }

    /**
     * Prüft, ob ein Code auf diesen Warenkorb angewendet werden darf.
     *
     * @return array{ok:bool,grund:string,rabatt:array<string,mixed>|null}
     */
    public static function pruefen(string $code, int $warenwert, ?int $kundeId = null, string $email = ''): array
    {
        $rabatt = self::nachCode($code);
        if ($rabatt === null) {
            return ['ok' => false, 'grund' => 'Dieser Rabattcode ist ungültig.', 'rabatt' => null];
        }
        if ((int) $rabatt['aktiv'] !== 1) {
            return ['ok' => false, 'grund' => 'Dieser Rabattcode ist nicht mehr aktiv.', 'rabatt' => null];
        }
        $jetzt = Util::now();
        if (!empty($rabatt['gilt_ab']) && (string) $rabatt['gilt_ab'] > $jetzt) {
            return ['ok' => false, 'grund' => 'Dieser Rabattcode ist noch nicht gültig.', 'rabatt' => null];
        }
        if (!empty($rabatt['gilt_bis']) && (string) $rabatt['gilt_bis'] < $jetzt) {
            return ['ok' => false, 'grund' => 'Dieser Rabattcode ist abgelaufen.', 'rabatt' => null];
        }
        if ($rabatt['max_nutzungen'] !== null && (int) $rabatt['genutzt'] >= (int) $rabatt['max_nutzungen']) {
            return ['ok' => false, 'grund' => 'Dieser Rabattcode wurde bereits vollständig eingelöst.', 'rabatt' => null];
        }
        if ((int) $rabatt['mindestwert'] > 0 && $warenwert < (int) $rabatt['mindestwert']) {
            return [
                'ok'     => false,
                'grund'  => 'Der Mindestbestellwert von ' . Util::geld((int) $rabatt['mindestwert']) . ' ist nicht erreicht.',
                'rabatt' => null,
            ];
        }
        if ((int) $rabatt['einmal_pro_kunde'] === 1) {
            $schonGenutzt = $kundeId !== null
                ? DB::value("SELECT COUNT(*) FROM bestellungen WHERE kunde_id = ? AND UPPER(rabattcode) = ? AND status != 'storniert'", [$kundeId, $rabatt['code']])
                : ($email !== '' ? DB::value("SELECT COUNT(*) FROM bestellungen WHERE email = ? AND UPPER(rabattcode) = ? AND status != 'storniert'", [Util::normalizeEmail($email), $rabatt['code']]) : 0);
            if ((int) $schonGenutzt > 0) {
                return ['ok' => false, 'grund' => 'Diesen Rabattcode hast du bereits eingelöst.', 'rabatt' => null];
            }
        }
        return ['ok' => true, 'grund' => '', 'rabatt' => $rabatt];
    }

    public static function wertText(array $rabatt): string
    {
        return match ((string) $rabatt['art']) {
            'prozent' => rtrim(rtrim(number_format((int) $rabatt['wert'] / 100, 2, ',', ''), '0'), ',') . ' %',
            'betrag'  => Util::geld((int) $rabatt['wert']),
            default   => 'Gratisversand',
        };
    }
}
