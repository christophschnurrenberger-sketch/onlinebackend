<?php
/**
 * Steuern – Steuersätze in Basispunkten.
 *
 * 19 % werden als 1900 gespeichert. Das erlaubt auch krumme Sätze exakt und
 * hält die Berechnung frei von Fließkomma.
 */
final class Steuern
{
    /** @return array<int,array<string,mixed>> */
    public static function liste(): array
    {
        return DB::all('SELECT * FROM steuersaetze ORDER BY standard DESC, satz_bp DESC');
    }

    public static function holen(?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        return DB::row('SELECT * FROM steuersaetze WHERE id = ?', [$id]);
    }

    public static function standard(): ?array
    {
        return DB::row('SELECT * FROM steuersaetze WHERE standard = 1')
            ?? DB::row('SELECT * FROM steuersaetze ORDER BY id LIMIT 1');
    }

    /** Der Satz, mit dem gerechnet wird, wenn keiner am Artikel hängt. */
    public static function standardSatz(): int
    {
        $satz = self::standard();
        return $satz !== null ? (int) $satz['satz_bp'] : Settings::int('steuer_standard_bp', 1900);
    }

    /** @param string|float $prozent z. B. "19" oder "7,0" */
    public static function anlegen(string $name, $prozent, string $land = 'DE', bool $standard = false): int
    {
        $bp = (int) round(((float) str_replace(',', '.', (string) $prozent)) * 100);
        if ($standard) {
            DB::run('UPDATE steuersaetze SET standard = 0');
        }
        return DB::insert('steuersaetze', [
            'name'     => mb_substr(trim($name), 0, 120, 'UTF-8') ?: 'Steuersatz',
            'satz_bp'  => max(0, min(10000, $bp)),
            'land'     => mb_strtoupper(mb_substr($land, 0, 2, 'UTF-8'), 'UTF-8') ?: 'DE',
            'standard' => $standard ? 1 : 0,
        ]);
    }

    public static function loeschen(int $id): void
    {
        // Artikel mit diesem Satz fallen auf den Standardsatz zurück.
        DB::run('UPDATE varianten SET steuer_id = NULL WHERE steuer_id = ?', [$id]);
        DB::delete('steuersaetze', $id);
    }

    public static function prozentText(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',') . ' %';
    }
}
