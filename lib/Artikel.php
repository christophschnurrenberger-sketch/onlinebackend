<?php
/**
 * Artikel – Katalogartikel und ihre Varianten.
 *
 * Ein Artikel ist die Sache im Katalog, die Variante die kaufbare Einheit.
 * Preis und Bestand hängen immer an der Variante – auch Artikel ohne Optionen
 * bekommen genau eine Standardvariante. Das erspart im Warenkorb, an der Kasse
 * und in der Bestellung jede Sonderbehandlung.
 */
final class Artikel
{
    public const STATUS = ['entwurf' => 'Entwurf', 'aktiv' => 'Aktiv', 'archiv' => 'Archiviert'];

    /* ---------------------------------------------------------------- Lesen */

    /**
     * @param array<string,mixed> $filter
     * @return array{zeilen:array<int,array<string,mixed>>,gesamt:int}
     */
    public static function liste(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['suche'])) {
            $such     = '%' . $filter['suche'] . '%';
            $where[]  = '(a.titel LIKE ? OR a.handle LIKE ? OR EXISTS
                          (SELECT 1 FROM varianten v WHERE v.artikel_id = a.id AND v.artikelnummer LIKE ?))';
            $params[] = $such;
            $params[] = $such;
            $params[] = $such;
        }
        if (!empty($filter['typ'])) {
            $where[]  = 'a.typ = ?';
            $params[] = $filter['typ'];
        }
        if (!empty($filter['hersteller'])) {
            $where[]  = 'a.hersteller = ?';
            $params[] = $filter['hersteller'];
        }
        if (!empty($filter['kategorie_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM kategorie_artikel k WHERE k.artikel_id = a.id AND k.kategorie_id = ?)';
            $params[] = (int) $filter['kategorie_id'];
        }

        $sortierung = match ((string) ($filter['sortierung'] ?? 'geaendert')) {
            'neueste'    => 'a.erstellt DESC',
            'titel'      => 'a.titel ASC',
            'preis-auf'  => 'preis_min ASC',
            'preis-ab'   => 'preis_min DESC',
            'manuell'    => 'a.position ASC, a.id ASC',
            default      => 'a.geaendert DESC',
        };

        $bedingung = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit     = max(1, min(250, (int) ($filter['limit'] ?? 50)));
        $offset    = max(0, (int) ($filter['offset'] ?? 0));

        $zeilen = DB::all(
            'SELECT a.*,
                    (SELECT MIN(preis) FROM varianten v WHERE v.artikel_id = a.id) AS preis_min,
                    (SELECT MAX(preis) FROM varianten v WHERE v.artikel_id = a.id) AS preis_max,
                    (SELECT COUNT(*) FROM varianten v WHERE v.artikel_id = a.id) AS varianten_anzahl,
                    (SELECT SUM(bestand) FROM varianten v WHERE v.artikel_id = a.id) AS bestand_gesamt,
                    (SELECT url FROM artikel_bilder b WHERE b.artikel_id = a.id ORDER BY b.position, b.id LIMIT 1) AS bild_url
               FROM artikel a ' . $bedingung . '
              ORDER BY ' . $sortierung . '
              LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return [
            'zeilen' => array_map([self::class, 'aufbereiten'], $zeilen),
            'gesamt' => (int) DB::value('SELECT COUNT(*) FROM artikel a ' . $bedingung, $params, 0),
        ];
    }

    private static function aufbereiten(array $row): array
    {
        $row['preis_min']      = (int) ($row['preis_min'] ?? 0);
        $row['preis_max']      = (int) ($row['preis_max'] ?? 0);
        $row['bestand_gesamt'] = (int) ($row['bestand_gesamt'] ?? 0);
        $row['schlagwortliste'] = self::schlagworte((string) ($row['schlagworte'] ?? ''));
        return $row;
    }

    /** @return array<int,string> */
    public static function schlagworte(string $wert): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $wert))));
    }

    /** @return array<string,mixed>|null */
    public static function holen(int $id): ?array
    {
        $artikel = DB::row('SELECT * FROM artikel WHERE id = ?', [$id]);
        return $artikel === null ? null : self::anreichern($artikel);
    }

    /** @return array<string,mixed>|null */
    public static function nachHandle(string $handle): ?array
    {
        $artikel = DB::row('SELECT * FROM artikel WHERE handle = ?', [$handle]);
        return $artikel === null ? null : self::anreichern($artikel);
    }

    private static function anreichern(array $artikel): array
    {
        $id = (int) $artikel['id'];
        $artikel['schlagwortliste'] = self::schlagworte((string) ($artikel['schlagworte'] ?? ''));
        $artikel['bilder']    = DB::all('SELECT * FROM artikel_bilder WHERE artikel_id = ? ORDER BY position, id', [$id]);
        $artikel['optionen']  = array_map(
            static function (array $o): array {
                $o['werteliste'] = json_decode((string) ($o['werte'] ?? '[]'), true) ?: [];
                return $o;
            },
            DB::all('SELECT * FROM artikel_optionen WHERE artikel_id = ? ORDER BY position, id', [$id])
        );
        $artikel['varianten'] = DB::all('SELECT * FROM varianten WHERE artikel_id = ? ORDER BY position, id', [$id]);
        $artikel['kategorien'] = DB::all(
            'SELECT k.id, k.titel, k.handle FROM kategorien k
               JOIN kategorie_artikel ka ON ka.kategorie_id = k.id
              WHERE ka.artikel_id = ? ORDER BY k.titel',
            [$id]
        );
        return $artikel;
    }

    /** @return array<string,mixed>|null */
    public static function variante(int $id): ?array
    {
        return DB::row('SELECT * FROM varianten WHERE id = ?', [$id]);
    }

    /** Variante mit den Artikeldaten, die Warenkorb und Bestellung brauchen. */
    public static function varianteMitArtikel(int $id): ?array
    {
        return DB::row(
            <<<SQL
            SELECT v.*, a.titel AS artikel_titel, a.handle AS artikel_handle, a.status AS artikel_status,
                   COALESCE(NULLIF(v.bild_url, ''),
                            (SELECT url FROM artikel_bilder b WHERE b.artikel_id = a.id
                              ORDER BY b.position, b.id LIMIT 1), '') AS anzeigebild
              FROM varianten v JOIN artikel a ON a.id = v.artikel_id
             WHERE v.id = ?
            SQL,
            [$id]
        );
    }

    /** @return array<int,string> */
    public static function hersteller(): array
    {
        return array_map('strval', DB::column("SELECT DISTINCT hersteller FROM artikel WHERE hersteller != '' ORDER BY hersteller"));
    }

    /** @return array<int,string> */
    public static function typen(): array
    {
        return array_map('strval', DB::column("SELECT DISTINCT typ FROM artikel WHERE typ != '' ORDER BY typ"));
    }

    /** @return array<int,string> */
    public static function alleSchlagworte(): array
    {
        $alle = [];
        foreach (DB::column("SELECT schlagworte FROM artikel WHERE schlagworte != ''") as $wert) {
            foreach (self::schlagworte((string) $wert) as $wort) {
                $alle[$wort] = true;
            }
        }
        $liste = array_keys($alle);
        sort($liste, SORT_NATURAL | SORT_FLAG_CASE);
        return $liste;
    }

    /* ------------------------------------------------------------ Schreiben */

    /** @param array<string,mixed> $daten */
    public static function anlegen(array $daten): int
    {
        return DB::transaction(static function () use ($daten): int {
            $felder = self::felderPruefen($daten);
            $felder['handle'] = Util::handleEindeutig(
                (string) ($daten['handle'] ?? '') ?: $felder['titel'],
                static fn(string $h): bool => DB::value('SELECT COUNT(*) FROM artikel WHERE handle = ?', [$h]) > 0,
                'artikel'
            );
            $felder['erstellt'] = Util::now();

            $id = DB::insert('artikel', $felder);

            self::optionenSetzen($id, $daten['optionen'] ?? []);
            $varianten = $daten['varianten'] ?? [];
            if (!is_array($varianten) || $varianten === []) {
                $varianten = [['titel' => 'Standard', 'preis' => $daten['preis'] ?? 0]];
            }
            self::variantenSetzen($id, $varianten);
            self::bilderSetzen($id, $daten['bilder'] ?? []);

            Log::info('artikel', 'Artikel "' . $felder['titel'] . '" angelegt.');
            return $id;
        });
    }

    /** @param array<string,mixed> $daten */
    public static function speichern(int $id, array $daten): void
    {
        DB::transaction(static function () use ($id, $daten): void {
            $vorher = DB::row('SELECT * FROM artikel WHERE id = ?', [$id]);
            if ($vorher === null) {
                throw new RuntimeException('Artikel nicht gefunden.');
            }

            $felder = self::felderPruefen($daten, $vorher);
            if (isset($daten['handle']) && (string) $daten['handle'] !== (string) $vorher['handle']) {
                $felder['handle'] = Util::handleEindeutig(
                    (string) $daten['handle'] ?: $felder['titel'],
                    static fn(string $h): bool => DB::value('SELECT COUNT(*) FROM artikel WHERE handle = ? AND id != ?', [$h, $id]) > 0,
                    'artikel'
                );
            }
            DB::update('artikel', $id, $felder);

            if (array_key_exists('optionen', $daten)) {
                self::optionenSetzen($id, $daten['optionen']);
            }
            if (array_key_exists('varianten', $daten)) {
                self::variantenSetzen($id, $daten['varianten']);
            }
            if (array_key_exists('bilder', $daten)) {
                self::bilderSetzen($id, $daten['bilder']);
            }
        });
    }

    /** @param array<string,mixed>|null $vorher */
    private static function felderPruefen(array $daten, ?array $vorher = null): array
    {
        $titel = trim((string) ($daten['titel'] ?? $vorher['titel'] ?? ''));
        if ($titel === '') {
            throw new InvalidArgumentException('Bitte einen Titel angeben.');
        }

        $beschreibung = Util::sauberesHtml((string) ($daten['beschreibung'] ?? $vorher['beschreibung'] ?? ''));
        $status = Util::einesVon(
            (string) ($daten['status'] ?? $vorher['status'] ?? 'entwurf'),
            array_keys(self::STATUS),
            'entwurf'
        );

        $schlagworte = $daten['schlagworte'] ?? $vorher['schlagworte'] ?? '';
        if (is_array($schlagworte)) {
            $schlagworte = implode(', ', array_filter(array_map('trim', $schlagworte)));
        }

        $felder = [
            'titel'        => mb_substr($titel, 0, 200, 'UTF-8'),
            'untertitel'   => mb_substr((string) ($daten['untertitel'] ?? $vorher['untertitel'] ?? ''), 0, 250, 'UTF-8'),
            'beschreibung' => $beschreibung,
            'hersteller'   => mb_substr((string) ($daten['hersteller'] ?? $vorher['hersteller'] ?? ''), 0, 120, 'UTF-8'),
            'typ'          => mb_substr((string) ($daten['typ'] ?? $vorher['typ'] ?? ''), 0, 120, 'UTF-8'),
            'schlagworte'  => mb_substr((string) $schlagworte, 0, 500, 'UTF-8'),
            'status'       => $status,
            'seo_titel'    => mb_substr((string) ($daten['seo_titel'] ?? $vorher['seo_titel'] ?? ''), 0, 200, 'UTF-8'),
            'seo_text'     => mb_substr((string) ($daten['seo_text'] ?? $vorher['seo_text'] ?? '') ?: Util::nurText($beschreibung, 160), 0, 400, 'UTF-8'),
            'position'     => (int) ($daten['position'] ?? $vorher['position'] ?? 0),
            'geaendert'    => Util::now(),
        ];

        // aktiv_seit hält fest, seit wann ein Artikel verkäuflich ist – nützlich
        // für "Neuheiten"-Sortierungen und automatische Kategorien.
        if ($status === 'aktiv') {
            $felder['aktiv_seit'] = $vorher['aktiv_seit'] ?? Util::now();
        } else {
            $felder['aktiv_seit'] = null;
        }
        return $felder;
    }

    public static function statusSetzen(int $id, string $status): void
    {
        $status = Util::einesVon($status, array_keys(self::STATUS), 'entwurf');
        $vorher = DB::row('SELECT aktiv_seit FROM artikel WHERE id = ?', [$id]);
        if ($vorher === null) {
            return;
        }
        DB::update('artikel', $id, [
            'status'     => $status,
            'aktiv_seit' => $status === 'aktiv' ? ($vorher['aktiv_seit'] ?? Util::now()) : null,
            'geaendert'  => Util::now(),
        ]);
    }

    public static function loeschen(int $id): void
    {
        DB::transaction(static function () use ($id): void {
            DB::run('DELETE FROM artikel_bilder WHERE artikel_id = ?', [$id]);
            DB::run('DELETE FROM artikel_optionen WHERE artikel_id = ?', [$id]);
            DB::run('DELETE FROM varianten WHERE artikel_id = ?', [$id]);
            DB::run('DELETE FROM kategorie_artikel WHERE artikel_id = ?', [$id]);
            DB::delete('artikel', $id);
        });
    }

    /** Eine Kopie landet immer als Entwurf – ein Klon darf nie sofort live gehen. */
    public static function duplizieren(int $id): int
    {
        $quelle = self::holen($id);
        if ($quelle === null) {
            throw new RuntimeException('Artikel nicht gefunden.');
        }

        $varianten = array_map(static function (array $v): array {
            unset($v['id']);
            $v['artikelnummer'] = '';
            return $v;
        }, $quelle['varianten']);

        return self::anlegen([
            'titel'        => $quelle['titel'] . ' (Kopie)',
            'handle'       => $quelle['handle'] . '-kopie',
            'untertitel'   => $quelle['untertitel'],
            'beschreibung' => $quelle['beschreibung'],
            'hersteller'   => $quelle['hersteller'],
            'typ'          => $quelle['typ'],
            'schlagworte'  => $quelle['schlagworte'],
            'status'       => 'entwurf',
            'optionen'     => $quelle['optionen'],
            'varianten'    => $varianten,
            'bilder'       => $quelle['bilder'],
        ]);
    }

    /* --------------------------------------------------------- Unterobjekte */

    /** @param array<int,mixed> $optionen */
    private static function optionenSetzen(int $artikelId, $optionen): void
    {
        DB::run('DELETE FROM artikel_optionen WHERE artikel_id = ?', [$artikelId]);
        if (!is_array($optionen)) {
            return;
        }
        $position = 1;
        foreach (array_slice($optionen, 0, 3) as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $werte = $option['werteliste'] ?? $option['werte'] ?? [];
            if (is_string($werte)) {
                $werte = array_map('trim', explode(',', $werte));
            }
            $werte = array_values(array_filter(array_map('strval', (array) $werte), static fn($w) => trim($w) !== ''));

            DB::insert('artikel_optionen', [
                'artikel_id' => $artikelId,
                'name'       => mb_substr($name, 0, 80, 'UTF-8'),
                'werte'      => json_encode($werte, JSON_UNESCAPED_UNICODE),
                'position'   => $position++,
            ]);
        }
    }

    /**
     * Varianten werden abgeglichen, nicht gelöscht und neu angelegt: bestehende
     * IDs bleiben erhalten, damit Bestellzeilen und Bestandsbewegungen ihre
     * Referenz behalten.
     *
     * @param array<int,mixed> $varianten
     */
    private static function variantenSetzen(int $artikelId, $varianten): void
    {
        if (!is_array($varianten) || $varianten === []) {
            return;
        }
        $vorhandene = array_map('intval', DB::column('SELECT id FROM varianten WHERE artikel_id = ?', [$artikelId]));
        $behalten   = [];
        $position   = 0;

        foreach (array_slice($varianten, 0, 100) as $roh) {
            if (!is_array($roh)) {
                continue;
            }
            $felder = [
                'artikel_id'      => $artikelId,
                'titel'           => mb_substr(trim((string) ($roh['titel'] ?? 'Standard')) ?: 'Standard', 0, 150, 'UTF-8'),
                'artikelnummer'   => mb_substr((string) ($roh['artikelnummer'] ?? ''), 0, 80, 'UTF-8'),
                'ean'             => mb_substr((string) ($roh['ean'] ?? ''), 0, 80, 'UTF-8'),
                'preis'           => max(0, Util::centAus($roh['preis'] ?? 0)),
                'streichpreis'    => self::centOderNull($roh['streichpreis'] ?? null),
                'einkaufspreis'   => self::centOderNull($roh['einkaufspreis'] ?? null),
                'option1'         => mb_substr((string) ($roh['option1'] ?? ''), 0, 80, 'UTF-8'),
                'option2'         => mb_substr((string) ($roh['option2'] ?? ''), 0, 80, 'UTF-8'),
                'option3'         => mb_substr((string) ($roh['option3'] ?? ''), 0, 80, 'UTF-8'),
                'bestand'         => (int) ($roh['bestand'] ?? 0),
                'bestand_fuehren' => !empty($roh['bestand_fuehren']) ? 1 : 0,
                'ueberverkauf'    => !empty($roh['ueberverkauf']) ? 1 : 0,
                'gewicht_g'       => max(0, (int) ($roh['gewicht_g'] ?? 0)),
                'versandpflicht'  => !empty($roh['versandpflicht']) ? 1 : 0,
                'inhalt_menge'    => max(0, Util::mengeAus($roh['inhalt_menge'] ?? 0)),
                'inhalt_einheit'  => isset(Util::EINHEITEN[(string) ($roh['inhalt_einheit'] ?? '')])
                                     ? (string) $roh['inhalt_einheit'] : '',
                'steuer_id'       => !empty($roh['steuer_id']) ? (int) $roh['steuer_id'] : null,
                'bild_url'        => mb_substr((string) ($roh['bild_url'] ?? ''), 0, 255, 'UTF-8'),
                'position'        => $position++,
                'geaendert'       => Util::now(),
            ];

            $id = (int) ($roh['id'] ?? 0);
            if ($id > 0 && in_array($id, $vorhandene, true)) {
                DB::update('varianten', $id, $felder);
                $behalten[] = $id;
            } else {
                $felder['erstellt'] = Util::now();
                $behalten[] = DB::insert('varianten', $felder);
            }
        }

        foreach (array_diff($vorhandene, $behalten) as $weg) {
            DB::delete('varianten', (int) $weg);
        }
    }

    private static function centOderNull($wert): ?int
    {
        if ($wert === null || $wert === '' || $wert === false) {
            return null;
        }
        return max(0, Util::centAus($wert));
    }

    /** @param array<int,mixed> $bilder */
    private static function bilderSetzen(int $artikelId, $bilder): void
    {
        DB::run('DELETE FROM artikel_bilder WHERE artikel_id = ?', [$artikelId]);
        if (!is_array($bilder)) {
            return;
        }
        $position = 0;
        foreach (array_slice($bilder, 0, 30) as $bild) {
            $url = is_string($bild) ? $bild : (string) ($bild['url'] ?? '');
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            DB::insert('artikel_bilder', [
                'artikel_id' => $artikelId,
                'url'        => mb_substr($url, 0, 255, 'UTF-8'),
                'alt'        => mb_substr((string) (is_array($bild) ? ($bild['alt'] ?? '') : ''), 0, 255, 'UTF-8'),
                'position'   => $position++,
            ]);
        }
    }

    /**
     * Kartesisches Produkt der Optionswerte – die Variantenmatrix.
     *
     * @param array<int,array{name:string,werteliste:array<int,string>}> $optionen
     * @return array<int,array<string,mixed>>
     */
    public static function matrix(array $optionen, array $basis = []): array
    {
        $achsen = [];
        foreach ($optionen as $option) {
            $werte = $option['werteliste'] ?? $option['werte'] ?? [];
            if (is_string($werte)) {
                $werte = array_map('trim', explode(',', $werte));
            }
            $werte = array_values(array_filter((array) $werte, static fn($w) => trim((string) $w) !== ''));
            if ($werte !== []) {
                $achsen[] = $werte;
            }
        }
        if ($achsen === []) {
            return [array_merge($basis, ['titel' => 'Standard'])];
        }

        $kombis = [[]];
        foreach (array_slice($achsen, 0, 3) as $werte) {
            $neu = [];
            foreach ($kombis as $kombi) {
                foreach ($werte as $wert) {
                    $neu[] = array_merge($kombi, [$wert]);
                }
            }
            $kombis = $neu;
        }

        $out = [];
        foreach (array_slice($kombis, 0, 100) as $kombi) {
            $out[] = array_merge($basis, [
                'titel'   => implode(' / ', $kombi),
                'option1' => $kombi[0] ?? '',
                'option2' => $kombi[1] ?? '',
                'option3' => $kombi[2] ?? '',
            ]);
        }
        return $out;
    }
}
