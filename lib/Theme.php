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
    /** @var array<string,mixed>|null Die live geschaltete Fassung. */
    private static ?array $fassung = null;

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
<style>
:root {
<?= self::cssVariablen() ?>
}
<?= self::eigenesCss() ?>
</style>
</head>
<body>
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
        <?= Util::e($shopName) ?>
      <?php endif; ?>
    </a>
    <nav>
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
    </nav>
    <div class="kopf-aktionen">
      <form class="suchfeld" action="<?= Util::e(Config::url('suche.php')) ?>" role="search">
        <label class="nur-vorlesen" for="q">Suche</label>
        <input type="search" id="q" name="q" placeholder="Suchen…" value="<?= Util::e(Util::get('q')) ?>">
      </form>
      <a class="warenkorb-link" href="<?= Util::e(Config::url('warenkorb.php')) ?>">
        Warenkorb <span class="warenkorb-zahl" data-warenkorb-zahl><?= (int) $anzahl ?></span>
      </a>
    </div>
  </div>
</header>

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
      <div>
        <h4><?= Util::e(self::e('shop_name')) ?></h4>
        <p class="nebentext"><?= Util::e(self::e('fusszeile_text') ?: self::e('shop_beschreibung')) ?></p>
        <?php
        $sozial = array_filter([
            'Instagram' => self::e('social_instagram'),
            'Facebook'  => self::e('social_facebook'),
        ]);
        if ($sozial !== []): ?>
          <ul>
            <?php foreach ($sozial as $name => $url): ?>
              <li><a href="<?= Util::e($url) ?>" rel="noopener"><?= Util::e($name) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <?php if (!empty($fassung['menues']['fuss'])): ?>
      <div>
        <h4>Shop</h4>
        <ul>
          <?php foreach ($fassung['menues']['fuss'] as $punkt): ?>
            <li><a href="<?= Util::e(self::url((string) $punkt['url'])) ?>"><?= Util::e((string) $punkt['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($rechtsseiten !== []): ?>
      <div>
        <h4>Rechtliches</h4>
        <ul>
          <?php foreach ($rechtsseiten as [$label, $handle]): ?>
            <li><a href="<?= Util::e(Config::url('seite.php?h=' . rawurlencode($handle))) ?>"><?= Util::e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div>
        <h4>Kontakt</h4>
        <ul>
          <?php if (self::e('shop_email') !== ''): ?>
            <li><a href="mailto:<?= Util::e(self::e('shop_email')) ?>"><?= Util::e(self::e('shop_email')) ?></a></li>
          <?php endif; ?>
          <?php if (self::e('shop_telefon') !== ''): ?><li><?= Util::e(self::e('shop_telefon')) ?></li><?php endif; ?>
          <?php if (self::e('ort') !== ''): ?>
            <li><?= Util::e(trim(self::e('strasse') . ', ' . self::e('plz') . ' ' . self::e('ort'), ' ,')) ?></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

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
        $sale        = ((int) $artikel['streich_max']) > ((int) $artikel['preis_max']);
        $ausverkauft = self::istAusverkauft($artikel, $bestaende);
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
              <?php elseif ($sale && self::anAus('streichpreis_zeigen')): ?>
                <span class="marker">Sale</span>
              <?php endif; ?>
            </div>
            <?php if (self::anAus('hersteller_zeigen') && (string) $artikel['hersteller'] !== ''): ?>
              <p class="hersteller"><?= Util::e((string) $artikel['hersteller']) ?></p>
            <?php endif; ?>
            <h3><?= Util::e((string) $artikel['titel']) ?></h3>
            <?= self::preisText($artikel) ?>
          </a>
        </article>
        <?php
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
