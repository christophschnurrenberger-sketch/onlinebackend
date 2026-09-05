<?php
/**
 * install.php – einmalige Einrichtung.
 *
 * Legt config.php an, erstellt die Datenbanktabellen, Steuersätze,
 * Versandzonen, Rechtsseiten und den ersten Zugang. Danach sollte diese Datei
 * gelöscht werden – das Backend weist darauf hin.
 */

/*
 * Schritt 0: PHP-Version prüfen, BEVOR das eigentliche System geladen wird.
 * Bei zu altem PHP ließen sich die Programmdateien gar nicht erst lesen – das
 * Ergebnis wäre eine weiße Seite ohne jede Erklärung.
 */
if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>PHP zu alt</title></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#16181d;">'
        . '<h1 style="font-size:22px;">Dieser Server nutzt eine zu alte PHP-Version</h1>'
        . '<p style="color:#6b7280;line-height:1.6;">Gefunden: <strong>PHP ' . PHP_VERSION . '</strong>. '
        . 'Das Shop-System benötigt mindestens <strong>PHP 8.1</strong>.</p>'
        . '<p style="color:#6b7280;line-height:1.6;">Die PHP-Version stellst du im Hosting-Menü um '
        . '(bei IONOS: „Websites &amp; Shops“ → deine Website → „PHP verwalten“, '
        . 'bei Strato und All-Inkl unter „PHP-Version“). Danach diese Seite neu laden.</p>'
        . '<p><a href="systemcheck.php" style="color:#2f6f4f;">Zum Systemcheck</a></p>'
        . '</body></html>';
    exit;
}

/*
 * Fehler während der Einrichtung sichtbar machen. Ohne das bliebe die Seite bei
 * einem Problem einfach leer – der häufigste Grund für Ratlosigkeit.
 * Nach der Einrichtung wird install.php gelöscht, es bleibt also nichts offen.
 */
