<?php
/**
 * Inhalte – Seiten, Journal-Beiträge und die Navigation.
 *
 * Seiten und Beiträge sind fast gleich aufgebaut, deshalb teilen sie sich die
 * Methoden und unterscheiden sich nur über den Tabellennamen.
 */
final class Inhalte
{
    /* ------------------------------------------------------------- Seiten */

    /** @return array<int,array<string,mixed>> */
    public static function seiten(?bool $nurSichtbare = null, string $suche = ''): array
    {
        return self::liste('seiten', $nurSichtbare, $suche);
    }

    public static function seite(int $id): ?array
    {
        return DB::row('SELECT * FROM seiten WHERE id = ?', [$id]);
    }

    public static function seiteNachHandle(string $handle): ?array
    {
        return DB::row('SELECT * FROM seiten WHERE handle = ?', [$handle]);
    }

    /** @param array<string,mixed> $daten */
    public static function seiteSpeichern(?int $id, array $daten): int
    {
        return self::speichern('seiten', $id, $daten, 'seite');
    }

    public static function seiteLoeschen(int $id): void
    {
        DB::delete('seiten', $id);
    }

    /* ----------------------------------------------------------- Beiträge */

    /** @return array<int,array<string,mixed>> */
    public static function beitraege(?bool $nurSichtbare = null, string $suche = ''): array
    {
        return self::liste('beitraege', $nurSichtbare, $suche);
    }

    public static function beitrag(int $id): ?array
    {
        return DB::row('SELECT * FROM beitraege WHERE id = ?', [$id]);
    }

    public static function beitragNachHandle(string $handle): ?array
    {
        return DB::row('SELECT * FROM beitraege WHERE handle = ?', [$handle]);
    }

    /** @param array<string,mixed> $daten */
    public static function beitragSpeichern(?int $id, array $daten): int
    {
        return self::speichern('beitraege', $id, $daten, 'beitrag');
    }

    public static function beitragLoeschen(int $id): void
    {
        DB::delete('beitraege', $id);
    }

    /* ---------------------------------------------------------- Gemeinsam */

