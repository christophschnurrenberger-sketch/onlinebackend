<?php
/**
 * Util – kleine Helfer, die überall gebraucht werden.
 *
 * Geld ist im ganzen System eine Ganzzahl in Cent: 19,90 € sind 1990.
 * Gerundet wird ausschließlich hier – das hält Warenkorb, Bestellung und
 * Rechnung auf denselben Beträgen.
 */
final class Util
{
    /* ------------------------------------------------------------- Ausgabe */

    /** HTML-Ausgabe absichern. Kurzer Name, weil er sehr oft vorkommt. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Header-Werte säubern (verhindert Zeilenumbrüche im Header). */
    public static function header(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    /* ---------------------------------------------------------------- Geld */

    /** Cent als Währungstext: 1990 → "19,90 €". */
    public static function geld(int $cent, ?string $waehrung = null): string
    {
        $waehrung = $waehrung ?: Settings::get('waehrung', 'EUR');
        $zeichen  = ['EUR' => '€', 'CHF' => 'CHF', 'USD' => '$', 'GBP' => '£'][$waehrung] ?? $waehrung;
        $text     = number_format($cent / 100, 2, ',', '.');
        return $waehrung === 'CHF' ? $zeichen . ' ' . $text : $text . ' ' . $zeichen;
    }

    /**
     * Einheiten für die Füllmenge und ihr Umrechnungsfaktor auf die
     * Grundpreis-Basiseinheit (1 l, 1 kg, 1 m, 1 m²).
     *
     * @var array<string,array{0:string,1:float,2:string}> Kürzel => [Anzeige, Faktor, Basis]
     */
    public const EINHEITEN = [
        'ml'  => ['ml', 0.001, 'l'],
        'l'   => ['l',  1.0,   'l'],
        'g'   => ['g',  0.001, 'kg'],
        'kg'  => ['kg', 1.0,   'kg'],
        'cm'  => ['cm', 0.01,  'm'],
        'm'   => ['m',  1.0,   'm'],
        'm2'  => ['m²', 1.0,   'm²'],
        'stk' => ['Stück', 1.0, 'Stück'],
    ];

    /**
     * Grundpreis nach Preisangabenverordnung: "19,49 €/l".
     *
     * Pflicht für alles, was nach Gewicht, Volumen, Länge oder Fläche verkauft
     * wird – und einer der häufigsten Abmahngründe im deutschen Onlinehandel.
     * Basis ist seit 2022 einheitlich 1 kg bzw. 1 l; bei Mengen bis 250 g/ml
     * darf auf 100 g/ml bezogen werden, was hier automatisch geschieht, weil
     * "12,99 €/l" bei einer 50-ml-Flasche niemandem hilft.
     *
     * @param int $menge Füllmenge in Tausendsteln der Einheit (0,75 l => 750)
     */
    public static function grundpreis(int $preisCent, int $menge, string $einheit): string
    {
        $eintrag = self::EINHEITEN[$einheit] ?? null;
        if ($eintrag === null || $menge <= 0 || $preisCent <= 0 || $einheit === 'stk') {
            return '';
        }
        [, $faktor, $basis] = $eintrag;

        $inBasis = ($menge / 1000) * $faktor;
        if ($inBasis <= 0) {
            return '';
        }

        $bezug = 1.0;
        $label = $basis;
        if (in_array($basis, ['l', 'kg'], true) && $inBasis <= 0.25) {
            $bezug = 0.1;
            $label = $basis === 'l' ? '100 ml' : '100 g';
        }

        return self::geld((int) round($preisCent / $inBasis * $bezug)) . '/' . $label;
    }

    /** Füllmenge als Eingabewert: 750 → "0,75". */
    public static function mengeFeld(int $menge): string
    {
        return $menge === 0 ? '' : rtrim(rtrim(number_format($menge / 1000, 3, ',', ''), '0'), ',');
    }

    /** Liest "0,75" oder "750" als Tausendstel. */
    public static function mengeAus($eingabe): int
    {
        if ($eingabe === null || $eingabe === '') {
            return 0;
        }
        $text = str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', (string) $eingabe) ?? '');
        return $text === '' ? 0 : (int) round(((float) $text) * 1000);
    }

    /** Cent als reine Zahl für Eingabefelder: 1990 → "19,90". */
    public static function geldFeld(?int $cent): string
    {
        return $cent === null ? '' : number_format($cent / 100, 2, ',', '');
    }

    /**
     * Liest Nutzereingaben wie "19,90", "19.90" oder "1.990,00" als Cent.
     * Deutsche und englische Schreibweise werden beide erkannt.
     */
    public static function centAus($eingabe): int
    {
        if ($eingabe === null || $eingabe === '') {
            return 0;
        }
        if (is_int($eingabe)) {
            return $eingabe;
        }
        if (is_float($eingabe)) {
            return (int) round($eingabe * 100);
        }

        $text  = preg_replace('/[^\d,.\-]/', '', (string) $eingabe) ?? '';
        $komma = strrpos($text, ',');
        $punkt = strrpos($text, '.');

        if ($komma !== false && ($punkt === false || $komma > $punkt)) {
            // Deutsches Format: Punkt trennt Tausender, Komma die Dezimalstellen.
            $text = str_replace(['.', ','], ['', '.'], $text);
        } else {
            $text = str_replace(',', '', $text);
        }
        return is_numeric($text) ? (int) round(((float) $text) * 100) : 0;
    }

    /**
     * Verteilt einen Betrag proportional auf Gewichte, ohne dass durch Rundung
     * Cent verloren gehen: die Summe der Anteile ist exakt $gesamt. Der Rest
     * wandert an die Positionen mit dem größten Rundungsverlust.
     *
     * @param array<int,int> $gewichte
     * @return array<int,int>
     */
    public static function verteilen(int $gesamt, array $gewichte): array
    {
        $summe = array_sum($gewichte);
        if ($summe <= 0 || $gesamt === 0) {
            return array_fill(0, count($gewichte), 0);
        }

        $anteile = [];
        $reste   = [];
        foreach ($gewichte as $i => $gewicht) {
            $exakt      = ($gesamt * $gewicht) / $summe;
            $anteile[$i] = (int) floor($exakt);
            $reste[$i]   = $exakt - floor($exakt);
        }

        $rest = $gesamt - array_sum($anteile);
        arsort($reste);
        foreach (array_keys($reste) as $i) {
            if ($rest <= 0) {
                break;
            }
            $anteile[$i]++;
            $rest--;
        }
        ksort($anteile);
        return $anteile;
    }

    /** Der im Bruttopreis enthaltene Steueranteil. $satz in Basispunkten (1900 = 19 %). */
    public static function steuerAusBrutto(int $brutto, int $satz): int
    {
        if ($satz <= 0) {
            return 0;
        }
        return (int) round($brutto - $brutto / (1 + $satz / 10000));
    }

    /* --------------------------------------------------------------- Zeit */

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function inTagen(int $tage): string
    {
        return date('Y-m-d H:i:s', time() + $tage * 86400);
    }

    public static function dt(?string $value, string $format = 'd.m.Y H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $ts = strtotime($value);
        return $ts === false ? '—' : date($format, $ts);
    }

    /** "vor 5 Min." – für Listen angenehmer als ein voller Zeitstempel. */
    public static function seit(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60)     return 'gerade eben';
        if ($diff < 3600)   return 'vor ' . (int) round($diff / 60) . ' Min.';
        if ($diff < 86400)  return 'vor ' . (int) round($diff / 3600) . ' Std.';
        if ($diff < 2592000) {
            $tage = (int) round($diff / 86400);
            return 'vor ' . $tage . ' Tag' . ($tage === 1 ? '' : 'en');
        }
        return date('d.m.Y', $ts);
    }