@ini_set('display_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $fehler = error_get_last();
    if ($fehler === null || !in_array($fehler['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    echo '<div style="font-family:Arial,Helvetica,sans-serif;max-width:760px;margin:24px auto;padding:18px 22px;'
        . 'background:#fdecea;border:1px solid #f5c6c1;border-radius:8px;color:#8a1c12;line-height:1.6;">'
        . '<strong>Die Einrichtung wurde durch einen Fehler abgebrochen:</strong><br>'
        . htmlspecialchars($fehler['message'], ENT_QUOTES, 'UTF-8')
        . '<br><span style="font-size:13px;">in ' . htmlspecialchars(basename((string) $fehler['file']), ENT_QUOTES, 'UTF-8')
        . ', Zeile ' . (int) $fehler['line'] . '</span>'
        . '<p style="margin:12px 0 0;"><a href="systemcheck.php" style="color:#8a1c12;">Systemcheck öffnen</a> – '
        . 'dort steht, was auf diesem Server fehlt.</p></div>';
});

define('SHOP_INSTALLER', true);
require __DIR__ . '/lib/bootstrap.php';

// bootstrap.php schaltet die Fehleranzeige ab (richtig für den Betrieb).
// Für die einmalige Einrichtung schalten wir sie wieder ein.
@ini_set('display_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    echo '<div style="font-family:Arial,Helvetica,sans-serif;max-width:760px;margin:24px auto;padding:18px 22px;'
        . 'background:#fdecea;border:1px solid #f5c6c1;border-radius:8px;color:#8a1c12;line-height:1.6;">'
        . '<strong>Fehler bei der Einrichtung:</strong><br>'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '<br><span style="font-size:13px;">in ' . htmlspecialchars(basename($e->getFile()), ENT_QUOTES, 'UTF-8')
        . ', Zeile ' . $e->getLine() . '</span>'
        . '<p style="margin:12px 0 0;"><a href="systemcheck.php" style="color:#8a1c12;">Systemcheck öffnen</a></p></div>';
    exit(1);
});

/* ------------------------------------------------- Bereits eingerichtet? */

if (Config::isInstalled()) {
    try {
        DB::init();
        if (Schema::isInstalled() && Auth::anzahl() > 0) {
            http_response_code(403);
            echo fertigSeite();
            exit;
        }
    } catch (Throwable $e) {
        // Konfiguration vorhanden, Datenbank aber noch leer → weiter einrichten.
    }
}

/* ------------------------------------------------------- Voraussetzungen */

$vorbedingungen = [
    'PHP 8.1 oder neuer'                => PHP_VERSION_ID >= 80100,
    'PDO (Datenbankzugriff)'            => extension_loaded('pdo'),
    'SQLite oder MySQL für PDO'         => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
    'mbstring (Umlaute)'                => extension_loaded('mbstring'),
    'openssl (Verschlüsselung)'         => extension_loaded('openssl'),
    'Ordner beschreibbar'               => is_writable(__DIR__),
];
$bereit = !in_array(false, $vorbedingungen, true);

/* --------------------------------------------------------- Formularwerte */

$fehler = [];
$werte  = [
    'base_url'     => Util::erratenenBasisUrl(),
    'db_treiber'   => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql',
    'db_host'      => 'localhost',
    'db_name'      => '',
    'db_user'      => '',
    'db_pass'      => '',
    'shop_name'    => '',
    'admin_name'   => '',
    'admin_email'  => '',
    'absender'     => '',
    'beispiele'    => '1',
];

if (Util::isPost() && $bereit) {
    foreach (array_keys($werte) as $feld) {
        $werte[$feld] = Util::post($feld, $werte[$feld]);
    }
    $werte['db_pass']   = Util::postRaw('db_pass');
    $werte['beispiele'] = Util::postBool('beispiele') ? '1' : '0';
    $passwort           = Util::postRaw('admin_pass');
    $passwort2          = Util::postRaw('admin_pass2');

    /* --- Eingaben prüfen ------------------------------------------------- */

    if ($werte['base_url'] === '' || !preg_match('#^https?://#', $werte['base_url'])) {
        $fehler['base_url'] = 'Bitte die vollständige Adresse angeben, beginnend mit http:// oder https://';
    }
    if ($werte['shop_name'] === '') {
        $fehler['shop_name'] = 'Bitte einen Namen für den Shop angeben.';
    }
    if (!Util::isEmail($werte['admin_email'])) {
        $fehler['admin_email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }
    if ($passwort !== $passwort2) {
        $fehler['admin_pass'] = 'Die beiden Passwörter stimmen nicht überein.';
    } else {
        $problem = Auth::passwortProblem($passwort, $werte['admin_email']);
        if ($problem !== '') {
            $fehler['admin_pass'] = $problem;
        }
    }
    if ($werte['db_treiber'] === 'mysql') {
        foreach (['db_name' => 'Datenbankname', 'db_user' => 'Benutzername'] as $feld => $label) {
            if ($werte[$feld] === '') {
                $fehler[$feld] = 'Bitte den ' . $label . ' der Datenbank angeben.';
            }
        }
    }

    /* --- Datenbankverbindung testen -------------------------------------- */

    $dbKonfiguration = $werte['db_treiber'] === 'mysql'
        ? [
            'driver' => 'mysql',
            'host'   => $werte['db_host'] ?: 'localhost',
            'port'   => 3306,
            'name'   => $werte['db_name'],
            'user'   => $werte['db_user'],
            'pass'   => $werte['db_pass'],
        ]
        : ['driver' => 'sqlite', 'path' => __DIR__ . '/data/shop.sqlite'];

    if ($fehler === []) {
        try {
            DB::reset();
            DB::init($dbKonfiguration);
            DB::pdo()->query('SELECT 1');
        } catch (Throwable $e) {
            $fehler['db'] = $werte['db_treiber'] === 'mysql'
                ? 'Die Datenbank ist nicht erreichbar: ' . $e->getMessage()
                  . ' – bitte Servername, Datenbankname, Benutzer und Passwort im Hosting-Menü nachsehen.'
                : 'Die Datenbankdatei konnte nicht angelegt werden: ' . $e->getMessage()
                  . ' – bitte prüfen, ob der Ordner „data“ beschreibbar ist (Rechte 755).';
        }
    }

    /* --- Einrichten ------------------------------------------------------- */

    if ($fehler === []) {
        Config::write([
            'base_url' => rtrim($werte['base_url'], '/'),
            'secret'   => bin2hex(random_bytes(32)),
            'db'       => $dbKonfiguration,
        ]);
        Config::load();
        DB::reset();
        DB::init();

        Schema::migrate();
        Auth::anlegen($werte['admin_email'], $passwort, $werte['admin_name'] ?: 'Inhaber', 'inhaber');
        grunddatenAnlegen($werte);
        if ($werte['beispiele'] === '1') {
            beispieleAnlegen();
        }
        Veroeffentlichung::veroeffentlichen('Erste Veröffentlichung bei der Einrichtung');
        Log::info('install', 'Shop eingerichtet (' . SHOP_VERSION . ').');

        echo erfolgSeite($werte);
        exit;
    }
}

/* ---------------------------------------------------------- Grunddaten */

function grunddatenAnlegen(array $werte): void
{
    Steuern::anlegen('Standard (19 %)', '19', 'DE', true);
    Steuern::anlegen('Ermäßigt (7 %)', '7', 'DE');

    Versand::zoneAnlegen('Deutschland', ['DE'], [
        ['name' => 'Standardversand', 'preis' => '4,90', 'lieferzeit' => '2–3 Werktage', 'frei_ab' => '75,00'],
        ['name' => 'Expressversand', 'preis' => '11,90', 'lieferzeit' => 'Nächster Werktag', 'position' => 1],
    ]);
    Versand::zoneAnlegen('Österreich & Schweiz', ['AT', 'CH'], [
        ['name' => 'Standardversand', 'preis' => '9,90', 'lieferzeit' => '3–5 Werktage', 'frei_ab' => '120,00'],
    ]);
    Versand::zoneAnlegen('EU', ['NL', 'BE', 'LU', 'FR', 'IT', 'ES', 'PL', 'DK', 'CZ', 'SE'], [
        ['name' => 'EU-Versand', 'preis' => '14,90', 'lieferzeit' => '4–7 Werktage'],
    ]);

    Settings::setMany([
        'shop_name'          => $werte['shop_name'],
        'shop_email'         => $werte['admin_email'],
        'mail_absender'      => $werte['absender'] ?: $werte['admin_email'],
        'mail_absender_name' => $werte['shop_name'],
        'zahlarten'          => 'test,rechnung,vorkasse',
        'start_titel'        => 'Willkommen bei ' . $werte['shop_name'],
        'start_text'         => 'Handverlesene Artikel, sofort lieferbar.',
        'hinweisleiste'      => 'Versandkostenfrei ab 75 € innerhalb Deutschlands',
        'hinweisleiste_an'   => '1',
    ]);

    rechtsseitenAnlegen();
    navigationAnlegen();
}

function rechtsseitenAnlegen(): void
{
    $seiten = [
        ['impressum', 'Impressum', '<p><strong>Angaben gemäß § 5 TMG</strong></p>
<p>Mein Shop GmbH<br>Musterstraße 1<br>10115 Berlin</p>
<p>Vertreten durch: Max Mustermann</p>
<p>Kontakt: shop@example.com</p>
<p>Umsatzsteuer-ID gemäß § 27a UStG: DE000000000</p>
<p><em>Bitte im Backend unter Inhalte → Seiten durch die eigenen Angaben ersetzen.</em></p>'],

        ['datenschutz', 'Datenschutzerklärung', '<p>Wir verarbeiten personenbezogene Daten ausschließlich zur
Abwicklung deiner Bestellung sowie auf Grundlage der gesetzlichen Bestimmungen (DSGVO, TDDDG).</p>
<h2>Verantwortlicher</h2><p>Mein Shop GmbH, Musterstraße 1, 10115 Berlin</p>
<h2>Deine Rechte</h2><p>Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung,
Datenübertragbarkeit und Widerspruch.</p>
<p><em>Bitte durch eine geprüfte Datenschutzerklärung ersetzen.</em></p>'],

        ['agb', 'Allgemeine Geschäftsbedingungen', '<h2>1. Geltungsbereich</h2>
<p>Für alle Bestellungen über unseren Online-Shop gelten die nachfolgenden Bedingungen.</p>
<h2>2. Vertragspartner</h2><p>Der Kaufvertrag kommt zustande mit der Mein Shop GmbH.</p>
<h2>3. Vertragsschluss</h2><p>Mit dem Klick auf „Zahlungspflichtig bestellen“ gibst du ein
verbindliches Angebot ab.</p>
<h2>4. Preise und Versandkosten</h2><p>Alle Preise verstehen sich inklusive gesetzlicher
Mehrwertsteuer zuzüglich Versandkosten.</p>
<p><em>Bitte durch geprüfte AGB ersetzen.</em></p>'],

        ['widerruf', 'Widerrufsbelehrung', '<h2>Widerrufsrecht</h2>
<p>Du hast das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p>
<h2>Folgen des Widerrufs</h2><p>Wenn du diesen Vertrag widerrufst, haben wir dir alle Zahlungen
unverzüglich und spätestens binnen vierzehn Tagen zurückzuzahlen.</p>
<p><em>Bitte durch eine geprüfte Belehrung ersetzen.</em></p>'],

        ['versand', 'Versand & Zahlung', '<h2>Versandkosten</h2><ul>
<li>Deutschland: 4,90 € – ab 75 € versandkostenfrei</li>
<li>Österreich & Schweiz: 9,90 € – ab 120 € versandkostenfrei</li>
<li>EU: 14,90 €</li></ul>
<h2>Lieferzeit</h2><p>Innerhalb Deutschlands in der Regel 2–3 Werktage nach Zahlungseingang.</p>
<h2>Zahlarten</h2><p>Welche Zahlarten angeboten werden, stellst du im Backend unter
Einstellungen → Zahlungen ein.</p>'],
    ];

    foreach ($seiten as [$handle, $titel, $inhalt]) {
        Inhalte::seiteSpeichern(null, [
            'handle' => $handle, 'titel' => $titel, 'inhalt' => $inhalt, 'sichtbar' => 1,
        ]);
    }
}

function navigationAnlegen(): void
{
    Inhalte::menueSetzen('haupt', [
        ['label' => 'Shop', 'url' => 'kategorie.php?h=alle', 'kinder' => [
            ['label' => 'Neuheiten', 'url' => 'kategorie.php?h=neuheiten'],
        ]],
        ['label' => 'Journal', 'url' => 'journal.php'],
        ['label' => 'Versand & Zahlung', 'url' => 'seite.php?h=versand'],
    ]);
    Inhalte::menueSetzen('fuss', [
        ['label' => 'Alle Artikel', 'url' => 'kategorie.php?h=alle'],
        ['label' => 'Kategorien', 'url' => 'kategorien.php'],
        ['label' => 'Journal', 'url' => 'journal.php'],
    ]);
}

function beispieleAnlegen(): void
{
    $beispiele = [
        ['Leinenhemd Sommer', 'Luftig, waschbar, aus europäischem Leinen', 'Hemden', 'Hausmarke',
         ['neu', 'sommer'], '89,00', '119,00', 12,
         [['name' => 'Größe', 'werteliste' => ['S', 'M', 'L', 'XL']]],
         '<p>Ein Hemd aus 100 % europäischem Leinen – leicht, atmungsaktiv und mit jeder Wäsche
etwas weicher. Klassischer Kentkragen, verdeckte Knopfleiste.</p>
<ul><li>100 % Leinen</li><li>Maschinenwäsche 30 °C</li><li>Regular Fit</li></ul>'],

        ['Wollpullover Merino', 'Feinstrick aus Merinowolle, mulesing-frei', 'Pullover', 'Hausmarke',
         ['neu', 'winter'], '149,00', null, 8,
         [['name' => 'Größe', 'werteliste' => ['S', 'M', 'L', 'XL']]],
         '<p>Feiner Merino-Strick, der wärmt ohne aufzutragen. Rundhalsausschnitt,
Bündchen an Ärmeln und Saum.</p><ul><li>100 % Merinowolle</li><li>Mulesing-frei</li></ul>'],

        ['Ledergürtel Vollrind', 'Pflanzlich gegerbt, Messingschnalle', 'Accessoires', 'Sattlerei Nord',
         ['leder'], '69,00', null, 20,
         [['name' => 'Länge', 'werteliste' => ['85 cm', '90 cm', '95 cm', '100 cm']]],
         '<p>Aus einem Stück pflanzlich gegerbtem Vollrindleder geschnitten, mit massiver
Messingschnalle. Bekommt mit den Jahren eine eigene Patina.</p>'],

        ['Canvas-Tasche Weekender', 'Wasserabweisend, mit Lederboden', 'Taschen', 'Hausmarke',
         ['neu', 'reise'], '189,00', null, 5, [],
         '<p>Reisetasche aus schwerem gewachstem Canvas mit Lederboden und abnehmbarem
Schultergurt. 42 Liter – Handgepäckmaß.</p>'],

        ['Emaille-Becher', '350 ml, spülmaschinenfest', 'Küche', 'Manufaktur Süd',
         ['geschenk'], '18,00', null, 40, [],
         '<p>Klassischer Emaille-Becher mit Stahlkern und blauem Rand. Für Lagerfeuer,
Büro und alles dazwischen.</p>'],

        ['Notizbuch A5 Leinen', '192 Seiten, dotted, fadengebunden', 'Papeterie', 'Manufaktur Süd',
         ['geschenk'], '24,00', null, 30, [],
         '<p>Fadengebundenes Notizbuch mit Leineneinband, 100 g/m² Papier und Lesebändchen.
Liegt flach auf.</p>'],
    ];

    foreach ($beispiele as $i => [$titel, $untertitel, $typ, $hersteller, $schlagworte, $preis, $streich, $bestand, $optionen, $text]) {
        $basis = [
            'preis'           => $preis,
            'streichpreis'    => $streich,
            'bestand'         => $bestand,
            'bestand_fuehren' => 1,
            'versandpflicht'  => 1,
        ];
        $varianten = Artikel::matrix($optionen, $basis);
        $kuerzel   = mb_strtoupper(mb_substr($titel, 0, 3, 'UTF-8'), 'UTF-8');
        foreach ($varianten as $nr => $variante) {
            $varianten[$nr]['artikelnummer'] = $kuerzel . '-' . str_pad((string) ($nr + 1), 3, '0', STR_PAD_LEFT);
        }

        Artikel::anlegen([
            'titel'        => $titel,
            'untertitel'   => $untertitel,
            'beschreibung' => $text,
            'typ'          => $typ,
            'hersteller'   => $hersteller,
            'schlagworte'  => $schlagworte,
            'status'       => 'aktiv',
            'optionen'     => $optionen,
            'varianten'    => $varianten,
        ]);
    }

    Kategorien::anlegen([
        'titel' => 'Alle Artikel', 'handle' => 'alle', 'sichtbar' => 1, 'art' => 'automatisch',
        'sortierung' => 'titel',
        'regeln' => [['feld' => 'preis', 'operator' => 'groesser', 'wert' => '0']],
    ]);
    Kategorien::anlegen([
        'titel' => 'Neuheiten', 'handle' => 'neuheiten', 'sichtbar' => 1, 'art' => 'automatisch',
        'sortierung' => 'neueste',
        'beschreibung' => '<p>Zuletzt aufgenommen – solange der Vorrat reicht.</p>',
        'regeln' => [['feld' => 'schlagwort', 'operator' => 'ist', 'wert' => 'neu']],
    ]);
    Kategorien::anlegen([
        'titel' => 'Accessoires', 'handle' => 'accessoires', 'sichtbar' => 1, 'art' => 'automatisch',
        'regeln' => [['feld' => 'typ', 'operator' => 'ist', 'wert' => 'Accessoires']],
    ]);

    Rabatte::anlegen([
        'code' => 'WILLKOMMEN10', 'name' => '10 % für Neukunden', 'art' => 'prozent',
        'wert' => '10', 'mindestwert' => '50,00', 'einmal_pro_kunde' => 1, 'aktiv' => 1,
    ]);

    Inhalte::beitragSpeichern(null, [
        'titel' => 'Willkommen im Shop', 'sichtbar' => 1, 'autor' => 'Redaktion',
        'anriss' => 'Warum es diesen Shop gibt und was du hier findest.',
        'inhalt' => '<p>Dieser Shop läuft auf einem eigenen System: Artikel, Kategorien, Inhalte und
Einstellungen werden im Backend gepflegt und mit einem Klick veröffentlicht.</p>
<p>Bis zum Veröffentlichen sieht niemand die Änderungen – danach alle.</p>',
    ]);
}

/* ---------------------------------------------------------- Abschlussseiten */

function fertigSeite(): string
{
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Bereits eingerichtet</title>' . installStil() . '</head><body><div class="karte">'
        . '<h1>Bereits eingerichtet</h1>'
        . '<p class="unter">Der Shop ist fertig installiert.</p>'
        . '<div class="hinweis hinweis-warnung"><strong>Bitte install.php vom Server löschen.</strong> '
        . 'Sie wird nicht mehr gebraucht und sollte nicht öffentlich erreichbar bleiben.</div>'
        . '<a class="knopf" href="admin/">Zum Backend</a> '
        . '<a class="knopf knopf-leer" href="index.php">Zum Shop</a>'
        . '</div></body></html>';
}

function erfolgSeite(array $werte): string
{
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Einrichtung abgeschlossen</title>' . installStil() . '</head><body><div class="karte">'
        . '<div class="haken">✓</div>'
        . '<h1>Der Shop ist eingerichtet</h1>'
        . '<p class="unter">Du kannst dich jetzt im Backend anmelden – mit '
        . '<strong>' . Util::e($werte['admin_email']) . '</strong> und deinem Passwort.</p>'
        . '<div class="hinweis hinweis-warnung"><strong>Jetzt bitte install.php vom Server löschen.</strong><br>'
        . 'Solange sie erreichbar ist, könnte jemand den Shop neu einrichten.</div>'
        . '<div class="schritte"><strong>Als Nächstes:</strong><ol>'
        . '<li>Unter <em>Einstellungen → Shop</em> Anschrift und Kontaktdaten eintragen.</li>'
        . '<li>Unter <em>Inhalte → Seiten</em> Impressum, AGB, Datenschutz und Widerruf '
        . 'durch geprüfte Texte ersetzen – die mitgelieferten sind nur Platzhalter.</li>'
        . '<li>Unter <em>Einstellungen → Zahlungen</em> die gewünschten Zahlarten aktivieren.</li>'
        . '<li>Artikel anlegen und oben rechts auf <em>Veröffentlichen</em> klicken.</li>'
        . '</ol></div>'
        . '<a class="knopf" href="admin/">Zum Backend</a> '
        . '<a class="knopf knopf-leer" href="index.php">Zum Shop</a>'
        . '</div></body></html>';
}

function installStil(): string
{
    return '<style>
    *{box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
      background:#f1f2f4;color:#16181d;margin:0;padding:40px 20px;line-height:1.55}
    .karte{max-width:660px;margin:0 auto;background:#fff;border-radius:12px;padding:34px;
      box-shadow:0 4px 24px rgba(0,0,0,.07)}
    h1{font-size:23px;margin:0 0 6px}
    h2{font-size:16px;margin:26px 0 12px;padding-top:20px;border-top:1px solid #e5e7eb}
    h2:first-of-type{border-top:0;padding-top:0;margin-top:20px}
    .unter{color:#6b7280;margin:0 0 22px}
    label{display:block;font-size:14px;font-weight:600;margin:0 0 5px}
    input[type=text],input[type=email],input[type=password],input[type=url],select{
      width:100%;padding:10px 12px;border:1px solid #c9cccf;border-radius:7px;font:inherit;font-size:14px}
    input:focus,select:focus{outline:2px solid #2f6f4f;outline-offset:-1px;border-color:#2f6f4f}
    .feld{margin-bottom:16px}
    .zeile{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .tipp{font-size:13px;color:#6b7280;margin-top:5px}
    .fehler{font-size:13px;color:#d72c0d;margin-top:5px;font-weight:500}
    .feld-fehler input,.feld-fehler select{border-color:#d72c0d}
    .knopf{display:inline-block;background:#16181d;color:#fff;border:1px solid #16181d;padding:12px 24px;
      border-radius:7px;font:inherit;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:8px}
    .knopf:hover{background:#000}
    .knopf-leer{background:transparent;color:#16181d;border-color:#c9cccf}
    .knopf-leer:hover{background:#f6f6f7}
    .liste{list-style:none;padding:0;margin:0 0 22px}
    .liste li{padding:9px 0;border-bottom:1px solid #e5e7eb;display:flex;gap:10px;align-items:center;font-size:14px}
    .liste li:last-child{border-bottom:0}
    .ja{color:#008060;font-weight:700}.nein{color:#d72c0d;font-weight:700}
    .hinweis{padding:13px 16px;border-radius:8px;margin:18px 0;font-size:14px;line-height:1.6}
    .hinweis-warnung{background:#fff4e4;color:#8a6800}
    .hinweis-fehler{background:#fdecea;color:#8a1c12}
    .hinweis-info{background:#eaf4ff;color:#1a4b8c}
    .haken{width:52px;height:52px;border-radius:50%;background:#e3f1df;color:#008060;
      display:grid;place-items:center;font-size:27px;margin-bottom:16px}
    .schritte{background:#f6f6f7;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px}
    .schritte ol{margin:8px 0 0;padding-left:20px}
    .schritte li{margin-bottom:7px}
    .db-felder{background:#f6f6f7;border-radius:8px;padding:16px;margin-top:12px}
    .kasten{display:flex;gap:9px;align-items:flex-start;font-size:14px;margin:14px 0}
    .kasten input{margin-top:3px}
    code{background:#f1f2f4;padding:2px 6px;border-radius:4px;font-size:13px}
    @media(max-width:560px){.zeile{grid-template-columns:1fr}}
    </style>';
}

/* -------------------------------------------------------------- Formular */
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Shop einrichten</title>
<?= installStil() ?>
</head>
<body>
<div class="karte">
  <h1>Shop einrichten</h1>
  <p class="unter">Einmalig ausfüllen – danach ist der Shop startklar. Dauert etwa zwei Minuten.</p>

  <h2>Voraussetzungen auf diesem Server</h2>
  <ul class="liste">
    <?php foreach ($vorbedingungen as $name => $erfuellt): ?>
      <li><span class="<?= $erfuellt ? 'ja' : 'nein' ?>"><?= $erfuellt ? '✓' : '✕' ?></span>
          <?= Util::e($name) ?></li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$bereit): ?>
    <div class="hinweis hinweis-fehler">
      <strong>Dieser Server erfüllt noch nicht alle Voraussetzungen.</strong><br>
      <?php if (!is_writable(__DIR__)): ?>
        Der Shop-Ordner ist nicht beschreibbar. Setze die Rechte im FTP-Programm auf <code>755</code>
        (Ordner) und lade die Seite neu.<br>
      <?php endif; ?>
      Der <a href="systemcheck.php">Systemcheck</a> zeigt alle Einzelheiten.
    </div>
  <?php else: ?>

    <?php if (isset($fehler['db'])): ?>
      <div class="hinweis hinweis-fehler"><?= Util::e($fehler['db']) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <h2>Adresse des Shops</h2>
      <div class="feld <?= isset($fehler['base_url']) ? 'feld-fehler' : '' ?>">
        <label for="base_url">Vollständige Adresse dieses Ordners</label>
        <input type="url" id="base_url" name="base_url" value="<?= Util::e($werte['base_url']) ?>" required>
        <div class="tipp">Ohne Schrägstrich am Ende. Diese Adresse rufst du gerade auf –
          der Vorschlag stimmt meist schon.</div>
        <?php if (isset($fehler['base_url'])): ?><div class="fehler"><?= Util::e($fehler['base_url']) ?></div><?php endif; ?>
      </div>

      <h2>Datenbank</h2>
      <div class="feld">
        <label for="db_treiber">Art der Datenbank</label>
        <select id="db_treiber" name="db_treiber" onchange="document.getElementById('mysql').style.display = this.value === 'mysql' ? 'block' : 'none'">
          <option value="sqlite" <?= $werte['db_treiber'] === 'sqlite' ? 'selected' : '' ?>
            <?= extension_loaded('pdo_sqlite') ? '' : 'disabled' ?>>
            SQLite – eine Datei, nichts einzurichten (empfohlen)
          </option>
          <option value="mysql" <?= $werte['db_treiber'] === 'mysql' ? 'selected' : '' ?>
            <?= extension_loaded('pdo_mysql') ? '' : 'disabled' ?>>
            MySQL / MariaDB – Zugangsdaten vom Hoster nötig
          </option>
        </select>
        <div class="tipp">SQLite reicht für die allermeisten Shops völlig aus und braucht keine
          Einrichtung. MySQL lohnt sich bei sehr vielen gleichzeitigen Besuchern.</div>
      </div>

      <div class="db-felder" id="mysql" style="display:<?= $werte['db_treiber'] === 'mysql' ? 'block' : 'none' ?>">
        <div class="zeile">
          <div class="feld"><label for="db_host">Server</label>
            <input type="text" id="db_host" name="db_host" value="<?= Util::e($werte['db_host']) ?>"></div>
          <div class="feld <?= isset($fehler['db_name']) ? 'feld-fehler' : '' ?>">
            <label for="db_name">Datenbankname</label>
            <input type="text" id="db_name" name="db_name" value="<?= Util::e($werte['db_name']) ?>"></div>
        </div>
        <div class="zeile">
          <div class="feld <?= isset($fehler['db_user']) ? 'feld-fehler' : '' ?>">
            <label for="db_user">Benutzername</label>
            <input type="text" id="db_user" name="db_user" value="<?= Util::e($werte['db_user']) ?>"></div>
          <div class="feld"><label for="db_pass">Passwort</label>
            <input type="password" id="db_pass" name="db_pass"></div>
        </div>
        <div class="tipp">Diese Angaben stehen im Hosting-Menü unter „Datenbanken“.</div>
      </div>

      <h2>Dein Shop</h2>
      <div class="feld <?= isset($fehler['shop_name']) ? 'feld-fehler' : '' ?>">
        <label for="shop_name">Name des Shops</label>
        <input type="text" id="shop_name" name="shop_name" value="<?= Util::e($werte['shop_name']) ?>"
               placeholder="z. B. Mein Laden" required>
        <?php if (isset($fehler['shop_name'])): ?><div class="fehler"><?= Util::e($fehler['shop_name']) ?></div><?php endif; ?>
      </div>

      <h2>Dein Zugang zum Backend</h2>
      <div class="feld">
        <label for="admin_name">Dein Name</label>
        <input type="text" id="admin_name" name="admin_name" value="<?= Util::e($werte['admin_name']) ?>">
      </div>
      <div class="feld <?= isset($fehler['admin_email']) ? 'feld-fehler' : '' ?>">
        <label for="admin_email">E-Mail-Adresse</label>
        <input type="email" id="admin_email" name="admin_email" value="<?= Util::e($werte['admin_email']) ?>" required>
        <div class="tipp">Damit meldest du dich später an. Bestellbenachrichtigungen gehen ebenfalls hierhin.</div>
        <?php if (isset($fehler['admin_email'])): ?><div class="fehler"><?= Util::e($fehler['admin_email']) ?></div><?php endif; ?>
      </div>
      <div class="zeile">
        <div class="feld <?= isset($fehler['admin_pass']) ? 'feld-fehler' : '' ?>">
          <label for="admin_pass">Passwort</label>
          <input type="password" id="admin_pass" name="admin_pass" required>
          <div class="tipp">Mindestens 10 Zeichen.</div>
        </div>
        <div class="feld <?= isset($fehler['admin_pass']) ? 'feld-fehler' : '' ?>">
          <label for="admin_pass2">Passwort wiederholen</label>
          <input type="password" id="admin_pass2" name="admin_pass2" required>
        </div>
      </div>
      <?php if (isset($fehler['admin_pass'])): ?>
        <div class="fehler" style="margin-top:-10px;margin-bottom:14px"><?= Util::e($fehler['admin_pass']) ?></div>
      <?php endif; ?>

      <label class="kasten">
        <input type="checkbox" name="beispiele" value="1" <?= $werte['beispiele'] === '1' ? 'checked' : '' ?>>
        <span>Beispielartikel und -kategorien anlegen
          <span class="tipp" style="display:block">Zum Ausprobieren. Lässt sich später vollständig löschen.</span>
        </span>
      </label>

      <div class="hinweis hinweis-info">
        Angelegt werden außerdem: Steuersätze (19 % / 7 %), drei Versandzonen und Entwürfe für
        Impressum, AGB, Datenschutz und Widerruf.
      </div>

      <button class="knopf" type="submit">Shop einrichten</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
