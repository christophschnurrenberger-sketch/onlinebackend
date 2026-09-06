<?php
/**
 * bootstrap.php – gemeinsamer Einstieg für alle Skripte des Shop-Systems.
 *
 * Lädt die Klassen, die Konfiguration und die Datenbankverbindung.
 * Ohne config.php (also vor der Einrichtung) wird auf install.php verwiesen.
 */

declare(strict_types=1);

if (!defined('SHOP_ROOT')) {
    define('SHOP_ROOT', dirname(__DIR__));
}

/**
 * Fassung des Programmcodes. Steht im Backend unten und im Systemcheck – so
 * ist sofort erkennbar, welcher Stand auf dem Server liegt.
 */
define('SHOP_VERSION', '1.0.0');

/*
 * PHP-Version prüfen, bevor die Klassen geladen werden.
 *
 * Die Programmdateien nutzen Sprachmittel aus PHP 8.1. Auf einem älteren
 * Server ließen sie sich nicht einmal einlesen – der Besucher sähe nur eine
 * weiße Seite oder "Internal Server Error", ohne jeden Hinweis auf die
 * Ursache. Diese Datei selbst kommt bewusst ohne neue Sprachmittel aus und
 * kann die Lage deshalb erklären.
 */
if (PHP_VERSION_ID < 80100) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Dieses Shop-System benötigt PHP 8.1 oder neuer. Gefunden: PHP ' . PHP_VERSION . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>PHP zu alt</title></head>'
       . '<body style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#16181d;">'
       . '<h1 style="font-size:22px;">Dieser Server nutzt eine zu alte PHP-Version</h1>'
       . '<p style="color:#6b7280;line-height:1.6;">Gefunden: <strong>PHP ' . PHP_VERSION . '</strong>, '
       . 'benötigt wird mindestens <strong>PHP 8.1</strong>. Die Version stellst du im Hosting-Menü um; '
       . 'danach diese Seite neu laden.</p>'
       . '<p><a href="systemcheck.php" style="color:#2f6f4f;">Zum Systemcheck</a></p>'
       . '</body></html>';
    exit;
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Berlin');

/*
 * Ausgabe zwischenspeichern.
 *
 * Die Backend-Seiten geben ihren Seitenkopf aus, bevor sie ein abgeschicktes
 * Formular verarbeiten. Ohne Puffer wäre danach kein "Location"-Header mehr
 * möglich – die Weiterleitung nach dem Speichern liefe ins Leere. Viele Server
 * haben den Puffer ab Werk aus, deshalb schalten wir ihn selbst ein.
 */
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    ob_start();
}

/* Klassen laden – bewusst ohne Composer, damit das System überall läuft. */
foreach ([
    'Config', 'Util', 'DB', 'Log', 'Settings', 'Schema', 'Auth',
    'Medien', 'Artikel', 'Kategorien', 'Inhalte', 'Bausteine', 'Kunden',
    'Steuern', 'Versand', 'Rabatte', 'Bestand', 'Preise', 'Warenkorb',
    'Bestellungen', 'Zahlung', 'Kasse', 'Mail', 'Veroeffentlichung', 'Theme',
] as $klasse) {
    require_once SHOP_ROOT . '/lib/' . $klasse . '.php';
}

/* Fehler nicht an Besucher ausgeben, aber protokollieren. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log('[Shop] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    try {
        Log::error('app', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    } catch (Throwable $ignoriert) {
        // Datenbank eventuell nicht verfügbar
    }
    if (Util::isCli()) {
        fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler</title></head>'
       . '<body style="font-family:Arial,Helvetica,sans-serif;padding:40px;color:#16181d;">'
       . '<h1 style="font-size:20px;">Es ist ein Fehler aufgetreten</h1>'
       . '<p style="color:#6b7280;">Bitte später noch einmal versuchen. Der Vorfall wurde protokolliert.</p>'
       . '</body></html>';
    exit(1);
});

/* Konfiguration laden. */
$konfigurationGeladen = Config::load();

if (!$konfigurationGeladen && !defined('SHOP_INSTALLER')) {
    if (Util::isCli()) {
        fwrite(STDERR, "Der Shop ist noch nicht eingerichtet. Bitte install.php im Browser aufrufen.\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Einrichtung nötig</title></head>'
       . '<body style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:60px auto;padding:0 20px;color:#16181d;">'
       . '<h1 style="font-size:22px;">Der Shop ist noch nicht eingerichtet</h1>'
       . '<p style="color:#6b7280;line-height:1.6;">Bitte einmalig '
       . '<a href="install.php" style="color:#2f6f4f;">install.php</a> im Browser aufrufen. '
       . 'Die Einrichtung dauert etwa zwei Minuten.</p>'
       . '<p style="color:#6b7280;line-height:1.6;">Vorher zeigt der '
       . '<a href="systemcheck.php" style="color:#2f6f4f;">Systemcheck</a>, ob dieser Server alles mitbringt.</p>'
       . '</body></html>';
    exit;
}

if ($konfigurationGeladen) {
    DB::init();

    /*
     * Nach dem Hochladen einer neueren Fassung fehlen der Datenbank unter
     * Umständen neue Spalten oder Tabellen. Das holen wir hier automatisch
     * nach – sonst müsste nach jedem Update von Hand nachgearbeitet werden.
     * Schema::migrate() ist idempotent und läuft nur bei geänderter Version.
     */
    try {
        if (Schema::isInstalled() && Settings::int('schema_version') < Schema::VERSION) {
            Schema::migrate();
            Log::info('schema', 'Datenbank auf Stand ' . Schema::VERSION . ' gebracht (' . SHOP_VERSION . ').');
        }
    } catch (Throwable $e) {
        error_log('[Shop] Aktualisierung der Datenbank fehlgeschlagen: ' . $e->getMessage());
    }
}

/* Sicherheitskopfzeilen für alle Ausgaben im Browser. */
if (!Util::isCli() && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}
