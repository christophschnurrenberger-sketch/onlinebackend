<?php
/**
 * Veröffentlichung – der "Push ins Frontend".
 *
 * Ein Klick friert den kompletten veröffentlichungsfähigen Zustand als
 * JSON-Fassung ein. Der Shop liest ausschließlich aus der aktuell live
 * geschalteten Fassung, nie aus den Arbeitstabellen.
 *
 * Vier praktische Folgen:
 *   1. Entwürfe sind sicher – man kann tagelang an einem Artikel arbeiten,
 *      ohne dass Kunden Halbfertiges sehen.
 *   2. Veröffentlichen wird ein Ereignis mit Datum, Version und Urheber.
 *   3. Rollback ist ein einziges UPDATE.
 *   4. Der Shop ist schnell – Katalogseiten kommen aus einer fertigen Struktur.
 *
 * Bewusst NICHT über die Fassung laufen: Bestände, Warenkörbe, Bestellungen und
 * Rabattzähler. Die müssen sofort wirken – ein ausverkaufter Artikel darf nicht
 * bis zur nächsten Veröffentlichung weiterverkauft werden.
 */
final class Veroeffentlichung
{
    /** @var array<string,mixed>|null */
    private static ?array $liveCache = null;

    /* ------------------------------------------------------------ Erzeugen */

    /** Baut die Fassung aus dem aktuellen Backend-Zustand – ohne sie zu speichern. */
    public static function bauen(): array
    {
        $artikel = [];
        foreach (Artikel::liste(['status' => 'aktiv', 'limit' => 250, 'sortierung' => 'manuell'])['zeilen'] as $kurz) {
            $voll = Artikel::holen((int) $kurz['id']);
            if ($voll === null) {
                continue;
            }
            $preise = array_map(static fn($v) => (int) $v['preis'], $voll['varianten']);
            $streich = array_map(static fn($v) => (int) ($v['streichpreis'] ?? 0), $voll['varianten']);

            $artikel[] = [
                'id'            => (int) $voll['id'],
                'handle'        => (string) $voll['handle'],
                'titel'         => (string) $voll['titel'],
                'untertitel'    => (string) $voll['untertitel'],
                'beschreibung'  => (string) $voll['beschreibung'],
                'hersteller'    => (string) $voll['hersteller'],
                'typ'           => (string) $voll['typ'],
                'schlagworte'   => $voll['schlagwortliste'],
                'seo_titel'     => (string) $voll['seo_titel'],
                'seo_text'      => (string) $voll['seo_text'],
                'aktiv_seit'    => (string) ($voll['aktiv_seit'] ?? ''),
                'bilder'        => array_map(
                    static fn($b) => ['url' => (string) $b['url'], 'alt' => (string) $b['alt']],
                    $voll['bilder']
                ),
                'optionen'      => array_map(
                    static fn($o) => ['name' => (string) $o['name'], 'werte' => $o['werteliste']],
                    $voll['optionen']
                ),
                // Bestände wandern bewusst NICHT in die Fassung – die
                // Verfügbarkeit liest der Shop direkt aus der Datenbank.
                'varianten'     => array_map(static fn($v) => [
                    'id'           => (int) $v['id'],
                    'titel'        => (string) $v['titel'],
                    'artikelnummer' => (string) $v['artikelnummer'],
                    'preis'        => (int) $v['preis'],
                    'streichpreis' => $v['streichpreis'] !== null ? (int) $v['streichpreis'] : null,
                    'option1'      => (string) $v['option1'],
                    'option2'      => (string) $v['option2'],
                    'option3'      => (string) $v['option3'],
                    'bild_url'     => (string) $v['bild_url'],
                    'inhalt_menge'   => (int) ($v['inhalt_menge'] ?? 0),
                    'inhalt_einheit' => (string) ($v['inhalt_einheit'] ?? ''),
                ], $voll['varianten']),
                'preis_min'     => $preise === [] ? 0 : min($preise),
                'preis_max'     => $preise === [] ? 0 : max($preise),
                'streich_max'   => $streich === [] ? 0 : max($streich),
            ];
        }

        $artikelIds = array_column($artikel, 'id');

        $kategorien = [];
        foreach (Kategorien::liste(true) as $kategorie) {
            // Regeln werden beim Veröffentlichen ausgewertet und als feste
            // Liste abgelegt: der Shop muss nie Regeln auswerten, und der
            // Betreiber sieht genau das, was er in der Vorschau hatte.
            $ids = array_values(array_intersect(
                array_map(static fn($a) => (int) $a['id'], Kategorien::artikel($kategorie, true, 1000)),
                $artikelIds
            ));
            $kategorien[] = [
                'id'           => (int) $kategorie['id'],
                'handle'       => (string) $kategorie['handle'],
                'titel'        => (string) $kategorie['titel'],
                'beschreibung' => (string) $kategorie['beschreibung'],
                'bild_url'     => (string) $kategorie['bild_url'],
                'sortierung'   => (string) $kategorie['sortierung'],
                'seo_titel'    => (string) $kategorie['seo_titel'],
                'seo_text'     => (string) $kategorie['seo_text'],
                'artikel_ids'  => $ids,
            ];
        }

        return [
            'erzeugt'     => Util::now(),
            'einstellungen' => Settings::all(true),
            'artikel'     => $artikel,
            'kategorien'  => $kategorien,
            'seiten'      => array_map([self::class, 'inhaltKurz'], Inhalte::seiten(true)),
            'beitraege'   => array_map(static function (array $b): array {
                $kurz = self::inhaltKurz($b);
                $kurz['anriss']        = (string) $b['anriss'];
                $kurz['bild_url']      = (string) $b['bild_url'];
                $kurz['autor']         = (string) $b['autor'];
                $kurz['sichtbar_seit'] = (string) ($b['sichtbar_seit'] ?? '');
                return $kurz;
            }, Inhalte::beitraege(true)),
            'menues'      => [
                'haupt'  => self::menuKurz(Inhalte::menue('haupt')),
                'fuss'   => self::menuKurz(Inhalte::menue('fuss')),
            ],
        ];
    }

