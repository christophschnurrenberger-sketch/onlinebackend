<?php
/**
 * Medien – hochgeladene Bilder.
 *
 * Der Dateiname wird beim Hochladen neu vergeben: ein vom Browser gelieferter
 * Name könnte Pfadanteile enthalten oder eine bestehende Datei überschreiben.
 */
final class Medien
{
    public const MAX_BYTES = 8388608; // 8 MB

    /** Erlaubte Typen und die Endung, unter der wir sie speichern. */
    public const TYPEN = [
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/webp' => '.webp',
        'image/avif' => '.avif',
        'image/gif'  => '.gif',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function liste(int $limit = 200): array
    {
        return DB::all('SELECT * FROM medien ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
    }

    /**
     * Nimmt einen Upload aus $_FILES entgegen.
     *
     * @param array<string,mixed> $datei Ein Eintrag aus $_FILES
     * @return array{url:string,id:int}
     */
    public static function hochladen(array $datei): array
    {
        $fehler = (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fehler !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::fehlertext($fehler));
        }
        $tmp = (string) ($datei['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Die Datei konnte nicht gelesen werden.');
        }
        $groesse = (int) ($datei['size'] ?? 0);
        if ($groesse > self::MAX_BYTES) {
            throw new RuntimeException('Die Datei ist größer als 8 MB.');
        }

        // Auf den tatsächlichen Inhalt schauen, nicht auf die Angabe des
        // Browsers – die lässt sich frei setzen.
        $info = @getimagesize($tmp);
        $typ  = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!isset(self::TYPEN[$typ])) {
            throw new RuntimeException('Nur Bilder sind erlaubt (JPEG, PNG, WebP, AVIF, GIF).');
        }

        $ordner = SHOP_ROOT . '/uploads';
        if (!is_dir($ordner) && !@mkdir($ordner, 0755, true)) {
            throw new RuntimeException('Der Ordner "uploads" konnte nicht angelegt werden.');
        }
        if (!is_writable($ordner)) {
            throw new RuntimeException('Der Ordner "uploads" ist nicht beschreibbar. Bitte die Rechte auf 755 setzen.');
        }

        $name = date('Ymd') . '-' . Util::token(6) . self::TYPEN[$typ];
        if (!@move_uploaded_file($tmp, $ordner . '/' . $name)) {
            throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
        }
        @chmod($ordner . '/' . $name, 0644);

        $url = 'uploads/' . $name;
        $id  = DB::insert('medien', [
            'datei'    => mb_substr((string) ($datei['name'] ?? $name), 0, 255, 'UTF-8'),
            'url'      => $url,
            'typ'      => $typ,
            'groesse'  => $groesse,
            'alt'      => '',
            'erstellt' => Util::now(),
        ]);

        return ['id' => $id, 'url' => $url];
    }

    public static function loeschen(int $id): void
    {
        $eintrag = DB::row('SELECT url FROM medien WHERE id = ?', [$id]);
        if ($eintrag === null) {
            return;
        }
        $pfad = SHOP_ROOT . '/' . ltrim((string) $eintrag['url'], '/');
        // Nur Dateien im uploads-Ordner löschen, nie etwas außerhalb.
        if (str_starts_with(realpath(dirname($pfad)) ?: '', realpath(SHOP_ROOT . '/uploads') ?: 'x') && is_file($pfad)) {
            @unlink($pfad);
        }
        DB::delete('medien', $id);
    }

    private static function fehlertext(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'Die Datei ist zu groß. Erlaubt sind '
                . ini_get('upload_max_filesize') . ' – der Wert steht in der PHP-Konfiguration des Hosters.',
            UPLOAD_ERR_PARTIAL   => 'Die Datei wurde nur teilweise übertragen. Bitte noch einmal versuchen.',
            UPLOAD_ERR_NO_FILE   => 'Es wurde keine Datei ausgewählt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Auf dem Server fehlt ein temporäres Verzeichnis. Bitte den Hoster fragen.',
            UPLOAD_ERR_CANT_WRITE => 'Die Datei konnte nicht auf die Festplatte geschrieben werden.',
            default              => 'Der Upload ist fehlgeschlagen (Code ' . $code . ').',
        };
    }
}
