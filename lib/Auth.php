<?php
/**
 * Auth – Anmeldung am Backend.
 *
 * Passwörter: password_hash() mit dem Standardverfahren von PHP.
 * Sitzungen: zufälliges Token im HttpOnly-Cookie, serverseitig gespeichert.
 * Kein Token im Cookie allein – so wirkt ein Abmelden sofort und wirklich.
 */
final class Auth
{
    public const COOKIE = 'shop_sitzung';

    private static ?array $benutzer = null;
    private static bool $geladen = false;

    /* --------------------------------------------------------------- Rollen */

    public const ROLLEN = [
        'inhaber'     => 'Inhaber',
        'admin'       => 'Administrator',
        'mitarbeiter' => 'Mitarbeiter',
    ];

    public static function rolleName(string $rolle): string
    {
        return self::ROLLEN[$rolle] ?? $rolle;
    }

    /**
     * Rechte je Rolle.
     *   lesen       – Listen und Details ansehen
     *   pflegen     – Artikel, Bestellungen, Kunden, Inhalte bearbeiten
     *   einstellen  – Einstellungen, Versand, Steuern, Benutzer, Rollback
     */
    public static function rechteVon(string $rolle): array
    {
        return match ($rolle) {
            'inhaber', 'admin' => ['lesen', 'pflegen', 'einstellen'],
            default            => ['lesen', 'pflegen'],
        };
    }

    public static function darf(string $recht): bool
    {
        $benutzer = self::user();
        return $benutzer !== null && in_array($recht, self::rechteVon((string) $benutzer['rolle']), true);
    }

    /* ----------------------------------------------------------- Benutzer */

    public static function anlegen(string $email, string $passwort, string $name = '', string $rolle = 'mitarbeiter'): int
    {
        $email = Util::normalizeEmail($email);
        if (!Util::isEmail($email)) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        $problem = self::passwortProblem($passwort, $email);
        if ($problem !== '') {
            throw new InvalidArgumentException($problem);
        }
        if (DB::value('SELECT COUNT(*) FROM benutzer WHERE email = ?', [$email]) > 0) {
            throw new InvalidArgumentException('Diese E-Mail-Adresse wird bereits verwendet.');
        }

        return DB::insert('benutzer', [
            'email'     => $email,
            'passwort'  => password_hash($passwort, PASSWORD_DEFAULT),
            'name'      => mb_substr($name, 0, 120, 'UTF-8'),
            'rolle'     => Util::einesVon($rolle, array_keys(self::ROLLEN), 'mitarbeiter'),
            'aktiv'     => 1,
            'erstellt'  => Util::now(),
            'geaendert' => Util::now(),
        ]);
    }

    /** Gibt eine Begründung zurück, warum das Passwort nicht taugt – oder "". */
    public static function passwortProblem(string $passwort, ?string $email = null): string
    {
        if (mb_strlen($passwort, 'UTF-8') < 10) {
            return 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        }
        if ($email !== null && $email !== '' && stripos($passwort, explode('@', $email)[0]) !== false) {
            return 'Das Passwort darf nicht die eigene E-Mail-Adresse enthalten.';
        }
        $haeufig = ['passwort', 'password', '12345678', 'qwertz', 'qwerty', 'shop1234', 'administrator', 'willkommen'];
        foreach ($haeufig as $wort) {
            if (stripos($passwort, $wort) !== false) {
                return 'Dieses Passwort ist zu leicht zu erraten. Bitte etwas anderes wählen.';
            }
        }
        return '';
    }