    private static function liste(string $tabelle, ?bool $nurSichtbare, string $suche): array
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
        $order     = $tabelle === 'beitraege' ? 'sichtbar_seit DESC, id DESC' : 'titel ASC';
        return DB::all('SELECT * FROM ' . $tabelle . ' ' . $bedingung . ' ORDER BY ' . $order, $params);
    }

    /** @param array<string,mixed> $daten */
    private static function speichern(string $tabelle, ?int $id, array $daten, string $fallbackHandle): int
    {
        $vorher = $id !== null ? DB::row('SELECT * FROM ' . $tabelle . ' WHERE id = ?', [$id]) : null;

        $titel = trim((string) ($daten['titel'] ?? $vorher['titel'] ?? ''));
        if ($titel === '') {
            throw new InvalidArgumentException('Bitte einen Titel angeben.');
        }
        $inhalt   = Util::sauberesHtml((string) ($daten['inhalt'] ?? $vorher['inhalt'] ?? ''));
        $sichtbar = !empty($daten['sichtbar']);

        $felder = [
            'titel'     => mb_substr($titel, 0, 200, 'UTF-8'),
            'inhalt'    => $inhalt,
            'sichtbar'  => $sichtbar ? 1 : 0,
            'seo_titel' => mb_substr((string) ($daten['seo_titel'] ?? $vorher['seo_titel'] ?? ''), 0, 200, 'UTF-8'),
            'seo_text'  => mb_substr((string) ($daten['seo_text'] ?? $vorher['seo_text'] ?? '') ?: Util::nurText($inhalt, 160), 0, 400, 'UTF-8'),
            'geaendert' => Util::now(),
        ];

        if ($tabelle === 'beitraege') {
            $felder['anriss']   = mb_substr((string) ($daten['anriss'] ?? $vorher['anriss'] ?? '') ?: Util::nurText($inhalt, 200), 0, 500, 'UTF-8');
            $felder['bild_url'] = mb_substr((string) ($daten['bild_url'] ?? $vorher['bild_url'] ?? ''), 0, 255, 'UTF-8');
            $felder['autor']    = mb_substr((string) ($daten['autor'] ?? $vorher['autor'] ?? ''), 0, 120, 'UTF-8');
            $felder['sichtbar_seit'] = $sichtbar ? ($vorher['sichtbar_seit'] ?? Util::now()) : null;
        }

        $handleQuelle = (string) ($daten['handle'] ?? '') ?: $titel;
        if ($vorher === null) {
            $felder['handle'] = Util::handleEindeutig(
                $handleQuelle,
                static fn(string $h): bool => DB::value('SELECT COUNT(*) FROM ' . $tabelle . ' WHERE handle = ?', [$h]) > 0,
                $fallbackHandle
            );
            $felder['erstellt'] = Util::now();
            return DB::insert($tabelle, $felder);
        }

        if (isset($daten['handle']) && (string) $daten['handle'] !== (string) $vorher['handle']) {
            $felder['handle'] = Util::handleEindeutig(
                $handleQuelle,
                static fn(string $h): bool => DB::value('SELECT COUNT(*) FROM ' . $tabelle . ' WHERE handle = ? AND id != ?', [$h, $id]) > 0,
                $fallbackHandle
            );
        }
        DB::update($tabelle, $id, $felder);
        return $id;
    }

    /* --------------------------------------------------------- Navigation */

    /**
     * Menü als Baum. Zwei Ebenen genügen – tiefer verschachtelte
     * Shop-Navigationen sind auf Mobilgeräten unbedienbar.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function menue(string $menue = 'haupt'): array
    {
        $zeilen = DB::all('SELECT * FROM menuepunkte WHERE menue = ? ORDER BY position, id', [$menue]);

        // In einem Durchgang die Wurzelpunkte sammeln, im zweiten die Kinder
        // einhängen. Zwei Durchgänge, weil ein Kind vor seinem Elternpunkt
        // stehen kann.
        $wurzeln = [];
        foreach ($zeilen as $zeile) {
            if ($zeile['eltern_id'] === null) {
                $zeile['kinder'] = [];
                $wurzeln[(int) $zeile['id']] = $zeile;
            }
        }
        foreach ($zeilen as $zeile) {
            $eltern = $zeile['eltern_id'] !== null ? (int) $zeile['eltern_id'] : 0;
            if ($eltern > 0 && isset($wurzeln[$eltern])) {
                $wurzeln[$eltern]['kinder'][] = $zeile;
            }
        }
        return array_values($wurzeln);
    }

    /**
     * Ersetzt ein Menü vollständig – das Backend schickt immer den ganzen Baum.
     *
     * @param array<int,array{label:string,url:string,kinder?:array}> $punkte
     */
    public static function menueSetzen(string $menue, array $punkte): void
    {
        DB::transaction(static function () use ($menue, $punkte): void {
            DB::run('DELETE FROM menuepunkte WHERE menue = ?', [$menue]);

            $position = 0;
            foreach (array_slice($punkte, 0, 50) as $punkt) {
                $label = trim((string) ($punkt['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $elternId = DB::insert('menuepunkte', [
                    'menue'     => $menue,
                    'eltern_id' => null,
                    'label'     => mb_substr($label, 0, 120, 'UTF-8'),
                    'url'       => mb_substr(trim((string) ($punkt['url'] ?? '')) ?: 'index.php', 0, 255, 'UTF-8'),
                    'position'  => $position++,
                ]);

                $kindPosition = 0;
                foreach (array_slice((array) ($punkt['kinder'] ?? []), 0, 20) as $kind) {
                    $kindLabel = trim((string) ($kind['label'] ?? ''));
                    if ($kindLabel === '') {
                        continue;
                    }
                    DB::insert('menuepunkte', [
                        'menue'     => $menue,
                        'eltern_id' => $elternId,
                        'label'     => mb_substr($kindLabel, 0, 120, 'UTF-8'),
                        'url'       => mb_substr(trim((string) ($kind['url'] ?? '')) ?: 'index.php', 0, 255, 'UTF-8'),
                        'position'  => $kindPosition++,
                    ]);
                }
            }
        });
    }
}
