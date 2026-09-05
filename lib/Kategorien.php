<?php
/**
 * Kategorien.
 *
 * Zwei Sorten, wie bei den großen Shopsystemen:
 *   manuell      – der Betreiber wählt Artikel und Reihenfolge selbst
 *   automatisch  – Regeln (Schlagwort, Typ, Hersteller, Titel, Preis) bestimmen
 *                  die Mitglieder
 *
 * Automatische Kategorien werden beim Lesen ausgewertet, nicht gespeichert: ein
 * neu angelegter Artikel ist damit sofort in der richtigen Kategorie.
 */
final class Kategorien
{
    public const FELDER = [
        'schlagwort' => 'Schlagwort',
        'typ'        => 'Produkttyp',
        'hersteller' => 'Hersteller',
        'titel'      => 'Titel',
        'preis'      => 'Preis',
    ];

    public const OPERATOREN = [
        'ist'          => 'ist gleich',
        'ist_nicht'    => 'ist nicht',
        'enthaelt'     => 'enthält',
        'beginnt_mit'  => 'beginnt mit',
        'groesser'     => 'ist größer als',
        'kleiner'      => 'ist kleiner als',
    ];

    public const SORTIERUNGEN = [
        'manuell'    => 'Manuell',
        'titel'      => 'Titel A–Z',
        'titel-ab'   => 'Titel Z–A',
        'preis-auf'  => 'Preis aufsteigend',
        'preis-ab'   => 'Preis absteigend',
        'neueste'    => 'Neueste zuerst',
    ];

    /* ---------------------------------------------------------------- Lesen */

    /** @return array<int,array<string,mixed>> */
    public static function liste(?bool $nurSichtbare = null, string $suche = ''): array
    {
        $where  = [];
        $params = [];
        if ($nurSichtbare !== null) {
            $where[]  = 'sichtbar = ?';
            $params[] = $nurSichtbare ? 1 : 0;
        }
        if ($suche !== '') {
            $where[]  = '(titel LIKE ? OR handle LIKE ?)';
            $params[] = '%' . $suche . '%';
            $params[] = '%' . $suche . '%';
        }
        $bedingung = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return array_map(
            [self::class, 'anreichern'],
            DB::all('SELECT * FROM kategorien ' . $bedingung . ' ORDER BY position, titel', $params)
        );
    }

    private static function anreichern(array $row): array
    {
        $row['regelliste'] = json_decode((string) ($row['regeln'] ?? '[]'), true) ?: [];
        $row['anzahl']     = self::anzahl($row);
        return $row;
    }

    public static function holen(int $id): ?array
    {
        $row = DB::row('SELECT * FROM kategorien WHERE id = ?', [$id]);
        return $row === null ? null : self::anreichern($row);
    }

    public static function nachHandle(string $handle): ?array
    {
        $row = DB::row('SELECT * FROM kategorien WHERE handle = ?', [$handle]);
        return $row === null ? null : self::anreichern($row);
    }

    /* ------------------------------------------------------------ Schreiben */

    /** @param array<string,mixed> $daten */
    public static function anlegen(array $daten): int
    {
        return DB::transaction(static function () use ($daten): int {
            $felder = self::felderPruefen($daten);
            $felder['handle'] = Util::handleEindeutig(
                (string) ($daten['handle'] ?? '') ?: $felder['titel'],
                static fn(string $h): bool => DB::value('SELECT COUNT(*) FROM kategorien WHERE handle = ?', [$h]) > 0,
                'kategorie'
            );
            $felder['erstellt'] = Util::now();

            $id = DB::insert('kategorien', $felder);
            if (isset($daten['artikel_ids']) && is_array($daten['artikel_ids'])) {
                self::artikelSetzen($id, $daten['artikel_ids']);
            }
            return $id;
        });
    }

