<?php
/**
 * Log – Protokoll in der Datenbank.
 *
 * Sichtbar im Backend unter "Protokoll". Alte Einträge werden beim Aufräumen
 * entfernt, damit die Datenbank nicht unbegrenzt wächst.
 */
final class Log
{
    public static function info(string $bereich, string $text): void
    {
        self::schreiben('info', $bereich, $text);
    }

    public static function warn(string $bereich, string $text): void
    {
        self::schreiben('warnung', $bereich, $text);
    }

    public static function error(string $bereich, string $text): void
    {
        self::schreiben('fehler', $bereich, $text);
    }

    private static function schreiben(string $ebene, string $bereich, string $text): void
    {
        try {
            DB::insert('protokoll', [
                'ebene'    => $ebene,
                'bereich'  => mb_substr($bereich, 0, 40, 'UTF-8'),
                'text'     => mb_substr($text, 0, 2000, 'UTF-8'),
                'erstellt' => Util::now(),
            ]);
        } catch (Throwable $e) {
            // Das Protokoll darf nie den eigentlichen Vorgang scheitern lassen.
            error_log('[Shop] ' . $ebene . ' ' . $bereich . ': ' . $text);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function letzte(int $limit = 200, string $ebene = ''): array
    {
        $where  = $ebene !== '' ? 'WHERE ebene = ?' : '';
        $params = $ebene !== '' ? [$ebene] : [];
        return DB::all(
            'SELECT * FROM protokoll ' . $where . ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)),
            $params
        );
    }

    public static function aufraeumen(int $tage = 60): int
    {
        return DB::run('DELETE FROM protokoll WHERE erstellt < ?', [
            date('Y-m-d H:i:s', time() - $tage * 86400),
        ])->rowCount();
    }
}
