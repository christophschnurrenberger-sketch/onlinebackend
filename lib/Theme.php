<?php
/**
 * Theme – der Rahmen aller Shopseiten.
 *
 * Die Design-Einstellungen werden als CSS-Custom-Properties in den <head>
 * geschrieben. Deshalb ändert ein Farbwechsel im Backend das ganze Frontend,
 * ohne dass eine Datei angefasst werden muss – und ein späteres eigenes Theme
 * überschreibt entweder diese Variablen oder ersetzt assets/shop.css.
 */
final class Theme
{
    /**
     * Fertige Shop-Stile.
     *
     * Ein Stil besteht aus zwei Teilen: den Farb- und Schriftwerten (sie landen
     * als CSS-Variablen im Kopf jeder Seite) und optional einer Stildatei unter
     * assets/stile/. Die Datei macht das, was sich nicht als Variable ausdrücken
     * lässt – Versalien, Rahmen, Abstände, Verhalten beim Überfahren.
     *
     * Die vier Branchenstile sind nach dem gebaut, was in der jeweiligen Branche
     * üblich ist; sie kopieren keinen fremden Shop. Wer sie anpassen will,
     * ändert entweder die Werte im Backend oder die Datei.
     *
     * Die Schlüssel dieser Liste sind zugleich der einzige erlaubte Dateiname –
     * so kann über die Einstellung kein fremder Pfad eingeschleust werden.
     */
    public const STILE = [
        'basis' => [
            'name'  => 'Basis',
            'text'  => 'Neutral und zurückhaltend. Der Ausgangspunkt für ein eigenes Design.',
            'datei' => '',
            'werte' => [
                'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f7f7f8', 'farbe_text' => '#16181d',
                'farbe_nebentext' => '#6b7280', 'farbe_rahmen' => '#e5e7eb', 'farbe_knopf' => '#16181d',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#2f6f4f', 'farbe_sale' => '#c0392b',
                'ecken' => '10px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_HELVETICA, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
        'golf' => [
            'name'  => 'Golf',
            'text'  => 'Sportfachhandel: tiefes Grün, viel Weiß, Versalien, geordnetes Raster. '
                     . 'Für Schläger, Bekleidung, Schuhe und Zubehör.',
            'datei' => 'golf',
            'werte' => [
                'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f1f5f2', 'farbe_text' => '#0f2419',
                'farbe_nebentext' => '#5d6b63', 'farbe_rahmen' => '#dbe3dd', 'farbe_knopf' => '#14532d',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#2f8f4e', 'farbe_sale' => '#c0392b',
                'ecken' => '6px', 'inhaltsbreite' => '1400px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_HELVETICA, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
        'rad' => [
            'name'  => 'Fahrrad',
            'text'  => 'Technisch und kantig: Schwarz, Signalrot, schmale Versalien, große Preise. '
                     . 'Für Räder, Komponenten, Ersatzteile und Werkstattzubehör.',
            'datei' => 'rad',
            'werte' => [
                'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f2f3f5', 'farbe_text' => '#14161a',
                'farbe_nebentext' => '#6a7078', 'farbe_rahmen' => '#d9dce1', 'farbe_knopf' => '#14161a',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#e2231a', 'farbe_sale' => '#e2231a',
                'ecken' => '0px', 'inhaltsbreite' => '1400px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_SCHMAL, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
        'pferd' => [
            'name'  => 'Pferdesport',
            'text'  => 'Reitsport und Stallbedarf: Marineblau auf Sand, Serifenüberschriften, ruhige Karten. '
                     . 'Für ein breites Katalogsortiment.',
            'datei' => 'pferd',
            'werte' => [
                'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f4f0e8', 'farbe_text' => '#1b2436',
                'farbe_nebentext' => '#6f6a60', 'farbe_rahmen' => '#e2dccd', 'farbe_knopf' => '#1d3557',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#9a6a2f', 'farbe_sale' => '#b23a2f',
                'ecken' => '6px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_GEORGIA, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
        'kraeuter' => [
            'name'  => 'Kräuter',
            'text'  => 'Naturprodukte: cremefarbener Grund, weiße Karten, kräftiges Rot als einzige '
                     . 'Signalfarbe, Salbeigrün für den Versandhinweis. Marke mittig, alles rund.',
            'datei' => 'kraeuter',
            'werte' => [
                'farbe_hintergrund' => '#fbf8f4', 'farbe_flaeche' => '#ebe8df', 'farbe_text' => '#434343',
                'farbe_nebentext' => '#7d7a74', 'farbe_rahmen' => '#e3ded4', 'farbe_knopf' => '#c11b1a',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#849e62', 'farbe_sale' => '#c11b1a',
                'ecken' => '18px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_QUELLE, 'schrift_text' => self::SCHRIFT_QUELLE,
            ],
        ],
        'stube' => [
            'name'  => 'Kräuterstube',
            'text'  => 'Handgemacht: Creme und Terrakotta, Serifenüberschriften, Handschrift für '
                     . 'Randnotizen, Bogenbilder. Für kleine Manufakturen, die selbst mischen.',
            'datei' => 'stube',
            'werte' => [
                'farbe_hintergrund' => '#fbf5ea', 'farbe_flaeche' => '#eadfc9', 'farbe_text' => '#33281f',
                'farbe_nebentext' => '#7a6a55', 'farbe_rahmen' => '#e0cfb2', 'farbe_knopf' => '#b4694a',
                'farbe_knopf_text' => '#fbf5ea', 'farbe_akzent' => '#56694a', 'farbe_sale' => '#b4694a',
                'ecken' => '18px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '3',
                'schrift_titel' => self::SCHRIFT_PETRONA, 'schrift_text' => self::SCHRIFT_CABIN,
            ],
        ],
        'kontrast' => [
            'name'  => 'Kontrast',
            'text'  => 'Schwarz auf Weiß, kantig, ohne Farbe.',
            'datei' => '',
            'werte' => [
                'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f2f2f2', 'farbe_text' => '#000000',
                'farbe_nebentext' => '#666666', 'farbe_rahmen' => '#000000', 'farbe_knopf' => '#000000',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#000000', 'farbe_sale' => '#d40000',
                'ecken' => '0px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_HELVETICA, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
        'warm' => [
            'name'  => 'Warm',
            'text'  => 'Sand und Terrakotta, weiche Ecken.',
            'datei' => '',
            'werte' => [
                'farbe_hintergrund' => '#fdfaf5', 'farbe_flaeche' => '#f4ede3', 'farbe_text' => '#2c231b',
                'farbe_nebentext' => '#7d6c5b', 'farbe_rahmen' => '#e2d6c6', 'farbe_knopf' => '#8c4a2f',
                'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#6b7f4f', 'farbe_sale' => '#b4432a',
                'ecken' => '18px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_GEORGIA, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
        'dunkel' => [
            'name'  => 'Dunkel',
            'text'  => 'Heller Text auf dunklem Grund.',
            'datei' => '',
            'werte' => [
                'farbe_hintergrund' => '#12141a', 'farbe_flaeche' => '#1b1f28', 'farbe_text' => '#f0f2f5',
                'farbe_nebentext' => '#9aa3b2', 'farbe_rahmen' => '#2a303c', 'farbe_knopf' => '#f0f2f5',
                'farbe_knopf_text' => '#12141a', 'farbe_akzent' => '#6bd6a4', 'farbe_sale' => '#ff7a6b',
                'ecken' => '10px', 'inhaltsbreite' => '1200px', 'artikel_pro_reihe' => '4',
                'schrift_titel' => self::SCHRIFT_HELVETICA, 'schrift_text' => self::SCHRIFT_SYSTEM,
            ],
        ],
    ];

    /* Schriftstapel. Nur Systemschriften – sie sind sofort da und kosten keine
       fremde Verbindung. Diese Werte stehen auch in der Auswahl im Backend. */
    public const SCHRIFT_SYSTEM    = '-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif';
    public const SCHRIFT_HELVETICA = '\'Helvetica Neue\', Helvetica, Arial, sans-serif';
    public const SCHRIFT_SCHMAL    = '\'Arial Narrow\', \'Helvetica Neue\', Helvetica, Arial, sans-serif';
    public const SCHRIFT_HUMANIST  = '\'Avenir Next\', Avenir, \'Segoe UI\', \'Trebuchet MS\', system-ui, sans-serif';
    /* Liegen als Datei bei, siehe assets/schriften/. */
    public const SCHRIFT_QUELLE    = '\'Source Sans 3\', system-ui, -apple-system, \'Segoe UI\', Roboto, Arial, sans-serif';
    public const SCHRIFT_PETRONA   = 'Petrona, Georgia, \'Times New Roman\', serif';
    public const SCHRIFT_CABIN     = 'Cabin, system-ui, -apple-system, \'Segoe UI\', Roboto, Arial, sans-serif';
    public const SCHRIFT_GEORGIA   = 'Georgia, "Times New Roman", serif';
    public const SCHRIFT_PALATINO  = '"Iowan Old Style", "Palatino Linotype", Palatino, serif';
    public const SCHRIFT_MONO      = 'ui-monospace, \'SF Mono\', Menlo, Consolas, monospace';

    /** @var array<string,mixed>|null Die live geschaltete Fassung. */
    private static ?array $fassung = null;

    /**
     * Name der Stildatei der veröffentlichten Fassung, oder '' für keine.
     *
     * Der Wert kommt aus der Datenbank und landet in einer URL – deshalb wird
     * er nicht durchgereicht, sondern in der Liste oben nachgeschlagen.
     */
    public static function stilDatei(): string
    {
        $schluessel = self::e('design_vorlage');
        return (string) (self::STILE[$schluessel]['datei'] ?? '');
    }

    /** Lädt die veröffentlichte Fassung; zeigt sonst den Hinweis und bricht ab. */
    public static function fassung(): array
    {
        if (self::$fassung !== null) {
            return self::$fassung;
        }
        $live = Veroeffentlichung::live();
        if ($live === null) {
            self::nichtVeroeffentlicht();
        }
        self::$fassung = $live;
        return $live;
    }

    /** Einstellung aus der veröffentlichten Fassung (nicht aus dem Arbeitsstand). */
    public static function e(string $schluessel, string $vorgabe = ''): string
    {
        $fassung = self::fassung();
        return (string) ($fassung['einstellungen'][$schluessel] ?? $vorgabe);
    }

    public static function anAus(string $schluessel): bool
    {
        return in_array(self::e($schluessel), ['1', 'true', 'ja', 'on'], true);
    }

    private static function nichtVeroeffentlicht(): never
    {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Noch nicht veröffentlicht</title>'
           . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;'
           . 'display:grid;place-items:center;min-height:100vh;margin:0;background:#f7f7f8;color:#16181d}'
           . '.k{max-width:480px;padding:40px;background:#fff;border-radius:12px;text-align:center;'
           . 'box-shadow:0 4px 24px rgba(0,0,0,.06)}a{color:#2f6f4f}</style></head><body><div class="k">'
           . '<h1 style="font-size:22px">Noch nichts veröffentlicht</h1>'
           . '<p style="color:#6b7280;line-height:1.6">Dieser Shop wurde noch nicht veröffentlicht. '
           . 'Lege im Backend Artikel an und klicke dort auf <strong>Veröffentlichen</strong>.</p>'
           . '<p><a href="admin/">Zum Backend</a></p></div></body></html>';
        exit;
    }

    /* --------------------------------------------------------------- Seite */

    /**
     * Gibt den Seitenkopf aus. Danach folgt der Seiteninhalt, am Ende fuss().
     *
     * @param array<string,mixed> $optionen titel, beschreibung, bild, canonical, noindex
     */
    public static function kopf(array $optionen = []): void
    {
        $fassung  = self::fassung();
        $shopName = self::e('shop_name', 'Shop');
        $titel    = trim((string) ($optionen['titel'] ?? ''));
        $vollTitel = $titel !== '' ? $titel . ' – ' . $shopName : $shopName . ' – ' . self::e('shop_slogan');
        $text     = (string) ($optionen['beschreibung'] ?? self::e('shop_beschreibung'));
        $anzahl   = Warenkorb::anzahl();

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="de" data-waehrung="<?= Util::e(self::e('waehrung', 'EUR')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Util::e($vollTitel) ?></title>
<meta name="description" content="<?= Util::e(Util::kuerzen($text, 300)) ?>">
<?php if (!empty($optionen['noindex'])): ?>
<meta name="robots" content="noindex,nofollow">
<?php endif; ?>
<?php if (!empty($optionen['canonical'])): ?>
<link rel="canonical" href="<?= Util::e((string) $optionen['canonical']) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= Util::e($shopName) ?>">
<meta property="og:title" content="<?= Util::e($vollTitel) ?>">
<meta property="og:description" content="<?= Util::e(Util::kuerzen($text, 300)) ?>">
<?php if (!empty($optionen['bild'])): ?>
<meta property="og:image" content="<?= Util::e(self::url((string) $optionen['bild'])) ?>">
<?php endif; ?>
<?php if (self::e('favicon_url') !== ''): ?>
<link rel="icon" href="<?= Util::e(self::url(self::e('favicon_url'))) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= Util::e(Config::url('assets/shop.css')) ?>">
<?php if (self::stilDatei() !== ''): ?>
<link rel="stylesheet" href="<?= Util::e(Config::url('assets/stile/' . self::stilDatei() . '.css')) ?>">
<?php endif; ?>
<style>
:root {
<?= self::cssVariablen() ?>
}
<?= self::eigenesCss() ?>
</style>
</head>
<body>
<?php
/*
 * Der Kopf ist bewusst dreiteilig aufgebaut, wie im deutschen Fachhandel
 * üblich: schmale Servicezeile, darunter die Zeile mit Marke, Suche und
 * Warenkorb, darunter das Kategorienband. Wer das nicht will, blendet die
 * Teile über die Einstellungen aus oder überschreibt sie im Stylesheet.
 */
$servicelinks = array_filter([
    'Kontakt'   => self::e('shop_email') !== '' ? 'mailto:' . self::e('shop_email') : '',
    'Impressum' => self::e('seite_impressum') !== '' ? 'seite.php?h=' . self::e('seite_impressum') : '',
]);
$vorteile = array_values(array_filter([
    self::e('vorteil_1'), self::e('vorteil_2'), self::e('vorteil_3'), self::e('vorteil_4'),
]));
?>
<?php if (self::anAus('servicezeile_an') && (self::e('servicezeile') !== '' || self::e('shop_telefon') !== '')): ?>
<div class="servicezeile">
  <div class="behaelter servicezeile-innen">
    <span class="service-text"><?= Util::e(self::e('servicezeile')) ?></span>
    <span class="service-rechts">
      <?php if (self::e('shop_telefon') !== ''): ?>
        <a class="service-telefon" href="tel:<?= Util::e(preg_replace('/[^0-9+]/', '', self::e('shop_telefon'))) ?>">
          <?= Util::e(self::e('shop_telefon')) ?></a>
      <?php endif; ?>
      <?php foreach ($servicelinks as $label => $ziel): ?>
        <a href="<?= Util::e(self::url($ziel)) ?>"><?= Util::e($label) ?></a>
      <?php endforeach; ?>
    </span>
  </div>
</div>
<?php endif; ?>

<?php if (self::anAus('hinweisleiste_an') && self::e('hinweisleiste') !== ''): ?>
<div class="hinweisleiste"><?= Util::e(self::e('hinweisleiste')) ?></div>
<?php endif; ?>

<header class="kopf">
  <div class="behaelter kopf-innen">
    <button class="menue-schalter" type="button" aria-expanded="false" aria-controls="hauptmenue" aria-label="Menü">☰</button>
    <a class="marke" href="<?= Util::e(Config::url()) ?>">
      <?php if (self::e('logo_url') !== ''): ?>
        <img src="<?= Util::e(self::url(self::e('logo_url'))) ?>" alt="<?= Util::e($shopName) ?>">
      <?php else: ?>
        <span class="marke-name"><?= Util::e($shopName) ?></span>
        <?php /* Der Slogan gehört zur Wortmarke, nicht in eine eigene Zeile:
                 "Kräuter für Pferd, Hund und Katze · seit 1998" sagt in acht
                 Wörtern, was sonst ein Absatz erklären müsste. Ob er
                 erscheint, entscheidet der Stil. */ ?>
        <?php if (self::e('shop_slogan') !== ''): ?>
          <span class="marke-slogan"><?= Util::e(self::e('shop_slogan')) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </a>
    <div class="kopf-aktionen">
      <form class="suchfeld" action="<?= Util::e(Config::url('suche.php')) ?>" role="search">
        <label class="nur-vorlesen" for="q">Suche</label>
        <input type="search" id="q" name="q" placeholder="Wonach suchst du?" value="<?= Util::e(Util::get('q')) ?>">
        <button type="submit" aria-label="Suchen"><?= self::symbol('lupe', 18) ?><span class="nur-vorlesen">Suchen</span></button>
      </form>
      <a class="warenkorb-link" href="<?= Util::e(Config::url('warenkorb.php')) ?>">
        <?= self::symbol('tasche') ?>
        <span class="warenkorb-wort">Warenkorb</span>
        <span class="warenkorb-zahl" data-warenkorb-zahl><?= (int) $anzahl ?></span>
      </a>
    </div>
  </div>

  <nav class="kopf-nav" aria-label="Hauptmenü">
    <div class="behaelter">
      <ul class="hauptmenue" id="hauptmenue">
        <?php foreach (($fassung['menues']['haupt'] ?? []) as $punkt): ?>
          <li class="menuepunkt">
            <a href="<?= Util::e(self::url((string) $punkt['url'])) ?>"><?= Util::e((string) $punkt['label']) ?></a>
            <?php if (!empty($punkt['kinder'])): ?>
              <ul class="untermenue">
                <?php foreach ($punkt['kinder'] as $kind): ?>
                  <li><a href="<?= Util::e(self::url((string) $kind['url'])) ?>"><?= Util::e((string) $kind['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>
</header>

<?php if (self::anAus('vorteile_an') && $vorteile !== []): ?>
<div class="vorteilsleiste">
  <div class="behaelter vorteile-innen">
    <?php foreach ($vorteile as $vorteil): ?>
      <span class="vorteil"><?= Util::e($vorteil) ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<main id="inhalt">
        <?php
    }

    public static function fuss(): void
    {
        $fassung = self::fassung();
        $jahr    = date('Y');
        $rechtsseiten = array_filter([
            'impressum'   => ['Impressum', self::e('seite_impressum')],
            'datenschutz' => ['Datenschutz', self::e('seite_datenschutz')],
            'agb'         => ['AGB', self::e('seite_agb')],
            'widerruf'    => ['Widerruf', self::e('seite_widerruf')],
            'versand'     => ['Versand & Zahlung', self::e('seite_versand')],
        ], static fn($eintrag) => $eintrag[1] !== '');
        ?>
</main>

<footer class="fuss">
  <div class="behaelter">
    <div class="fuss-raster">
      <div class="fuss-marke">
        <h4><?= Util::e(self::e('shop_name')) ?></h4>
        <p class="nebentext"><?= Util::e(self::e('fusszeile_text') ?: self::e('shop_beschreibung')) ?></p>
        <?php
        $sozial = array_filter([
            'instagram' => ['Instagram', self::e('social_instagram')],
            'facebook'  => ['Facebook', self::e('social_facebook')],
        ], static fn(array $e): bool => $e[1] !== '');
        ?>
        <?php if ($sozial !== []): ?>
          <div class="sozial">
            <?php foreach ($sozial as $symbol => [$name, $url]): ?>
              <a href="<?= Util::e($url) ?>" rel="noopener" target="_blank" aria-label="<?= Util::e($name) ?>">
                <?= self::symbol($symbol) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php
      /*
       * Die Spalten stecken in <details>. Am Schreibtisch stehen sie offen und
       * die Aufklappmarke ist weg; auf dem Telefon klappt shop.js sie zu, damit
       * der Fuß nicht länger wird als die Seite davor. Ohne JavaScript bleiben
       * sie offen – lesbar ist er dann immer noch.
       */
      ?>
      <?php if (!empty($fassung['menues']['fuss'])): ?>
      <details class="fuss-spalte" open>
        <summary><h4>Shop</h4></summary>
        <ul>
          <?php foreach ($fassung['menues']['fuss'] as $punkt): ?>
            <li><a href="<?= Util::e(self::url((string) $punkt['url'])) ?>"><?= Util::e((string) $punkt['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>

      <?php if ($rechtsseiten !== []): ?>
      <details class="fuss-spalte" open>
        <summary><h4>Rechtliches</h4></summary>
        <ul>
          <?php foreach ($rechtsseiten as [$label, $handle]): ?>
            <li><a href="<?= Util::e(Config::url('seite.php?h=' . rawurlencode($handle))) ?>"><?= Util::e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>

      <div class="fuss-hilfe">
        <?php
        /*
         * Anschrift, Telefon und Umsatzsteuer-ID stehen bewusst ausgeschrieben
         * im Fuß, nicht nur im Impressum. Wer dahinter steckt und wie man ihn
         * erreicht, ist in der Forschung zur Glaubwürdigkeit von Webseiten der
         * stärkste Einzelfaktor – und bei Fake-Shops fehlt genau das. Die
         * Telefonnummer steht deshalb groß und nicht als Fußnote.
         */
        $anbieter = array_values(array_filter([
            self::e('firma'),
            self::e('strasse'),
            trim(self::e('plz') . ' ' . self::e('ort')),
        ]));
        ?>
        <h4>Fragen? Wir helfen.</h4>
        <?php if (self::e('shop_telefon') !== ''): ?>
          <a class="hilfe-telefon" href="tel:<?= Util::e(preg_replace('/[^0-9+]/', '', self::e('shop_telefon'))) ?>">
            <?= self::symbol('telefon', 22) ?><span><?= Util::e(self::e('shop_telefon')) ?></span>
          </a>
        <?php endif; ?>
        <?php if (self::e('servicezeile') !== ''): ?>
          <p class="hilfe-zeiten"><?= Util::e(self::e('servicezeile')) ?></p>
        <?php endif; ?>
        <?php if (self::e('shop_email') !== ''): ?>
          <a class="hilfe-mail" href="mailto:<?= Util::e(self::e('shop_email')) ?>">
            <?= self::symbol('brief', 18) ?><span><?= Util::e(self::e('shop_email')) ?></span>
          </a>
        <?php endif; ?>
        <?php if ($anbieter !== []): ?>
          <address class="anbieter">
            <?= implode('<br>', array_map([Util::class, 'e'], $anbieter)) ?>
          </address>
        <?php endif; ?>
        <?php if (self::e('ust_id') !== ''): ?>
          <p class="klein nebentext">USt-IdNr. <?= Util::e(self::e('ust_id')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php
    $zahlarten = [];
    try {
        $zahlarten = array_map(static fn(array $z): string => (string) $z['name'], Zahlung::verfuegbare());
    } catch (Throwable $e) {
        // Ohne Datenbank keine Zahlarten – der Fuß bleibt trotzdem stehen.
    }
    $siegelBild = self::e('siegel_bild');
    ?>
    <?php if ($zahlarten !== [] || $siegelBild !== ''): ?>
      <div class="fuss-vertrauen">
        <?php if ($zahlarten !== []): ?>
          <div class="zahlarten">
            <span class="zahlarten-titel">Zahlungsarten</span>
            <?php foreach ($zahlarten as $name): ?>
              <span class="zahlart"><?= Util::e($name) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($siegelBild !== ''): ?>
          <div class="siegel">
            <?php if (self::e('siegel_url') !== ''): ?>
              <a href="<?= Util::e(self::e('siegel_url')) ?>" rel="noopener" target="_blank">
            <?php endif; ?>
            <img src="<?= Util::e(self::url($siegelBild)) ?>"
                 alt="<?= Util::e(self::e('siegel_text') ?: 'Gütesiegel') ?>" loading="lazy">
            <?php if (self::e('siegel_url') !== ''): ?></a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="fuss-unten">
      <span>© <?= $jahr ?> <?= Util::e(self::e('firma') ?: self::e('shop_name')) ?></span>
      <span>Alle Preise inkl. gesetzlicher MwSt. zzgl. Versandkosten.</span>
    </div>
  </div>
</footer>

<script src="<?= Util::e(Config::url('assets/shop.js')) ?>" defer></script>
</body>
</html>
        <?php
    }

    /* ------------------------------------------------------------- Bausteine */

    private static function cssVariablen(): string
    {
        $zuordnung = [
            '--hintergrund'  => 'farbe_hintergrund',
            '--flaeche'      => 'farbe_flaeche',
            '--text'         => 'farbe_text',
            '--nebentext'    => 'farbe_nebentext',
            '--rahmen'       => 'farbe_rahmen',
            '--knopf'        => 'farbe_knopf',
            '--knopf-text'   => 'farbe_knopf_text',
            '--akzent'       => 'farbe_akzent',
            '--sale'         => 'farbe_sale',
            '--ecken'        => 'ecken',
            '--breite'       => 'inhaltsbreite',
            '--schrift-titel' => 'schrift_titel',
            '--schrift-text' => 'schrift_text',
            '--spalten'      => 'artikel_pro_reihe',
        ];
        $zeilen = [];
        foreach ($zuordnung as $variable => $schluessel) {
            $wert = self::e($schluessel);
            if ($wert === '') {
                continue;
            }
            // Semikolons und Klammern könnten aus der Regel ausbrechen und
            // beliebiges CSS einschleusen.
            $zeilen[] = '  ' . $variable . ': ' . preg_replace('/[;{}<>]/', '', $wert) . ';';
        }
        return implode("\n", $zeilen);
    }

    private static function eigenesCss(): string
    {
        $css = self::e('eigenes_css');
        return $css === '' ? '' : preg_replace('#</?(script|style)#i', '', $css);
    }

    /**
     * Kleine Strichsymbole für Kopf und Fuß.
     *
     * Bewusst als SVG im Quelltext und nicht als Symbolschrift oder Bild: kein
     * zusätzlicher Ladevorgang, sie erben die Textfarbe, und sie sind auch dann
     * da, wenn eine externe Verbindung scheitert.
     */
    public static function symbol(string $name, int $groesse = 20): string
    {
        $pfade = [
            'lupe'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'tasche'    => '<path d="M6 8h12l1 12H5L6 8Z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
            'telefon'   => '<path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1Z"/>',
            'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/>',
            'facebook'  => '<path d="M14.5 8.5h2.5V5h-2.5A3.5 3.5 0 0 0 11 8.5V11H8.5v3.5H11V21h3.5v-6.5H17l.5-3.5h-3V9a.5.5 0 0 1 .5-.5Z"/>',
            'brief'     => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        ];
        $d = $pfade[$name] ?? '';
        return $d === '' ? '' : '<svg class="symbol" viewBox="0 0 24 24" width="' . $groesse . '" height="' . $groesse
            . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
    }

    /** Macht aus einem gespeicherten Pfad eine benutzbare Adresse. */
    public static function url(string $pfad): string
    {
        if ($pfad === '' || preg_match('#^(https?:)?//#', $pfad)) {
            return $pfad;
        }
        return Config::url(ltrim($pfad, '/'));
    }

    /* ------------------------------------------------------- Artikelkacheln */

    /** Eine Kachel im Artikelraster. */
    public static function kachel(array $artikel, array $bestaende = []): void
    {
        $bild        = $artikel['bilder'][0] ?? null;
        $streich     = (int) $artikel['streich_max'];
        $preis       = (int) $artikel['preis_max'];
        $sale        = $streich > $preis;
        $ausverkauft = self::istAusverkauft($artikel, $bestaende);
        // Im Handel steht auf dem Aufkleber der Nachlass, nicht das Wort "Sale".
        $rabatt      = $sale ? (int) round(($streich - $preis) / $streich * 100) : 0;
        $lager       = self::lieferstatus($artikel, $bestaende);
        ?>
        <article class="kachel">
          <a href="<?= Util::e(Config::url('artikel.php?h=' . rawurlencode((string) $artikel['handle']))) ?>">
            <div class="kachel-bild">
              <?php if ($bild !== null): ?>
                <img src="<?= Util::e(self::url((string) $bild['url'])) ?>"
                     alt="<?= Util::e((string) ($bild['alt'] ?: $artikel['titel'])) ?>" loading="lazy">
              <?php else: ?>
                <div class="kein-bild">Kein Bild</div>
              <?php endif; ?>
              <?php if ($ausverkauft): ?>
                <span class="marker marker-aus">Ausverkauft</span>
              <?php elseif ($sale && $rabatt > 0 && self::anAus('streichpreis_zeigen')): ?>
                <span class="marker">−<?= $rabatt ?>&nbsp;%</span>
              <?php endif; ?>
            </div>
            <?php if (self::anAus('hersteller_zeigen') && (string) $artikel['hersteller'] !== ''): ?>
              <p class="hersteller"><?= Util::e((string) $artikel['hersteller']) ?></p>
            <?php endif; ?>
            <h3><?= Util::e((string) $artikel['titel']) ?></h3>
            <?= self::preisText($artikel) ?>
            <?php $grundpreis = self::grundpreisText($artikel); ?>
            <?php if ($grundpreis !== ''): ?>
              <p class="grundpreis"><?= Util::e($grundpreis) ?></p>
            <?php endif; ?>
            <p class="preishinweis">inkl. MwSt., zzgl. Versand</p>
            <p class="kachel-lager lager-<?= Util::e($lager[0]) ?>"><?= Util::e($lager[1]) ?></p>
          </a>
        </article>
        <?php
    }

    /**
     * Lieferstatus für die Artikelkachel.
     *
     * Im Fachhandel steht die Verfügbarkeit schon in der Liste, nicht erst auf
     * der Artikelseite – sie entscheidet oft, welcher Artikel angeklickt wird.
     *
     * @param array<int,int|null> $bestaende
     * @return array{0:string,1:string} Klassenteil und Text
     */
    public static function lieferstatus(array $artikel, array $bestaende): array
    {
        $hoechster = 0;
        foreach ($artikel['varianten'] as $variante) {
            $frei = $bestaende[(int) $variante['id']] ?? null;
            if ($frei === null) {
                return ['da', 'Sofort lieferbar'];
            }
            $hoechster = max($hoechster, (int) $frei);
        }
        if ($hoechster <= 0) {
            return ['aus', 'Zurzeit nicht lieferbar'];
        }
        if ($hoechster <= 5) {
            return ['knapp', 'Nur noch ' . $hoechster . ' auf Lager'];
        }
        return ['da', 'Sofort lieferbar'];
    }

    /**
     * Grundpreis des Artikels für die Liste, etwa "25,99 €/l".
     *
     * Genommen wird die günstigste Variante mit hinterlegter Füllmenge – das
     * ist die, deren Preis in der Kachel steht. Ohne Füllmenge bleibt die
     * Zeile leer; bei Stückware gibt es keinen Grundpreis.
     */
    public static function grundpreisText(array $artikel): string
    {
        $beste = null;
        foreach ($artikel['varianten'] ?? [] as $variante) {
            if ((int) ($variante['inhalt_menge'] ?? 0) <= 0 || (string) ($variante['inhalt_einheit'] ?? '') === '') {
                continue;
            }
            if ($beste === null || (int) $variante['preis'] < (int) $beste['preis']) {
                $beste = $variante;
            }
        }
        return $beste === null
            ? ''
            : Util::grundpreis((int) $beste['preis'], (int) $beste['inhalt_menge'], (string) $beste['inhalt_einheit']);
    }

    /** Preisanzeige inklusive Streichpreis und "ab" bei Preisspannen. */
    public static function preisText(array $artikel): string
    {
        $min     = (int) $artikel['preis_min'];
        $max     = (int) $artikel['preis_max'];
        $streich = (int) ($artikel['streich_max'] ?? 0);
        $sale    = self::anAus('streichpreis_zeigen') && $streich > $max;

        $aktuell = $min === $max ? Util::geld($min) : 'ab ' . Util::geld($min);

        $html = '<div class="preis"><span class="preis-jetzt' . ($sale ? ' preis-sale' : '') . '">'
              . Util::e($aktuell) . '</span>';
        if ($sale) {
            $html .= '<span class="preis-vorher">' . Util::e(Util::geld($streich)) . '</span>';
        }
        return $html . '</div>';
    }

    /**
     * Bestände zu Varianten-IDs. null heißt unbegrenzt verfügbar.
     * Wird direkt aus der Datenbank gelesen – nicht aus der Fassung.
     *
     * @param array<int,int> $variantenIds
     * @return array<int,int|null>
     */
    public static function bestaende(array $variantenIds): array
    {
        $variantenIds = array_values(array_unique(array_map('intval', $variantenIds)));
        if ($variantenIds === []) {
            return [];
        }
        $platzhalter = implode(',', array_fill(0, count($variantenIds), '?'));
        $out = [];
        foreach (DB::all(
            'SELECT id, bestand, bestand_fuehren, ueberverkauf FROM varianten WHERE id IN (' . $platzhalter . ')',
            $variantenIds
        ) as $zeile) {
            $out[(int) $zeile['id']] = Bestand::verfuegbar($zeile);
        }
        return $out;
    }

    /** @param array<int,int|null> $bestaende */
    public static function istAusverkauft(array $artikel, array $bestaende): bool
    {
        foreach ($artikel['varianten'] as $variante) {
            $frei = $bestaende[(int) $variante['id']] ?? null;
            if ($frei === null || $frei > 0) {
                return false;
            }
        }
        return true;
    }

    /** Sammelt alle Varianten-IDs mehrerer Artikel – für eine einzige Abfrage. */
    public static function variantenIds(array $artikelListe): array
    {
        $ids = [];
        foreach ($artikelListe as $artikel) {
            foreach ($artikel['varianten'] as $variante) {
                $ids[] = (int) $variante['id'];
            }
        }
        return $ids;
    }

    /* ---------------------------------------------------------- Meldungen */

    public static function meldung(string $text, string $art = 'info'): void
    {
        if ($text === '') {
            return;
        }
        echo '<div class="meldung meldung-' . Util::e($art) . '">' . Util::e($text) . '</div>';
    }
}