    /** @param array<string,mixed> $daten */
    public static function speichern(int $id, array $daten): void
    {
        DB::transaction(static function () use ($id, $daten): void {
            $vorher = DB::row('SELECT * FROM kategorien WHERE id = ?', [$id]);
            if ($vorher === null) {
                throw new RuntimeException('Kategorie nicht gefunden.');
            }
            $felder = self::felderPruefen($daten, $vorher);
            if (isset($daten['handle']) && (string) $daten['handle'] !== (string) $vorher['handle']) {
                $felder['handle'] = Util::handleEindeutig(
                    (string) $daten['handle'] ?: $felder['titel'],
                    static fn(string $h): bool => DB::value('SELECT COUNT(*) FROM kategorien WHERE handle = ? AND id != ?', [$h, $id]) > 0,
                    'kategorie'
                );
            }
            DB::update('kategorien', $id, $felder);

            if (isset($daten['artikel_ids']) && is_array($daten['artikel_ids'])) {
                self::artikelSetzen($id, $daten['artikel_ids']);
            }
        });
    }

    private static function felderPruefen(array $daten, ?array $vorher = null): array
    {
        $titel = trim((string) ($daten['titel'] ?? $vorher['titel'] ?? ''));
        if ($titel === '') {
            throw new InvalidArgumentException('Bitte einen Titel angeben.');
        }
        $beschreibung = Util::sauberesHtml((string) ($daten['beschreibung'] ?? $vorher['beschreibung'] ?? ''));

        return [
            'titel'        => mb_substr($titel, 0, 200, 'UTF-8'),
            'beschreibung' => $beschreibung,
            'bild_url'     => mb_substr((string) ($daten['bild_url'] ?? $vorher['bild_url'] ?? ''), 0, 255, 'UTF-8'),
            'art'          => Util::einesVon((string) ($daten['art'] ?? $vorher['art'] ?? 'manuell'), ['manuell', 'automatisch'], 'manuell'),
            'regeln'       => json_encode(self::regelnPruefen($daten['regeln'] ?? json_decode((string) ($vorher['regeln'] ?? '[]'), true) ?: []), JSON_UNESCAPED_UNICODE),
            'regel_modus'  => Util::einesVon((string) ($daten['regel_modus'] ?? $vorher['regel_modus'] ?? 'alle'), ['alle', 'eine'], 'alle'),
            'sortierung'   => Util::einesVon((string) ($daten['sortierung'] ?? $vorher['sortierung'] ?? 'manuell'), array_keys(self::SORTIERUNGEN), 'manuell'),
            'sichtbar'     => !empty($daten['sichtbar']) ? 1 : 0,
            'position'     => (int) ($daten['position'] ?? $vorher['position'] ?? 0),
            'seo_titel'    => mb_substr((string) ($daten['seo_titel'] ?? $vorher['seo_titel'] ?? ''), 0, 200, 'UTF-8'),
            'seo_text'     => mb_substr((string) ($daten['seo_text'] ?? $vorher['seo_text'] ?? '') ?: Util::nurText($beschreibung, 160), 0, 400, 'UTF-8'),
            'geaendert'    => Util::now(),
        ];
    }

    /** @return array<int,array{feld:string,operator:string,wert:string}> */
    private static function regelnPruefen($regeln): array
    {
        if (!is_array($regeln)) {
            return [];
        }
        $out = [];
        foreach (array_slice($regeln, 0, 10) as $regel) {
            if (!is_array($regel)) {
                continue;
            }
            $wert = trim((string) ($regel['wert'] ?? ''));
            if ($wert === '') {
                continue;
            }
            $out[] = [
                'feld'     => Util::einesVon((string) ($regel['feld'] ?? 'schlagwort'), array_keys(self::FELDER), 'schlagwort'),
                'operator' => Util::einesVon((string) ($regel['operator'] ?? 'ist'), array_keys(self::OPERATOREN), 'ist'),
                'wert'     => mb_substr($wert, 0, 120, 'UTF-8'),
            ];
        }
        return $out;
    }