    public static function passwortSetzen(int $benutzerId, string $passwort): void
    {
        $benutzer = DB::row('SELECT email FROM benutzer WHERE id = ?', [$benutzerId]);
        $problem  = self::passwortProblem($passwort, (string) ($benutzer['email'] ?? ''));
        if ($problem !== '') {
            throw new InvalidArgumentException($problem);
        }
        DB::update('benutzer', $benutzerId, [
            'passwort'  => password_hash($passwort, PASSWORD_DEFAULT),
            'geaendert' => Util::now(),
        ]);
        // Alle anderen Sitzungen beenden – ein Passwortwechsel soll fremde
        // Anmeldungen aussperren.
        DB::run('DELETE FROM sitzungen WHERE benutzer_id = ? AND token != ?', [$benutzerId, self::aktuellesToken()]);
        Log::info('auth', 'Passwort geändert für Benutzer ' . $benutzerId . '.');
    }

    public static function anzahl(): int
    {
        try {
            return (int) DB::value('SELECT COUNT(*) FROM benutzer', [], 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /* -------------------------------------------------------- An- und Abmelden */

    /** @return string Leerer String bei Erfolg, sonst die Fehlermeldung. */
    public static function login(string $email, string $passwort): string
    {
        $email    = Util::normalizeEmail($email);
        $benutzer = DB::row('SELECT * FROM benutzer WHERE email = ?', [$email]);

        // Auch ohne Treffer einen Hash prüfen, damit die Antwortzeit nicht
        // verrät, ob es die Adresse gibt.
        if ($benutzer === null) {
            password_verify($passwort, '$2y$12$usukhFMBEEbFvIYlIsFm/.k9CDaWjZ3ZOF9YvfM/ULrJcQTWK4vDy');
            Log::warn('auth', 'Fehlgeschlagene Anmeldung für ' . $email . ' von ' . Util::ip());
            return 'E-Mail-Adresse oder Passwort ist falsch.';
        }
        if ((int) $benutzer['aktiv'] !== 1) {
            return 'Dieser Zugang ist deaktiviert.';
        }
        if (!password_verify($passwort, (string) $benutzer['passwort'])) {
            Log::warn('auth', 'Fehlgeschlagene Anmeldung für ' . $email . ' von ' . Util::ip());
            return 'E-Mail-Adresse oder Passwort ist falsch.';
        }

        // Hash bei Bedarf auf ein neueres Verfahren heben.
        if (password_needs_rehash((string) $benutzer['passwort'], PASSWORD_DEFAULT)) {
            DB::update('benutzer', (int) $benutzer['id'], [
                'passwort'  => password_hash($passwort, PASSWORD_DEFAULT),
                'geaendert' => Util::now(),
            ]);
        }

        self::sitzungStarten((int) $benutzer['id']);
        DB::update('benutzer', (int) $benutzer['id'], ['letzter_login' => Util::now()]);
        Log::info('auth', 'Anmeldung: ' . $email);
        return '';
    }

    private static function sitzungStarten(int $benutzerId): void
    {
        $token = Util::token(32);
        DB::insert('sitzungen', [
            'token'       => $token,
            'benutzer_id' => $benutzerId,
            'laeuft_ab'   => Util::inTagen(14),
            'browser'     => Util::userAgent(),
            'erstellt'    => Util::now(),
        ]);

        if (!headers_sent()) {
            setcookie(self::COOKIE, $token, [
                'expires'  => time() + 14 * 86400,
                'path'     => self::cookiePfad(),
                'secure'   => self::istHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[self::COOKIE] = $token;
        self::$geladen = false;
    }

    public static function logout(): void
    {
        $token = self::aktuellesToken();
        if ($token !== '') {
            DB::run('DELETE FROM sitzungen WHERE token = ?', [$token]);
        }
        if (!headers_sent()) {
            setcookie(self::COOKIE, '', [
                'expires' => time() - 3600, 'path' => self::cookiePfad(),
                'secure' => self::istHttps(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE[self::COOKIE]);
        self::$benutzer = null;
        self::$geladen  = true;
    }

    private static function aktuellesToken(): string
    {
        return (string) ($_COOKIE[self::COOKIE] ?? '');
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$geladen) {
            return self::$benutzer;
        }
        self::$geladen = true;
        self::$benutzer = null;

        $token = self::aktuellesToken();
        if ($token === '') {
            return null;
        }

        $row = DB::row(
            'SELECT b.id, b.email, b.name, b.rolle, b.aktiv, s.laeuft_ab
               FROM sitzungen s JOIN benutzer b ON b.id = s.benutzer_id
              WHERE s.token = ?',
            [$token]
        );
        if ($row === null) {
            return null;
        }
        if (strtotime((string) $row['laeuft_ab']) < time()) {
            DB::run('DELETE FROM sitzungen WHERE token = ?', [$token]);
            return null;
        }
        if ((int) $row['aktiv'] !== 1) {
            return null;
        }

        unset($row['laeuft_ab'], $row['aktiv']);
        self::$benutzer = $row;
        return self::$benutzer;
    }

    public static function angemeldet(): bool
    {
        return self::user() !== null;
    }

    /**
     * Für alle Backend-Seiten: nicht Angemeldete zur Anmeldung schicken,
     * Angemeldeten ohne das nötige Recht eine klare Absage geben.
     *
     * @return array<string,mixed>
     */
    public static function verlangen(string $recht = 'lesen'): array
    {
        $benutzer = self::user();
        if ($benutzer === null) {
            $ziel = (string) ($_SERVER['REQUEST_URI'] ?? '');
            Util::redirect('login.php' . ($ziel !== '' ? '?weiter=' . rawurlencode($ziel) : ''));
        }
        if (!self::darf($recht)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Kein Zugriff</title>'
               . '<link rel="stylesheet" href="assets/admin.css"></head><body>'
               . '<div class="ad-login-wrap"><div class="ad-login">'
               . '<h1>Kein Zugriff</h1>'
               . '<p class="ad-sub">Für diesen Bereich fehlt deinem Zugang die Berechtigung. '
               . 'Ein Administrator kann sie unter „Einstellungen → Benutzer“ vergeben.</p>'
               . '<a class="ad-btn" href="index.php">Zur Übersicht</a>'
               . '</div></div></body></html>';
            exit;
        }
        return $benutzer;
    }

    /* ----------------------------------------------------------------- CSRF */

    /**
     * Token gegen fremde Formulare. Es hängt an der Sitzung: ein Formular auf
     * einer fremden Seite kennt es nicht und kann deshalb keine Aktion im
     * Namen des angemeldeten Betreibers auslösen.
     */
    public static function csrfToken(): string
    {
        return hash_hmac('sha256', 'csrf:' . self::aktuellesToken(), Config::secret());
    }

    public static function csrfFeld(): string
    {
        return '<input type="hidden" name="_token" value="' . Util::e(self::csrfToken()) . '">';
    }

    /** Prüft das Token eines abgeschickten Formulars; bricht bei Fehlschlag ab. */
    public static function csrfPruefen(): void
    {
        $gesendet = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals(self::csrfToken(), $gesendet)) {
            Log::warn('auth', 'CSRF-Prüfung fehlgeschlagen auf ' . ($_SERVER['SCRIPT_NAME'] ?? '?'));
            http_response_code(400);
            echo '<!DOCTYPE html><meta charset="utf-8">'
               . '<p style="font-family:Arial,sans-serif;padding:40px">Das Formular ist abgelaufen. '
               . 'Bitte die Seite neu laden und noch einmal versuchen.</p>';
            exit;
        }
    }

    /* ------------------------------------------------------------ Aufräumen */

    public static function sitzungenAufraeumen(): int
    {
        return DB::run('DELETE FROM sitzungen WHERE laeuft_ab < ?', [Util::now()])->rowCount();
    }

    private static function istHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Cookie nur für den Shop-Ordner setzen, nicht für die ganze Domain. */
    private static function cookiePfad(): string
    {
        $pfad = parse_url(Config::baseUrl(), PHP_URL_PATH);
        return is_string($pfad) && $pfad !== '' ? rtrim($pfad, '/') . '/' : '/';
    }
}
