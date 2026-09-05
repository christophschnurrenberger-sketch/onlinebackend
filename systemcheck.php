<?php
/**
 * systemcheck.php – zeigt, ob dieser Server alles mitbringt.
 *
 * Läuft absichtlich auch OHNE config.php und ohne Datenbank: das ist die Seite,
 * die weiterhilft, wenn die Einrichtung scheitert. Sie lädt deshalb so wenig
 * wie möglich und prüft alles selbst.
 */

@ini_set('display_errors', '1');
error_reporting(E_ALL);

/*
 * Diese Seite muss auch auf einem zu alten PHP noch etwas anzeigen – sie ist ja
 * genau dann gefragt. Die beiden Funktionen unten gibt es erst ab PHP 8.0;
 * ohne Ersatz endete der Systemcheck auf älteren Servern mit einer weißen Seite.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$wurzel = __DIR__;
$hatConfig = is_file($wurzel . '/config.php');

/** Ein Prüfpunkt. */
function pruefung(string $name, bool $ok, string $hinweis = '', bool $optional = false): array
{
    return ['name' => $name, 'ok' => $ok, 'hinweis' => $hinweis, 'optional' => $optional];
}

/* ------------------------------------------------------------------ PHP */

$php = [
    pruefung(
        'PHP-Version 8.1 oder neuer',
        PHP_VERSION_ID >= 80100,
        'Gefunden: PHP ' . PHP_VERSION . '. Die Version stellst du im Hosting-Menü um '
        . '(IONOS: „Websites & Shops“ → Website → „PHP verwalten“; Strato und All-Inkl: „PHP-Version“).'
    ),
    pruefung('PDO (Datenbankzugriff)', extension_loaded('pdo'),
        'Ohne PDO kann das System keine Datenbank ansprechen. Beim Hoster nachfragen.'),
    pruefung('pdo_sqlite (Datei-Datenbank)', extension_loaded('pdo_sqlite'),
        'Ohne SQLite brauchst du eine MySQL-Datenbank vom Hoster.', true),
    pruefung('pdo_mysql (MySQL/MariaDB)', extension_loaded('pdo_mysql'),
        'Nur nötig, wenn du MySQL statt SQLite verwenden willst.', true),
    pruefung('mbstring (Umlaute)', extension_loaded('mbstring'),
        'Ohne mbstring werden Umlaute falsch dargestellt.'),
    pruefung('openssl (Verschlüsselung)', extension_loaded('openssl'),
        'Wird gebraucht, um Zugangsdaten für Stripe, PayPal und SMTP verschlüsselt zu speichern.'),
    pruefung('json', extension_loaded('json'), 'Gehört zum PHP-Standard.'),
    pruefung('gd (Bildprüfung)', extension_loaded('gd'),
        'Wird beim Bild-Upload benutzt, um den tatsächlichen Dateityp zu erkennen.'),
    pruefung('cURL (Zahlungsanbieter)', function_exists('curl_init'),
        'Ohne cURL versucht das System es über Streams – funktioniert meist auch, ist aber langsamer.', true),
    pruefung('Ausgehende HTTPS-Verbindungen erlaubt',
        function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
        'Ohne ausgehende Verbindungen sind Stripe und PayPal nicht nutzbar. '
        . 'Rechnung, Vorkasse und Nachnahme funktionieren trotzdem.', true),
    pruefung('mail() verfügbar', function_exists('mail'),
        'Für Bestellbestätigungen. Alternativ lässt sich im Backend ein SMTP-Zugang hinterlegen.', true),
];

/* -------------------------------------------------------------- Schreiben */

$ordner = [];
foreach ([
    ''         => 'Shop-Ordner (für config.php)',
    '/data'    => 'data (Datenbank bei SQLite)',
    '/uploads' => 'uploads (hochgeladene Bilder)',
] as $pfad => $bezeichnung) {
    $voll = $wurzel . $pfad;
    if (!is_dir($voll)) {
        $ordner[] = pruefung($bezeichnung, false, 'Der Ordner fehlt. Bitte per FTP anlegen und Rechte 755 setzen.');
        continue;
    }
    $ordner[] = pruefung(
        $bezeichnung,
        is_writable($voll),
        'Nicht beschreibbar. Im FTP-Programm die Rechte auf 755 setzen (bei manchen Hostern 775).'
    );
}