    public static function loeschen(int $id): void
    {
        DB::transaction(static function () use ($id): void {
            DB::run('DELETE FROM kategorie_artikel WHERE kategorie_id = ?', [$id]);
            DB::delete('kategorien', $id);
        });
    }

    /** @param array<int,mixed> $artikelIds */
    public static function artikelSetzen(int $kategorieId, array $artikelIds): void
    {
        DB::run('DELETE FROM kategorie_artikel WHERE kategorie_id = ?', [$kategorieId]);
        $position = 0;
        foreach (array_slice($artikelIds, 0, 2000) as $artikelId) {
            $artikelId = (int) $artikelId;
            if ($artikelId <= 0) {
                continue;
            }
            try {
                DB::insert('kategorie_artikel', [
                    'kategorie_id' => $kategorieId,
                    'artikel_id'   => $artikelId,
                    'position'     => $position++,
                ]);
            } catch (Throwable $e) {
                // Doppelte Zuordnung ist unschädlich.
            }
        }
    }

    public static function artikelHinzufuegen(int $kategorieId, int $artikelId): void
    {
        $vorhanden = DB::value(
            'SELECT COUNT(*) FROM kategorie_artikel WHERE kategorie_id = ? AND artikel_id = ?',
            [$kategorieId, $artikelId]
        );
        if ((int) $vorhanden > 0) {
            return;
        }
        $position = (int) DB::value('SELECT MAX(position) FROM kategorie_artikel WHERE kategorie_id = ?', [$kategorieId], -1) + 1;
        DB::insert('kategorie_artikel', [
            'kategorie_id' => $kategorieId,
            'artikel_id'   => $artikelId,
            'position'     => $position,
        ]);
    }

    public static function artikelEntfernen(int $kategorieId, int $artikelId): void
    {
        DB::run('DELETE FROM kategorie_artikel WHERE kategorie_id = ? AND artikel_id = ?', [$kategorieId, $artikelId]);
    }

    /* --------------------------------------------------------- Regelauswertung */

