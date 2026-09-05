<?php
/**
 * aufraeumen.php – entfernt Dateien einer früheren Fassung.
 *
 * Beim Aktualisieren per FTP werden alte Dateien überschrieben, aber nie
 * gelöscht. Was es in der neuen Fassung nicht mehr gibt, bleibt liegen. Meist
 * ist das harmlos – mit einer Ausnahme: Eine übrig gebliebene index.html hat
 * bei Apache Vorrang vor der index.php. Dann erscheint die alte Oberfläche,
 * deren Stylesheets und Skripte gelöscht sind (404 in der Browser-Konsole).
 *
 * Diese Seite löscht ausschließlich Pfade aus der fest eingebauten Liste
 * unten. Alles, was zur aktuellen Fassung gehört, kann sie nicht anfassen.
 * Nach getaner Arbeit bitte löschen – sie bietet das selbst an.
 */

/* Schritt 0: PHP-Version prüfen, bevor irgendetwas geladen wird. */
if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>PHP zu alt</title></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;">'
        . '<h1 style="font-size:22px;">Dieser Server nutzt eine zu alte PHP-Version</h1>'
        . '<p>Gefunden: <strong>PHP ' . PHP_VERSION . '</strong>, benötigt wird mindestens <strong>PHP 8.1</strong>.</p>'
        . '<p><a href="systemcheck.php">Zum Systemcheck</a></p></body></html>';
    exit;
}

define('SHOP_INSTALLER', true); // Bootstrap soll auch ohne config.php durchlaufen.
require __DIR__ . '/lib/bootstrap.php';

@ini_set('display_errors', '1');

/**
 * Reste der Node-Fassung. Ausschließlich diese Pfade dürfen gelöscht werden.
 * Ordner werden mitsamt Inhalt entfernt.
 */
const ALTLASTEN = [
    'admin/css', 'admin/js', 'src', 'public', 'tests', 'node_modules', 'coverage',
    'package.json', 'package-lock.json', '.env', '.env.example',
    'docs/API.md', 'data/.gitkeep',
];

/**
 * Pfade der aktuellen Fassung. Doppelter Boden: Selbst wenn oben einmal etwas
 * Falsches stünde, bleiben diese unangetastet.
 */
const GESCHUETZT = [
    '', '.', '..', '.htaccess', 'config.php', 'data', 'uploads', 'lib', 'assets',
    'admin', 'docs', 'index.php', 'install.php', 'systemcheck.php', 'aufraeumen.php',
    'admin/assets', 'admin/partials',
];

/** Sucht, was tatsächlich auf dem Server liegt. */
function gefundeneAltlasten(): array
{
    $gefunden = [];

    /*
     * Eine index.html neben einer index.php: der eigentliche Übeltäter. Wir
     * suchen sie dort, wo die aktuelle Fassung eine index.php hat – so trifft
     * es nie eine index.html, die zur neuen Fassung gehört.
     */
    foreach (['', 'admin'] as $bereich) {
        $ordner = SHOP_ROOT . ($bereich === '' ? '' : '/' . $bereich);
        if (is_file($ordner . '/index.html') && is_file($ordner . '/index.php')) {
            $gefunden[] = ($bereich === '' ? '' : $bereich . '/') . 'index.html';
        }
    }

    foreach (ALTLASTEN as $pfad) {
        if (file_exists(SHOP_ROOT . '/' . $pfad)) {
            $gefunden[] = $pfad;
        }
    }

    return $gefunden;
}

/** Darf dieser Pfad gelöscht werden? Drei Schranken, alle müssen halten. */
function darfWeg(string $rel, array $erlaubt): bool
{
    if (!in_array($rel, $erlaubt, true) || in_array($rel, GESCHUETZT, true)) {
        return false;
    }
    $echt = realpath(SHOP_ROOT . '/' . $rel);
    $wurzel = realpath(SHOP_ROOT);
    return $echt !== false && $wurzel !== false && $echt !== $wurzel
        && str_starts_with($echt, $wurzel . DIRECTORY_SEPARATOR);
}

/** Löscht Datei oder Ordner samt Inhalt. Gibt die Anzahl gelöschter Dateien zurück. */
function entfernen(string $pfad): int
{
    if (is_link($pfad) || is_file($pfad)) {
        return @unlink($pfad) ? 1 : 0;
    }
    if (!is_dir($pfad)) {
        return 0;
    }
    $anzahl = 0;
    foreach (scandir($pfad) ?: [] as $eintrag) {
        if ($eintrag !== '.' && $eintrag !== '..') {
            $anzahl += entfernen($pfad . '/' . $eintrag);
        }
    }
    @rmdir($pfad);
    return $anzahl;
}

/* ------------------------------------------------------------- Zugriff */

