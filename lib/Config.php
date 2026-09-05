<?php
/**
 * Config – lädt die Datei config.php (wird vom Installer erzeugt).
 *
 * In der config.php stehen nur die technischen Grunddaten (Datenbank,
 * Basis-URL, Geheimschlüssel). Alle inhaltlichen Einstellungen – Shopname,
 * Zahlarten, Versandkosten, Design – liegen in der Datenbank und werden im
 * Backend gepflegt.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(?string $file = null): bool
    {
        $file = $file ?: SHOP_ROOT . '/config.php';
        if (!is_file($file)) {
            self::$loaded = false;
            return false;
        }
        $data = require $file;
        if (!is_array($data)) {
            throw new RuntimeException('config.php muss ein Array zurückgeben.');
        }
        self::$data   = $data;
        self::$loaded = true;
        return true;
    }

    public static function isInstalled(): bool
    {
        return self::$loaded && !empty(self::$data['db']);
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        // Punktnotation: 'db.driver'
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Adresse des Shop-Ordners, ohne Schrägstrich am Ende. */
    public static function baseUrl(): string
    {
        return rtrim((string) self::get('base_url', ''), '/');
    }

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === '' ? self::baseUrl() . '/' : self::baseUrl() . '/' . $path;
    }

    /** Geheimschlüssel für Signaturen und die Verschlüsselung von Zugangsdaten. */
    public static function secret(): string
    {
        $secret = (string) self::get('secret', '');
        if ($secret === '') {
            throw new RuntimeException('In config.php fehlt der Wert "secret".');
        }
        return $secret;
    }

    /**
     * Schreibt eine neue config.php (nur vom Installer benutzt).
     *
     * @param array<string,mixed> $data
     */
    public static function write(array $data, ?string $file = null): void
    {
        $file = $file ?: SHOP_ROOT . '/config.php';
        $php  = "<?php\n"
              . "/**\n"
              . " * Konfiguration des Shop-Systems.\n"
              . " * Erzeugt am " . date('d.m.Y H:i') . " durch install.php.\n"
              . " * Diese Datei enthält Zugangsdaten – nicht öffentlich teilen, nicht ins Git.\n"
              . " */\n"
              . "return " . self::export($data) . ";\n";

        if (file_put_contents($file, $php, LOCK_EX) === false) {
            throw new RuntimeException('config.php konnte nicht geschrieben werden.');
        }
        @chmod($file, 0640);
        self::$data   = $data;
        self::$loaded = true;
    }

    /** var_export-Ersatz mit kurzer Array-Syntax. */
    private static function export($value, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            $out    = "[\n";
            foreach ($value as $k => $v) {
                $out .= $pad . '    ';
                if (!$isList) {
                    $out .= var_export((string) $k, true) . ' => ';
                }
                $out .= self::export($v, $indent + 1) . ",\n";
            }
            return $out . $pad . ']';
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return var_export($value, true);
        }
        return var_export((string) $value, true);
    }
}