    /**
     * Artikel einer Kategorie. $nurAktive ist für den Shop gedacht – im Backend
     * will man auch die Entwürfe sehen.
     *
     * @param array<string,mixed> $kategorie
     * @return array<int,array<string,mixed>>
     */
    public static function artikel(array $kategorie, bool $nurAktive = true, int $limit = 500, int $offset = 0): array
    {
        $where  = [];
        $params = [];
        $join   = '';

        if ($nurAktive) {
            $where[] = "a.status = 'aktiv'";
        }

        $sortierung = self::sortierungSql((string) ($kategorie['sortierung'] ?? 'manuell'));

        if (($kategorie['art'] ?? 'manuell') === 'automatisch') {
            $regeln = $kategorie['regelliste'] ?? json_decode((string) ($kategorie['regeln'] ?? '[]'), true) ?: [];
            if ($regeln === []) {
                // Eine automatische Kategorie ohne Regeln ist absichtlich leer,
                // nicht "alles" – sonst wäre ein Tippfehler fatal.
                return [];
            }
            $teile = [];
            foreach ($regeln as $regel) {
                [$sql, $regelParams] = self::regelSql($regel);
                $teile[] = $sql;
                $params  = array_merge($params, $regelParams);
            }
            $verbinder = ($kategorie['regel_modus'] ?? 'alle') === 'eine' ? ' OR ' : ' AND ';
            $where[]   = '(' . implode($verbinder, $teile) . ')';

            if ($sortierung === 'ka.position ASC, a.id ASC') {
                $sortierung = 'a.titel ASC';
            }
        } else {
            $join   = 'JOIN kategorie_artikel ka ON ka.artikel_id = a.id AND ka.kategorie_id = ?';
            $params = array_merge([(int) $kategorie['id']], $params);
        }

        $bedingung = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit     = max(1, min(1000, $limit));
        $offset    = max(0, $offset);

        return DB::all(
            'SELECT a.*,
                    (SELECT MIN(preis) FROM varianten v WHERE v.artikel_id = a.id) AS preis_min,
                    (SELECT MAX(preis) FROM varianten v WHERE v.artikel_id = a.id) AS preis_max,
                    (SELECT MAX(COALESCE(streichpreis,0)) FROM varianten v WHERE v.artikel_id = a.id) AS streichpreis_max,
                    (SELECT SUM(bestand) FROM varianten v WHERE v.artikel_id = a.id) AS bestand_gesamt,
                    (SELECT url FROM artikel_bilder b WHERE b.artikel_id = a.id ORDER BY b.position, b.id LIMIT 1) AS bild_url
               FROM artikel a ' . $join . ' ' . $bedingung . '
              ORDER BY ' . $sortierung . '
              LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    private static function sortierungSql(string $sortierung): string
    {
        return match ($sortierung) {
            'titel'     => 'a.titel ASC',
            'titel-ab'  => 'a.titel DESC',
            'preis-auf' => '(SELECT MIN(preis) FROM varianten v WHERE v.artikel_id = a.id) ASC',
            'preis-ab'  => '(SELECT MIN(preis) FROM varianten v WHERE v.artikel_id = a.id) DESC',
            'neueste'   => 'a.erstellt DESC',
            default     => 'ka.position ASC, a.id ASC',
        };
    }

    /**
     * Übersetzt eine Regel in ein SQL-Fragment.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function regelSql(array $regel): array
    {
        $feld     = (string) ($regel['feld'] ?? 'schlagwort');
        $operator = (string) ($regel['operator'] ?? 'ist');
        $wert     = (string) ($regel['wert'] ?? '');

        if ($feld === 'preis') {
            $cent   = Util::centAus($wert);
            $spalte = '(SELECT MIN(preis) FROM varianten v WHERE v.artikel_id = a.id)';
            $op     = match ($operator) {
                'groesser'  => '>',
                'kleiner'   => '<',
                'ist_nicht' => '!=',
                default     => '=',
            };
            return [$spalte . ' ' . $op . ' ?', [$cent]];
        }

        $spalte = match ($feld) {
            'typ'        => 'a.typ',
            'hersteller' => 'a.hersteller',
            'titel'      => 'a.titel',
            default      => 'a.schlagworte',
        };

        return match ($operator) {
            'ist_nicht'   => [$spalte . ' != ?', [$wert]],
            'enthaelt'    => [$spalte . ' LIKE ?', ['%' . $wert . '%']],
            'beginnt_mit' => [$spalte . ' LIKE ?', [$wert . '%']],
            // Schlagworte sind eine kommagetrennte Liste: "ist" meint
            // "enthält dieses Schlagwort".
            default => $feld === 'schlagwort'
                ? [self::schlagwortSql(), ['%,' . $wert . ',%']]
                : [$spalte . ' = ?', [$wert]],
        };
    }

    /**
     * Schlagwörter liegen als kommagetrennte Liste in einer Spalte. Für den
     * Vergleich wird sie in ",wort,wort," umgeschlossen – SQLite verkettet mit
     * ||, MySQL braucht CONCAT().
     */
    private static function schlagwortSql(): string
    {
        return DB::isSqlite()
            ? "(',' || REPLACE(a.schlagworte, ', ', ',') || ',') LIKE ?"
            : "CONCAT(',', REPLACE(a.schlagworte, ', ', ','), ',') LIKE ?";
    }

    private static function anzahl(array $row): int
    {
        if (($row['art'] ?? 'manuell') === 'manuell') {
            return (int) DB::value('SELECT COUNT(*) FROM kategorie_artikel WHERE kategorie_id = ?', [(int) $row['id']], 0);
        }
        $row['regelliste'] = json_decode((string) ($row['regeln'] ?? '[]'), true) ?: [];
        return count(self::artikel($row, false, 1000));
    }
}