    /* ---------------------------------------------------------- Zeichenketten */

    /** URL-Handle aus einem Titel: "Größe & Länge" → "groesse-laenge". */
    public static function handle(string $text, string $fallback = 'eintrag'): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ç' => 'c', 'ñ' => 'n', 'å' => 'aa', 'ø' => 'oe', 'æ' => 'ae',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        $text = mb_substr($text, 0, 80, 'UTF-8');
        return $text === '' ? $fallback : $text;
    }

    /**
     * Macht einen Handle eindeutig, indem -2, -3 … angehängt wird.
     * $vergeben beantwortet "ist dieser Handle schon belegt?".
     */
    public static function handleEindeutig(string $basis, callable $vergeben, string $fallback = 'eintrag'): string
    {
        $handle = self::handle($basis, $fallback);
        if (!$vergeben($handle)) {
            return $handle;
        }
        for ($n = 2; $n < 1000; $n++) {
            if (!$vergeben($handle . '-' . $n)) {
                return $handle . '-' . $n;
            }
        }
        return $handle . '-' . time();
    }

    public static function kuerzen(string $value, int $max = 60): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        return mb_strlen($value, 'UTF-8') <= $max
            ? $value
            : mb_substr($value, 0, $max - 1, 'UTF-8') . '…';
    }

    /** Reiner Text aus HTML – für SEO-Beschreibungen und die Suche. */
    public static function nurText(string $html, int $max = 0): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        return $max > 0 ? self::kuerzen($text, $max) : $text;
    }

    /**
     * Erlaubt nur eine kleine, für Artikeltexte ausreichende Menge an HTML.
     * Ein Zugang im Backend ist kein Grund, dem Browser des Kunden beliebiges
     * Markup auszuliefern – auch Mitarbeitende sollen kein Skript einschleusen
     * können.
     */
    public static function sauberesHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Elemente, deren Inhalt nie gerendert werden darf.
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[\s\S]*?</\1>#i', '', $html) ?? $html;
        $html = preg_replace('#</?(script|style|iframe|object|embed|form)\b[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('/<!--[\s\S]*?-->/', '', $html) ?? $html;

        $erlaubteTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'hr', 'img',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div',
            'figure', 'figcaption', 'small', 'code', 'pre',
        ];
        $erlaubteAttr = ['href', 'title', 'alt', 'src', 'width', 'height', 'target', 'rel'];

        return preg_replace_callback(
            '#<(/?)([a-zA-Z0-9]+)((?:\s[^>]*)?)/?>#',
            static function (array $m) use ($erlaubteTags, $erlaubteAttr): string {
                $tag = strtolower($m[2]);
                if (!in_array($tag, $erlaubteTags, true)) {
                    return '';
                }
                if ($m[1] === '/') {
                    return '</' . $tag . '>';
                }

                $behalten = [];
                if (preg_match_all('/([a-zA-Z-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $m[3], $attrs, PREG_SET_ORDER)) {
                    foreach ($attrs as $attr) {
                        $name = strtolower($attr[1]);
                        if (!in_array($name, $erlaubteAttr, true)) {
                            continue;
                        }
                        $wert = $attr[3] ?? '';
                        if ($wert === '') {
                            $wert = $attr[4] ?? ($attr[5] ?? '');
                        }
                        // javascript: und data: in href/src sind der klassische XSS-Weg.
                        if (in_array($name, ['href', 'src'], true)
                            && preg_match('/^\s*(javascript|data|vbscript):/i', $wert)) {
                            continue;
                        }
                        $behalten[] = $name . '="' . self::e($wert) . '"';
                    }
                }
                $leer = in_array($tag, ['br', 'hr', 'img'], true);
                return '<' . $tag . ($behalten ? ' ' . implode(' ', $behalten) : '') . ($leer ? ' /' : '') . '>';
            },
            $html
        ) ?? '';
    }

    /* ------------------------------------------------------------ Zufall & Signatur */

    public static function token(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Kurzes, URL-taugliches Token ohne verwechselbare Zeichen. */
    public static function code(int $laenge = 8): string
    {
        $zeichen = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out     = '';
        for ($i = 0; $i < $laenge; $i++) {
            $out .= $zeichen[random_int(0, strlen($zeichen) - 1)];
        }
        return $out;
    }

    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, Config::secret());
    }

    public static function checkSign(string $payload, string $signature): bool
    {
        return hash_equals(self::sign($payload), $signature);
    }

    /** Verschlüsselt Zugangsdaten (z. B. SMTP-Passwörter) für die Datenbank. */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $key = hash('sha256', Config::secret(), true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($enc === false) {
            throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
        }
        return base64_encode($iv . $enc);
    }

    public static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $key = hash('sha256', Config::secret(), true);
        $dec = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));
        return $dec === false ? '' : $dec;
    }

    /* ------------------------------------------------------------- Eingaben */

    public static function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    public static function post(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function postRaw(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    public static function postInt(string $key, int $default = 0): int
    {
        $value = $_POST[$key] ?? null;
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function postBool(string $key): bool
    {
        return isset($_POST[$key]) && $_POST[$key] !== '' && $_POST[$key] !== '0';
    }

    /** @return array<int,string> */
    public static function postArray(string $key): array
    {
        $value = $_POST[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = $_GET[$key] ?? null;
        return is_numeric($value) ? (int) $value : $default;
    }

    /** Wert auf eine Liste erlaubter Werte begrenzen. */
    public static function einesVon(string $wert, array $erlaubt, ?string $fallback = null): string
    {
        return in_array($wert, $erlaubt, true) ? $wert : ($fallback ?? $erlaubt[0]);
    }

    public static function isEmail(string $email): bool
    {
        return (bool) filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function userAgent(int $max = 255): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, $max, 'UTF-8');
    }

    /* ------------------------------------------------------------- Antworten */

    public static function redirect(string $url): never
    {
        if (!headers_sent()) {
            header('Location: ' . self::header($url), true, 302);
        }
        echo '<!DOCTYPE html><meta charset="utf-8"><p><a href="' . self::e($url) . '">Weiter</a></p>';
        exit;
    }

    public static function json($data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /** Aktuelle Adresse erraten – für den Installer, bevor config.php existiert. */
    public static function erratenenBasisUrl(): string
    {
        $https  = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $pfad   = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return ($https ? 'https://' : 'http://') . $host . $pfad;
    }
}