/*
 * Ist der Shop eingerichtet, muss man angemeldet sein. Vor der Einrichtung
 * gibt es noch keinen Zugang – dann ist die Seite offen, genau wie install.php.
 * Zu holen gibt es hier ohnehin nichts: Die Liste oben enthält nur Dateien,
 * die zur aktuellen Fassung nicht gehören.
 */
$eingerichtet = Config::isInstalled();
$angemeldet = false;
if ($eingerichtet) {
    try {
        $angemeldet = Auth::darf('einstellen');
    } catch (Throwable $e) {
        $angemeldet = false;
    }
}

$stil = '<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
  background:#f1f2f4;color:#16181d;margin:0;padding:40px 20px;line-height:1.55}
.karte{max-width:660px;margin:0 auto;background:#fff;border-radius:12px;padding:34px;
  box-shadow:0 4px 24px rgba(0,0,0,.07)}
h1{font-size:23px;margin:0 0 6px}
.unter{color:#6b7280;margin:0 0 22px}
.liste{list-style:none;padding:0;margin:0 0 22px}
.liste li{padding:9px 0;border-bottom:1px solid #e5e7eb;display:flex;gap:10px;align-items:center;font-size:14px}
.liste li:last-child{border-bottom:0}
.ja{color:#008060;font-weight:700}.nein{color:#d72c0d;font-weight:700}
.pfad{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px}
.art{color:#6b7280;font-size:13px;margin-left:auto}
.knopf{display:inline-block;background:#16181d;color:#fff;border:1px solid #16181d;padding:12px 24px;
  border-radius:7px;font:inherit;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:8px}
.knopf:hover{background:#000}
.knopf-leer{background:transparent;color:#16181d;border-color:#c9cccf}
.knopf-leer:hover{background:#f6f6f7}
.hinweis{padding:13px 16px;border-radius:8px;margin:18px 0;font-size:14px;line-height:1.6}
.hinweis-warnung{background:#fff4e4;color:#8a6800}
.hinweis-fehler{background:#fdecea;color:#8a1c12}
.hinweis-info{background:#eaf4ff;color:#1a4b8c}
.haken{width:52px;height:52px;border-radius:50%;background:#e3f1df;color:#008060;
  display:grid;place-items:center;font-size:27px;margin-bottom:16px}
code{background:#f1f2f4;padding:2px 6px;border-radius:4px;font-size:13px}
</style>';

function seite(string $titel, string $inhalt): never
{
    global $stil;
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . Util::e($titel) . '</title>' . $stil . '</head><body><div class="karte">'
       . $inhalt . '</div></body></html>';
    exit;
}

if ($eingerichtet && !$angemeldet) {
    // "weiter" muss ein Pfad ab Serverwurzel sein - der Shop kann in einem
    // Unterordner liegen, dann reicht "/aufraeumen.php" nicht.
    $zielPfad = (string) (parse_url(Config::url('aufraeumen.php'), PHP_URL_PATH) ?: '/aufraeumen.php');
    $anmeldeUrl = Config::url('admin/login.php') . '?weiter=' . rawurlencode($zielPfad);

    seite('Anmeldung nötig',
        '<h1>Bitte anmelden</h1>'
        . '<p class="unter">Zum Aufräumen brauchst du einen Zugang mit Administratorrechten.</p>'
        . '<a class="knopf" href="' . Util::e($anmeldeUrl) . '">Zur Anmeldung</a>');
}

/* -------------------------------------------------------------- Handeln */

$gefunden = gefundeneAltlasten();
$aktion = (string) ($_POST['aktion'] ?? '');

if ($aktion !== '' && $eingerichtet) {
    Auth::csrfPruefen();
}

if ($aktion === 'selbst') {
    @unlink(__FILE__);
    seite('Fertig',
        '<div class="haken">✓</div><h1>aufraeumen.php ist gelöscht</h1>'
        . '<p class="unter">Damit ist alles erledigt.</p>'
        . '<a class="knopf" href="' . Util::e(Config::url('admin/')) . '">Zum Backend</a> '
        . '<a class="knopf knopf-leer" href="systemcheck.php">Systemcheck</a>');
}

if ($aktion === 'loeschen') {
    $geloescht = [];
    $fehler = [];
    $dateien = 0;

    foreach ((array) ($_POST['pfade'] ?? []) as $rel) {
        $rel = (string) $rel;
        if (!darfWeg($rel, $gefunden)) {
            continue;
        }
        $anzahl = entfernen(SHOP_ROOT . '/' . $rel);
        if (file_exists(SHOP_ROOT . '/' . $rel)) {
            $fehler[] = $rel;
        } else {
            $geloescht[] = $rel;
            $dateien += $anzahl;
        }
    }

    if ($eingerichtet && $geloescht !== []) {
        try {
            Log::info('wartung', 'Reste der alten Fassung entfernt: ' . implode(', ', $geloescht));
        } catch (Throwable $e) {
            // Protokoll ist nicht kriegsentscheidend.
        }
    }

    $inhalt = $fehler === []
        ? '<div class="haken">✓</div><h1>Aufgeräumt</h1>'
          . '<p class="unter">' . count($geloescht) . ' Einträge entfernt (' . $dateien . ' Dateien). '
          . 'Das Backend zeigt jetzt wieder die richtige Oberfläche.</p>'
        : '<h1>Teilweise aufgeräumt</h1>'
          . '<p class="unter">' . count($geloescht) . ' Einträge entfernt, '
          . count($fehler) . ' nicht.</p>';

    if ($geloescht !== []) {
        $inhalt .= '<ul class="liste">';
        foreach ($geloescht as $rel) {
            $inhalt .= '<li><span class="ja">✓</span><span class="pfad">' . Util::e($rel) . '</span></li>';
        }
        $inhalt .= '</ul>';
    }
    if ($fehler !== []) {
        $inhalt .= '<div class="hinweis hinweis-fehler"><strong>Diese Pfade ließen sich nicht löschen:</strong> '
                 . Util::e(implode(', ', $fehler))
                 . '<br>Meist fehlen die Schreibrechte. Bitte per FTP von Hand löschen.</div>';
    }

    $inhalt .= '<div class="hinweis hinweis-info">Diese Datei wird jetzt nicht mehr gebraucht. '
             . 'Lösch sie – sonst liegt ein Werkzeug offen herum, das Dateien entfernen kann.</div>'
             . '<form method="post"><input type="hidden" name="aktion" value="selbst">'
             . ($eingerichtet ? Auth::csrfFeld() : '')
             . '<button class="knopf" type="submit">aufraeumen.php jetzt löschen</button></form>'
             . '<p style="margin-top:18px"><a href="systemcheck.php">Systemcheck öffnen</a></p>';

    seite('Aufgeräumt', $inhalt);
}

/* -------------------------------------------------------------- Anzeigen */

if ($gefunden === []) {
    seite('Nichts zu tun',
        '<div class="haken">✓</div><h1>Es liegen keine alten Dateien herum</h1>'
        . '<p class="unter">Der Shop-Ordner enthält nur Dateien der aktuellen Fassung.</p>'
        . '<div class="hinweis hinweis-info">Diese Datei wird nicht mehr gebraucht.</div>'
        . '<form method="post"><input type="hidden" name="aktion" value="selbst">'
        . ($eingerichtet ? Auth::csrfFeld() : '')
        . '<button class="knopf" type="submit">aufraeumen.php löschen</button></form>'
        . '<p style="margin-top:18px"><a href="systemcheck.php">Systemcheck öffnen</a></p>');
}

$inhalt = '<h1>Reste einer früheren Fassung</h1>'
    . '<p class="unter">Diese Dateien gehören nicht zur aktuellen Fassung. Sie stammen vom '
    . 'Aktualisieren per FTP – dabei werden Dateien überschrieben, aber nie gelöscht.</p>'
    . '<ul class="liste">';

foreach ($gefunden as $rel) {
    $voll = SHOP_ROOT . '/' . $rel;
    $art = is_dir($voll) ? 'Ordner' : 'Datei';
    $inhalt .= '<li><span class="nein">✕</span><span class="pfad">' . Util::e($rel) . '</span>'
             . '<span class="art">' . $art . '</span></li>';
}

$inhalt .= '</ul>';

if (in_array('admin/index.html', $gefunden, true) || in_array('index.html', $gefunden, true)) {
    $inhalt .= '<div class="hinweis hinweis-warnung"><strong>Das ist die Ursache deiner Fehlermeldungen.</strong> '
             . 'Apache liefert eine <code>index.html</code> bevorzugt aus, noch vor der <code>index.php</code>. '
             . 'Deshalb siehst du die alte Oberfläche, die ihre längst gelöschten Dateien nachlädt.</div>';
}

$inhalt .= '<div class="hinweis hinweis-info"><code>config.php</code>, <code>data/</code> und '
    . '<code>uploads/</code> werden nicht angerührt. Deine Einstellungen, Artikel, Bestellungen '
    . 'und Bilder bleiben, wie sie sind.</div>'
    . '<form method="post">'
    . '<input type="hidden" name="aktion" value="loeschen">'
    . ($eingerichtet ? Auth::csrfFeld() : '');

foreach ($gefunden as $rel) {
    $inhalt .= '<input type="hidden" name="pfade[]" value="' . Util::e($rel) . '">';
}

$inhalt .= '<button class="knopf" type="submit">' . count($gefunden) . ' Einträge löschen</button> '
    . '<a class="knopf knopf-leer" href="systemcheck.php">Abbrechen</a>'
    . '</form>';

seite('Aufräumen', $inhalt);