    private static function inhaltKurz(array $row): array
    {
        return [
            'id'        => (int) $row['id'],
            'handle'    => (string) $row['handle'],
            'titel'     => (string) $row['titel'],
            'inhalt'    => (string) $row['inhalt'],
            'seo_titel' => (string) $row['seo_titel'],
            'seo_text'  => (string) $row['seo_text'],
        ];
    }

    private static function menuKurz(array $punkte): array
    {
        return array_map(static fn($p) => [
            'label'  => (string) $p['label'],
            'url'    => (string) $p['url'],
            'kinder' => array_map(
                static fn($k) => ['label' => (string) $k['label'], 'url' => (string) $k['url']],
                $p['kinder'] ?? []
            ),
        ], $punkte);
    }

    /* -------------------------------------------------------- Veröffentlichen */

    /** @return array{version:int,kennzahlen:array<string,int>} */
    public static function veroeffentlichen(string $notiz = '', ?int $benutzerId = null): array
    {
        return DB::transaction(static function () use ($notiz, $benutzerId): array {
            $fassung = self::bauen();
            $kennzahlen = [
                'artikel'    => count($fassung['artikel']),
                'kategorien' => count($fassung['kategorien']),
                'seiten'     => count($fassung['seiten']),
                'beitraege'  => count($fassung['beitraege']),
            ];
            $version = (int) DB::value('SELECT MAX(version) FROM veroeffentlichungen', [], 0) + 1;

            DB::run('UPDATE veroeffentlichungen SET live = 0 WHERE live = 1');
            DB::insert('veroeffentlichungen', [
                'version'     => $version,
                'notiz'       => mb_substr($notiz, 0, 500, 'UTF-8'),
                'inhalt'      => json_encode($fassung, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'kennzahlen'  => json_encode($kennzahlen),
                'live'        => 1,
                'benutzer_id' => $benutzerId,
                'erstellt'    => Util::now(),
            ]);

            Settings::setMany([
                'zuletzt_veroeffentlicht' => $fassung['erzeugt'],
                'live_version'            => (string) $version,
            ]);

            self::$liveCache = $fassung;
            Log::info('veroeffentlichung', 'Fassung ' . $version . ' veröffentlicht ('
                . $kennzahlen['artikel'] . ' Artikel).');

            return ['version' => $version, 'kennzahlen' => $kennzahlen];
        });
    }

    /** Die aktuell live geschaltete Fassung, oder null wenn nie veröffentlicht. */
    public static function live(): ?array
    {
        if (self::$liveCache !== null) {
            return self::$liveCache;
        }
        $zeile = DB::row('SELECT inhalt FROM veroeffentlichungen WHERE live = 1 ORDER BY version DESC LIMIT 1');
        if ($zeile === null) {
            return null;
        }
        $fassung = json_decode((string) $zeile['inhalt'], true);
        if (!is_array($fassung)) {
            return null;
        }
        self::$liveCache = $fassung;
        return $fassung;
    }

    public static function liveVersion(): int
    {
        return (int) DB::value('SELECT version FROM veroeffentlichungen WHERE live = 1 ORDER BY version DESC LIMIT 1', [], 0);
    }

    /** @return array<int,array<string,mixed>> */
    public static function fassungen(int $limit = 30): array
    {
        $zeilen = DB::all(
            'SELECT v.id, v.version, v.notiz, v.kennzahlen, v.live, v.erstellt, u.name AS benutzer_name
               FROM veroeffentlichungen v LEFT JOIN benutzer u ON u.id = v.benutzer_id
              ORDER BY v.version DESC LIMIT ' . max(1, min(100, $limit))
        );
        foreach ($zeilen as &$zeile) {
            $zeile['zahlen'] = json_decode((string) $zeile['kennzahlen'], true) ?: [];
        }
        return $zeilen;
    }

    /** Rollback: eine frühere Fassung wieder live schalten. */
    public static function zurueck(int $version): void
    {
        DB::transaction(static function () use ($version): void {
            $zeile = DB::row('SELECT * FROM veroeffentlichungen WHERE version = ?', [$version]);
            if ($zeile === null) {
                throw new RuntimeException('Diese Fassung gibt es nicht.');
            }
            DB::run('UPDATE veroeffentlichungen SET live = 0 WHERE live = 1');
            DB::update('veroeffentlichungen', (int) $zeile['id'], ['live' => 1]);
            Settings::set('live_version', (string) $version);

            self::$liveCache = json_decode((string) $zeile['inhalt'], true) ?: null;
            Log::info('veroeffentlichung', 'Auf Fassung ' . $version . ' zurückgesetzt.');
        });
    }

    /* ------------------------------------------------------ Offene Änderungen */

    /**
     * Was hat sich seit der letzten Veröffentlichung geändert? Das Backend zeigt
     * das vor dem Klick, damit niemand blind veröffentlicht.
     *
     * @return array{nie:bool,anzahl:int,liste:array<int,array{art:string,typ:string,titel:string}>}
     */
    public static function offeneAenderungen(): array
    {
        $live = self::live();
        if ($live === null) {
            return ['nie' => true, 'anzahl' => 1, 'liste' => []];
        }

        $entwurf = self::bauen();
        $liste   = [];

        foreach ([
            'artikel'    => 'Artikel',
            'kategorien' => 'Kategorie',
            'seiten'     => 'Seite',
            'beitraege'  => 'Beitrag',
        ] as $schluessel => $bezeichnung) {
            $vorher  = self::nachId($live[$schluessel] ?? []);
            $nachher = self::nachId($entwurf[$schluessel] ?? []);

            foreach ($nachher as $id => $eintrag) {
                if (!isset($vorher[$id])) {
                    $liste[] = ['art' => 'neu', 'typ' => $bezeichnung, 'titel' => (string) $eintrag['titel']];
                } elseif (json_encode($vorher[$id]) !== json_encode($eintrag)) {
                    $liste[] = ['art' => 'geaendert', 'typ' => $bezeichnung, 'titel' => (string) $eintrag['titel']];
                }
            }
            foreach ($vorher as $id => $eintrag) {
                if (!isset($nachher[$id])) {
                    $liste[] = ['art' => 'entfernt', 'typ' => $bezeichnung, 'titel' => (string) $eintrag['titel']];
                }
            }
        }

        // Einstellungen, die den Shop betreffen: Design, Stammdaten, Texte.
        // Betriebsdaten wie die Schemaversion sind hier bewusst ausgenommen.
        $ignorieren = ['schema_version', 'zuletzt_veroeffentlicht', 'live_version'];
        $vorherE  = array_diff_key($live['einstellungen'] ?? [], array_flip($ignorieren));
        $nachherE = array_diff_key($entwurf['einstellungen'] ?? [], array_flip($ignorieren));
        if ($vorherE != $nachherE) {
            $liste[] = ['art' => 'geaendert', 'typ' => 'Einstellungen', 'titel' => 'Shop-Daten oder Design'];
        }
        if (json_encode($live['menues'] ?? []) !== json_encode($entwurf['menues'] ?? [])) {
            $liste[] = ['art' => 'geaendert', 'typ' => 'Navigation', 'titel' => 'Menüpunkte'];
        }

        return ['nie' => false, 'anzahl' => count($liste), 'liste' => $liste];
    }

    /** @param array<int,array<string,mixed>> $eintraege */
    private static function nachId(array $eintraege): array
    {
        $out = [];
        foreach ($eintraege as $eintrag) {
            $out[(int) $eintrag['id']] = $eintrag;
        }
        return $out;
    }

    public static function cacheLeeren(): void
    {
        self::$liveCache = null;
    }
}
