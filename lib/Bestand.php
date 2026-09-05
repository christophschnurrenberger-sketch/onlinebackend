<?php
/**
 * Bestand – Lagerbestände und ihr Journal.
 *
 * Jede Änderung geht durch buchen() und hinterlässt eine Bewegung. Der Bestand
 * ist damit jederzeit erklärbar ("warum stehen hier drei?") – ohne Journal wäre
 * Lagerhaltung nicht nachvollziehbar.
 */
final class Bestand
{
    public const GRUENDE = [
        'verkauf'   => 'Verkauf',
        'zugang'    => 'Wareneingang',
        'korrektur' => 'Korrektur',
        'retoure'   => 'Retoure',
        'storno'    => 'Storno',
    ];

    /**
     * Verfügbare Menge einer Variante.
     * null bedeutet "unbegrenzt" – entweder ohne Bestandsführung oder mit
     * ausdrücklich erlaubtem Überverkauf.
     *
     * @param array<string,mixed> $variante
     */
    public static function verfuegbar(array $variante): ?int
    {
        if ((int) ($variante['bestand_fuehren'] ?? 1) !== 1) {
            return null;
        }
        if ((int) ($variante['ueberverkauf'] ?? 0) === 1) {
            return null;
        }
        return (int) ($variante['bestand'] ?? 0);
    }

    public static function buchen(int $variantenId, int $menge, string $grund = 'korrektur', ?int $bestellungId = null, ?int $benutzerId = null): ?int
    {
        return DB::transaction(static function () use ($variantenId, $menge, $grund, $bestellungId, $benutzerId): ?int {
            $variante = DB::row('SELECT bestand, bestand_fuehren FROM varianten WHERE id = ?', [$variantenId]);
            if ($variante === null) {
                return null;
            }
            if ((int) $variante['bestand_fuehren'] === 1) {
                DB::run('UPDATE varianten SET bestand = bestand + ?, geaendert = ? WHERE id = ?', [$menge, Util::now(), $variantenId]);
            }
            DB::insert('bestandsbewegungen', [
                'varianten_id'  => $variantenId,
                'menge'         => $menge,
                'grund'         => Util::einesVon($grund, array_keys(self::GRUENDE), 'korrektur'),
                'bestellung_id' => $bestellungId,
                'benutzer_id'   => $benutzerId,
                'erstellt'      => Util::now(),
            ]);
            return (int) DB::value('SELECT bestand FROM varianten WHERE id = ?', [$variantenId], 0);
        });
    }

    public static function setzen(int $variantenId, int $menge, ?int $benutzerId = null): ?int
    {
        $aktuell = DB::value('SELECT bestand FROM varianten WHERE id = ?', [$variantenId]);
        if ($aktuell === null) {
            return null;
        }
        return self::buchen($variantenId, $menge - (int) $aktuell, 'korrektur', null, $benutzerId);
    }

    /**
     * Bucht die Zeilen einer Bestellung aus. Vorher wird der ganze Korb geprüft:
     * entweder alles ist lieferbar oder nichts wird gebucht – ein halb
     * ausgebuchter Warenkorb wäre nicht reparierbar.
     *
     * @param array<int,array{varianten_id:int,menge:int,titel?:string}> $zeilen
     * @throws RuntimeException wenn etwas fehlt
     */
    public static function reservieren(array $zeilen, ?int $bestellungId = null, ?int $benutzerId = null): void
    {
        DB::transaction(static function () use ($zeilen, $bestellungId, $benutzerId): void {
            $probleme = [];
            foreach ($zeilen as $zeile) {
                $variante = DB::row('SELECT * FROM varianten WHERE id = ?', [(int) $zeile['varianten_id']]);
                if ($variante === null) {
                    $probleme[] = ($zeile['titel'] ?? 'Ein Artikel') . ' ist nicht mehr verfügbar.';
                    continue;
                }
                $frei = self::verfuegbar($variante);
                if ($frei !== null && (int) $zeile['menge'] > $frei) {
                    $probleme[] = ($zeile['titel'] ?? (string) $variante['titel']) . ': '
                        . ($frei <= 0 ? 'ausverkauft' : 'nur noch ' . $frei . ' verfügbar');
                }
            }
            if ($probleme !== []) {
                throw new RuntimeException(implode(' · ', $probleme));
            }
            foreach ($zeilen as $zeile) {
                self::buchen((int) $zeile['varianten_id'], -(int) $zeile['menge'], 'verkauf', $bestellungId, $benutzerId);
            }
        });
    }

    /** Gegenbuchung bei Storno oder Retoure. */
    public static function freigeben(array $zeilen, ?int $bestellungId = null, string $grund = 'storno', ?int $benutzerId = null): void
    {
        DB::transaction(static function () use ($zeilen, $bestellungId, $grund, $benutzerId): void {
            foreach ($zeilen as $zeile) {
                $variantenId = (int) ($zeile['varianten_id'] ?? 0);
                if ($variantenId <= 0) {
                    continue;
                }
                self::buchen($variantenId, (int) $zeile['menge'], $grund, $bestellungId, $benutzerId);
            }
        });
    }

    /** @return array<int,array<string,mixed>> */
    public static function bewegungen(int $variantenId, int $limit = 100): array
    {
        return DB::all(
            'SELECT bw.*, b.nummer AS bestellnummer, u.name AS benutzer_name
               FROM bestandsbewegungen bw
               LEFT JOIN bestellungen b ON b.id = bw.bestellung_id
               LEFT JOIN benutzer u ON u.id = bw.benutzer_id
              WHERE bw.varianten_id = ?
              ORDER BY bw.id DESC LIMIT ' . max(1, min(500, $limit)),
            [$variantenId]
        );
    }

    /** Varianten unter der Meldeschwelle – die Startseite des Backends zeigt sie. */
    public static function knapp(int $schwelle = 5, int $limit = 20): array
    {
        $sql = <<<SQL
            SELECT v.id, v.titel AS variante, v.artikelnummer, v.bestand,
                   a.id AS artikel_id, a.titel AS artikel
              FROM varianten v JOIN artikel a ON a.id = v.artikel_id
             WHERE v.bestand_fuehren = 1 AND v.bestand <= ? AND a.status != 'archiv'
             ORDER BY v.bestand ASC, a.titel
            SQL;
        return DB::all($sql . ' LIMIT ' . max(1, min(100, $limit)), [$schwelle]);
    }

    /** @return array<int,array<string,mixed>> Alle Varianten für die Bestandstabelle. */
    public static function uebersicht(string $suche = '', int $limit = 300): array
    {
        $where  = ["a.status != 'archiv'"];
        $params = [];
        if ($suche !== '') {
            $where[]  = '(a.titel LIKE ? OR v.artikelnummer LIKE ?)';
            $params[] = '%' . $suche . '%';
            $params[] = '%' . $suche . '%';
        }
        return DB::all(
            'SELECT v.*, a.id AS artikel_id, a.titel AS artikel, a.status
               FROM varianten v JOIN artikel a ON a.id = v.artikel_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY a.titel, v.position
              LIMIT ' . max(1, min(1000, $limit)),
            $params
        );
    }
}
