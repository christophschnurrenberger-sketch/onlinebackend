<?php
/**
 * Versand – Zonen und Versandarten.
 *
 * Eine Zone bündelt Länder, ihre Versandarten gelten für alle davon. Eine Zone
 * OHNE Länderliste ist die Auffangzone für alle übrigen Länder – ohne sie kann
 * aus nicht aufgeführten Ländern niemand bestellen.
 */
final class Versand
{
    /** @return array<int,array<string,mixed>> Zonen samt ihrer Versandarten. */
    public static function zonen(): array
    {
        $zonen = DB::all('SELECT * FROM versandzonen ORDER BY position, id');
        foreach ($zonen as &$zone) {
            $zone['laenderliste'] = self::laenderListe((string) $zone['laender']);
            $zone['arten'] = DB::all(
                'SELECT * FROM versandarten WHERE zone_id = ? ORDER BY position, preis',
                [(int) $zone['id']]
            );
        }
        return $zonen;
    }

    /** @return array<int,string> */
    public static function laenderListe(string $wert): array
    {
        $codes = array_map(
            static fn($c) => mb_strtoupper(trim((string) $c), 'UTF-8'),
            explode(',', $wert)
        );
        return array_values(array_filter($codes, static fn($c) => preg_match('/^[A-Z]{2}$/', $c) === 1));
    }

    public static function zoneAnlegen(string $name, array $laender, array $arten = []): int
    {
        $id = DB::insert('versandzonen', [
            'name'     => mb_substr(trim($name), 0, 120, 'UTF-8') ?: 'Zone',
            'laender'  => implode(',', self::laenderListe(implode(',', $laender))),
            'position' => (int) DB::value('SELECT COUNT(*) FROM versandzonen', [], 0),
        ]);
        foreach ($arten as $art) {
            self::artAnlegen($id, $art);
        }
        return $id;
    }

    public static function zoneSpeichern(int $id, string $name, array $laender, ?array $arten = null): void
    {
        DB::transaction(static function () use ($id, $name, $laender, $arten): void {
            DB::update('versandzonen', $id, [
                'name'    => mb_substr(trim($name), 0, 120, 'UTF-8') ?: 'Zone',
                'laender' => implode(',', self::laenderListe(implode(',', $laender))),
            ]);
            if ($arten !== null) {
                DB::run('DELETE FROM versandarten WHERE zone_id = ?', [$id]);
                foreach ($arten as $art) {
                    self::artAnlegen($id, $art);
                }
            }
        });
    }

    public static function zoneLoeschen(int $id): void
    {
        DB::transaction(static function () use ($id): void {
            DB::run('DELETE FROM versandarten WHERE zone_id = ?', [$id]);
            DB::delete('versandzonen', $id);
        });
    }

    /** @param array<string,mixed> $art */
    public static function artAnlegen(int $zoneId, array $art): int
    {
        $name = trim((string) ($art['name'] ?? ''));
        if ($name === '') {
            return 0;
        }
        return DB::insert('versandarten', [
            'zone_id'    => $zoneId,
            'name'       => mb_substr($name, 0, 120, 'UTF-8'),
            'hinweis'    => mb_substr((string) ($art['hinweis'] ?? ''), 0, 250, 'UTF-8'),
            'preis'      => max(0, Util::centAus($art['preis'] ?? 0)),
            'ab_wert'    => self::centOderNull($art['ab_wert'] ?? null),
            'bis_wert'   => self::centOderNull($art['bis_wert'] ?? null),
            'frei_ab'    => self::centOderNull($art['frei_ab'] ?? null),
            'lieferzeit' => mb_substr((string) ($art['lieferzeit'] ?? ''), 0, 80, 'UTF-8'),
            'position'   => (int) ($art['position'] ?? 0),
        ]);
    }

    private static function centOderNull($wert): ?int
    {
        if ($wert === null || $wert === '' || $wert === false) {
            return null;
        }
        return max(0, Util::centAus($wert));
    }

    public static function art(?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        return DB::row('SELECT * FROM versandarten WHERE id = ?', [$id]);
    }

    /**
     * Die für ein Land und einen Warenwert wählbaren Versandarten – jeweils mit
     * dem tatsächlich fälligen Preis, also mit eingerechneter
     * Versandkostenfreiheit.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function arten(string $land, int $warenwert, bool $versandnoetig = true): array
    {
        if (!$versandnoetig) {
            return [[
                'id' => 0, 'name' => 'Kein Versand nötig', 'hinweis' => '',
                'preis' => 0, 'lieferzeit' => '', 'endpreis' => 0, 'frei' => false,
            ]];
        }

        $land   = mb_strtoupper($land, 'UTF-8');
        $zonen  = self::zonen();
        $treffer = null;
        $auffang = null;

        foreach ($zonen as $zone) {
            if ($zone['laenderliste'] === []) {
                $auffang ??= $zone;
            } elseif (in_array($land, $zone['laenderliste'], true)) {
                $treffer = $zone;
                break;
            }
        }
        $zone = $treffer ?? $auffang;
        if ($zone === null) {
            return [];
        }

        $out = [];
        foreach ($zone['arten'] as $art) {
            $abWert  = $art['ab_wert'] === null ? null : (int) $art['ab_wert'];
            $bisWert = $art['bis_wert'] === null ? null : (int) $art['bis_wert'];
            if ($abWert !== null && $warenwert < $abWert) {
                continue;
            }
            if ($bisWert !== null && $warenwert > $bisWert) {
                continue;
            }
            $freiAb = $art['frei_ab'] === null ? null : (int) $art['frei_ab'];
            $frei   = $freiAb !== null && $warenwert >= $freiAb;

            $art['endpreis'] = $frei ? 0 : (int) $art['preis'];
            $art['frei']     = $frei;
            $out[] = $art;
        }

        usort($out, static fn($a, $b) => [$a['position'], $a['endpreis']] <=> [$b['position'], $b['endpreis']]);
        return $out;
    }

    /** Alle belieferbaren Länder; leer bedeutet "keine Einschränkung". */
    public static function laender(): array
    {
        $zonen = self::zonen();
        foreach ($zonen as $zone) {
            if ($zone['laenderliste'] === []) {
                return [];
            }
        }
        $alle = [];
        foreach ($zonen as $zone) {
            foreach ($zone['laenderliste'] as $code) {
                $alle[$code] = true;
            }
        }
        $liste = array_keys($alle);
        sort($liste);
        return $liste;
    }

    /** Länder mit Namen für Auswahlfelder. */
    public const LAENDERNAMEN = [
        'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz',
        'NL' => 'Niederlande', 'BE' => 'Belgien', 'LU' => 'Luxemburg',
        'FR' => 'Frankreich', 'IT' => 'Italien', 'ES' => 'Spanien',
        'PL' => 'Polen', 'CZ' => 'Tschechien', 'DK' => 'Dänemark',
        'SE' => 'Schweden', 'FI' => 'Finnland', 'PT' => 'Portugal',
        'IE' => 'Irland', 'GB' => 'Großbritannien', 'US' => 'USA',
    ];

    public static function landName(string $code): string
    {
        return self::LAENDERNAMEN[mb_strtoupper($code, 'UTF-8')] ?? $code;
    }
}