/* --------------------------------------------------------------- Grenzen */

$grenzen = [
    'Maximale Upload-Größe'   => ini_get('upload_max_filesize') ?: '?',
    'Maximale Formulargröße'  => ini_get('post_max_size') ?: '?',
    'Speicherbegrenzung'      => ini_get('memory_limit') ?: '?',
    'Maximale Laufzeit'       => (ini_get('max_execution_time') ?: '?') . ' s',
    'Zeitzone'                => date_default_timezone_get(),
    'Webserver'               => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unbekannt'),
    'PHP-Anbindung'           => PHP_SAPI,
];

/* --------------------------------------------------- Zustand der Anwendung */

$anwendung = [];
if ($hatConfig) {
    try {
        define('SHOP_INSTALLER', true);
        require $wurzel . '/lib/bootstrap.php';

        $anwendung[] = pruefung('config.php lesbar', Config::isInstalled());
        $anwendung[] = pruefung('Basis-Adresse gesetzt', Config::baseUrl() !== '',
            'Aktuell: ' . (Config::baseUrl() ?: '– nicht gesetzt –'));

        try {
            DB::init();
            DB::pdo()->query('SELECT 1');
            $anwendung[] = pruefung('Datenbank erreichbar (' . DB::driver() . ')', true);

            $installiert = Schema::isInstalled();
            $anwendung[] = pruefung('Tabellen angelegt', $installiert,
                $installiert ? '' : 'Bitte install.php aufrufen.');

            if ($installiert) {
                $anwendung[] = pruefung('Mindestens ein Zugang vorhanden', Auth::anzahl() > 0,
                    'Ohne Zugang kommst du nicht ins Backend – install.php legt einen an.');
                $anwendung[] = pruefung('Steuersatz hinterlegt', Steuern::liste() !== [],
                    'Unter Einstellungen → Steuern anlegen.');
                $anwendung[] = pruefung('Versandzone hinterlegt', Versand::zonen() !== [],
                    'Ohne Versandzone kann niemand bestellen. Unter Einstellungen → Versand anlegen.');
                $anwendung[] = pruefung('Zahlart aktiviert', Zahlung::verfuegbare() !== [],
                    'Unter Einstellungen → Zahlungen mindestens eine Zahlart aktivieren.');
                $anwendung[] = pruefung('Absenderadresse für E-Mails',
                    Settings::get('mail_absender') !== '',
                    'Ohne Absender werden keine Bestellbestätigungen verschickt.', true);

                $version = Veroeffentlichung::liveVersion();
                $anwendung[] = pruefung('Shop veröffentlicht', $version > 0,
                    $version > 0 ? 'Aktuell live: Fassung ' . $version
                                 : 'Im Backend auf „Veröffentlichen“ klicken – vorher sehen Besucher nichts.');
            }
        } catch (Throwable $e) {
            $anwendung[] = pruefung('Datenbank erreichbar', false, $e->getMessage());
        }
    } catch (Throwable $e) {
        $anwendung[] = pruefung('Programmdateien ladbar', false, $e->getMessage());
    }
}

/*
 * Ob die Datenbankdatei über das Internet abrufbar ist, prüft der Browser
 * weiter unten selbst.
 *
 * Ein Selbstaufruf vom Server aus wäre naheliegend, blockiert aber auf Hostern
 * mit nur einem Arbeitsprozess: die Anfrage wartet auf sich selbst. Der Browser
 * hat das Problem nicht – und testet nebenbei genau das, was ein echter
 * Besucher zu sehen bekäme.
 *
 * Die Prüfung ist wichtig, weil die mitgelieferte .htaccess nur auf
 * Apache-Servern gilt. Auf nginx wird sie ignoriert; dort wäre die Datenbank
 * ohne weitere Konfiguration herunterladbar – und damit lägen alle Kunden- und
 * Bestelldaten offen.
 */
$dbPfad = null;
if ($hatConfig && class_exists('Config')) {
    $konfiguriert = (string) Config::get('db.path', '');
    if ($konfiguriert !== '' && str_starts_with($konfiguriert, $wurzel)) {
        $dbPfad = ltrim(str_replace('\\', '/', substr($konfiguriert, strlen($wurzel))), '/');
    }
}

$sicherheit = [];

$sicherheit[] = pruefung('install.php entfernt', !is_file($wurzel . '/install.php'),
        'Nach der Einrichtung bitte löschen – sonst könnte jemand den Shop neu aufsetzen.',
    !$hatConfig);

$sicherheit[] = pruefung('Verbindung verschlüsselt (HTTPS)',
        (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'Ohne HTTPS wandern Kundenadressen und Anmeldedaten im Klartext durchs Netz. '
        . 'Bei fast allen Hostern lässt sich ein kostenloses Zertifikat aktivieren.');

/*
 * Die Schutzregeln bestehen aus mehreren Dateien: eine im Shop-Ordner und je
 * eine in data/, lib/, uploads/ und admin/partials/. FTP-Programme blenden
 * Dateien, die mit einem Punkt beginnen, oft aus – dann landen sie beim
 * Hochladen nicht auf dem Server. Deshalb prüfen wir jede einzeln.
 */
$schutzdateien = ['.htaccess', 'data/.htaccess', 'lib/.htaccess', 'uploads/.htaccess', 'admin/partials/.htaccess'];
$fehlendeSchutzdateien = [];
foreach ($schutzdateien as $datei) {
    if (!is_file($wurzel . '/' . $datei)) {
        $fehlendeSchutzdateien[] = $datei;
    }
}
$istApache = stripos((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''), 'apache') !== false;

$sicherheit[] = pruefung('Schutzregeln (.htaccess) vollständig', $fehlendeSchutzdateien === [],
    $fehlendeSchutzdateien === []
        ? 'Gefunden: alle ' . count($schutzdateien) . ' Dateien.'
        : 'Es fehlen: ' . implode(', ', $fehlendeSchutzdateien) . '. Im FTP-Programm die Anzeige '
          . 'versteckter Dateien einschalten und erneut hochladen.',
    !$istApache);

/** Zählt, was wirklich fehlt (Optionales zählt nicht als Fehler). */
$fehlend = 0;
foreach ([$php, $ordner, $anwendung, $sicherheit] as $gruppe) {
    foreach ($gruppe as $eintrag) {
        if (!$eintrag['ok'] && !$eintrag['optional']) {
            $fehlend++;
        }
    }
}

function block(string $titel, array $eintraege): void
{
    if ($eintraege === []) {
        return;
    }
    echo '<h2>' . htmlspecialchars($titel, ENT_QUOTES, 'UTF-8') . '</h2><ul class="liste">';
    foreach ($eintraege as $e) {
        $klasse = $e['ok'] ? 'ja' : ($e['optional'] ? 'egal' : 'nein');
        $symbol = $e['ok'] ? '✓' : ($e['optional'] ? '○' : '✕');
        echo '<li><span class="' . $klasse . '">' . $symbol . '</span><div><strong>'
           . htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
        if ($e['hinweis'] !== '' && (!$e['ok'] || str_contains($e['hinweis'], 'Aktuell') || str_contains($e['hinweis'], 'Gefunden'))) {
            echo '<div class="tipp">' . htmlspecialchars($e['hinweis'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
        echo '</div></li>';
    }
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Systemcheck</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
  background:#f1f2f4;color:#16181d;margin:0;padding:40px 20px;line-height:1.55}
.karte{max-width:720px;margin:0 auto;background:#fff;border-radius:12px;padding:34px;
  box-shadow:0 4px 24px rgba(0,0,0,.07)}
h1{font-size:23px;margin:0 0 6px}
h2{font-size:15px;margin:28px 0 10px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}
.unter{color:#6b7280;margin:0 0 20px}
.liste{list-style:none;padding:0;margin:0}
.liste li{padding:11px 0;border-bottom:1px solid #e5e7eb;display:flex;gap:12px;align-items:flex-start;font-size:14px}
.liste li:last-child{border-bottom:0}
.ja{color:#008060;font-weight:700;width:16px;flex-shrink:0}
.nein{color:#d72c0d;font-weight:700;width:16px;flex-shrink:0}
.egal{color:#8c9196;font-weight:700;width:16px;flex-shrink:0}
.tipp{font-size:13px;color:#6b7280;margin-top:3px}
.fazit{padding:15px 18px;border-radius:8px;margin:0 0 22px;font-size:14.5px;line-height:1.6}
.fazit-ok{background:#e3f1df;color:#008060}
.fazit-fehler{background:#fdecea;color:#8a1c12}
table{width:100%;border-collapse:collapse;font-size:14px}
td{padding:8px 0;border-bottom:1px solid #e5e7eb}
td:last-child{text-align:right;font-variant-numeric:tabular-nums;color:#6b7280}
.knopf{display:inline-block;background:#16181d;color:#fff;padding:11px 22px;border-radius:7px;
  text-decoration:none;font-weight:600;font-size:15px;margin-top:20px}
.knopf:hover{background:#000}
.fuss{margin-top:26px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:13px;color:#8c9196}
</style>
</head>
<body>
<div class="karte">
  <h1>Systemcheck</h1>
  <p class="unter">Zeigt, ob dieser Server alles mitbringt, was der Shop braucht.</p>

  <?php if ($fehlend === 0): ?>
    <div class="fazit fazit-ok"><strong>Alles in Ordnung.</strong>
      <?= $hatConfig ? 'Der Shop ist eingerichtet und einsatzbereit.'
                     : 'Du kannst den Shop jetzt einrichten.' ?></div>
  <?php else: ?>
    <div class="fazit fazit-fehler"><strong><?= $fehlend ?> Punkt<?= $fehlend === 1 ? '' : 'e' ?>
      brauch<?= $fehlend === 1 ? 't' : 'en' ?> noch Aufmerksamkeit.</strong>
      Die Einzelheiten stehen unten – ein ○ ist nur ein Hinweis, kein Fehler.</div>
  <?php endif; ?>

  <?php
  block('PHP', $php);
  block('Schreibrechte', $ordner);
  if ($anwendung !== []) {
      block('Shop', $anwendung);
  }
  block('Sicherheit', $sicherheit);

  if ($dbPfad !== null): ?>
    <ul class="liste" id="db-pruefung">
      <li><span class="egal" id="db-symbol">○</span><div>
        <strong>Datenbankdatei nicht über das Internet abrufbar</strong>
        <div class="tipp" id="db-text">wird geprüft …</div>
      </div></li>
    </ul>
    <script>
    (function () {
      var pfad = <?= json_encode($dbPfad, JSON_UNESCAPED_SLASHES) ?>;
      var symbol = document.getElementById('db-symbol');
      var text = document.getElementById('db-text');

      function ergebnis(klasse, zeichen, meldung) {
        symbol.className = klasse;
        symbol.textContent = zeichen;
        text.innerHTML = meldung;
      }

      fetch(pfad, { method: 'HEAD', cache: 'no-store' })
        .then(function (antwort) {
          if (antwort.ok) {
            ergebnis('nein', '✕',
              '<strong>Die Datenbank ist herunterladbar.</strong> Damit liegen alle Kunden- und ' +
              'Bestelldaten offen. Auf Apache-Servern hilft die mitgelieferte .htaccess; bei nginx ' +
              'muss der Hoster den Zugriff auf die Ordner <code>data</code> und <code>lib</code> ' +
              'sperren. Alternativ die Datenbank über <code>config.php</code> in einen Ordner ' +
              'außerhalb des Web-Verzeichnisses legen.');
          } else {
            ergebnis('ja', '✓', 'Der Zugriff wird abgewiesen (Status ' + antwort.status + ').');
          }
        })
        .catch(function () {
          ergebnis('ja', '✓', 'Der Zugriff wird abgewiesen.');
        });
    })();
    </script>
  <?php endif; ?>

  <h2>Grenzwerte des Servers</h2>
  <table>
    <?php foreach ($grenzen as $name => $wert): ?>
      <tr><td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $wert, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endforeach; ?>
  </table>

  <?php if (!$hatConfig): ?>
    <a class="knopf" href="install.php">Shop einrichten</a>
  <?php else: ?>
    <a class="knopf" href="admin/">Zum Backend</a>
  <?php endif; ?>

  <div class="fuss">
    Shop-System <?= defined('SHOP_VERSION') ? htmlspecialchars(SHOP_VERSION, ENT_QUOTES, 'UTF-8') : '' ?>
    · PHP <?= PHP_VERSION ?> · <?= htmlspecialchars(PHP_OS_FAMILY, ENT_QUOTES, 'UTF-8') ?>
  </div>
</div>
</body>
</html>
