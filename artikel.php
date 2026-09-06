<?php
/** Artikeldetailseite. */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();
$handle  = Util::get('h');

$artikel = null;
foreach ($fassung['artikel'] as $eintrag) {
    if ((string) $eintrag['handle'] === $handle) {
        $artikel = $eintrag;
        break;
    }
}

if ($artikel === null) {
    http_response_code(404);
    Theme::kopf(['titel' => 'Nicht gefunden', 'noindex' => true]);
    echo '<div class="behaelter"><div class="leer"><h1>Artikel nicht gefunden</h1>'
       . '<p>Diesen Artikel gibt es nicht (mehr).</p>'
       . '<a class="knopf" href="' . Util::e(Config::url()) . '">Zur Startseite</a></div></div>';
    Theme::fuss();
    exit;
}

$bestaende = Theme::bestaende(array_map(static fn($v) => (int) $v['id'], $artikel['varianten']));

/* Erste verfügbare Variante vorauswählen – nicht einfach die erste. */
$vorauswahl = null;
foreach ($artikel['varianten'] as $variante) {
    $frei = $bestaende[(int) $variante['id']] ?? null;
    if ($frei === null || $frei > 0) {
        $vorauswahl = $variante;
        break;
    }
}
$vorauswahl ??= ($artikel['varianten'][0] ?? null);
$ausverkauft = Theme::istAusverkauft($artikel, $bestaende);

/* Daten für die Variantenauswahl im Browser. */
$variantenDaten = array_map(static function (array $v) use ($bestaende): array {
    return [
        'id'            => (int) $v['id'],
        'preis'         => (int) $v['preis'],
        'streichpreis'  => $v['streichpreis'] !== null ? (int) $v['streichpreis'] : null,
        'artikelnummer' => (string) $v['artikelnummer'],
        'bild'          => $v['bild_url'] !== '' ? Theme::url((string) $v['bild_url']) : '',
        'optionen'      => array_values(array_filter([$v['option1'], $v['option2'], $v['option3']], static fn($o) => $o !== '')),
        'verfuegbar'    => $bestaende[(int) $v['id']] ?? null,
    ];
}, $artikel['varianten']);

$bilder = $artikel['bilder'] !== [] ? $artikel['bilder'] : [['url' => '', 'alt' => $artikel['titel']]];

$verwandte = array_values(array_filter(
    $fassung['artikel'],
    static fn($a) => (int) $a['id'] !== (int) $artikel['id']
        && ((string) $a['typ'] === (string) $artikel['typ'] || (string) $a['hersteller'] === (string) $artikel['hersteller'])
));
$verwandte = array_slice($verwandte, 0, 4);

$meldung = Util::get('meldung');
$versandseite = Theme::e('seite_versand');

Theme::kopf([
    'titel'        => (string) ($artikel['seo_titel'] ?: $artikel['titel']),
    'beschreibung' => (string) $artikel['seo_text'],
    'bild'         => (string) ($bilder[0]['url'] ?? ''),
    'canonical'    => Config::url('artikel.php?h=' . rawurlencode($handle)),
]);
?>
<div class="behaelter">
  <nav class="brotkrumen">
    <a href="<?= Util::e(Config::url()) ?>">Start</a> /
    <a href="<?= Util::e(Config::url('kategorien.php')) ?>">Kategorien</a> /
    <?= Util::e((string) $artikel['titel']) ?>
  </nav>

  <?php if ($meldung !== ''): ?>
    <div style="padding-top:var(--a4)"><?php Theme::meldung($meldung, 'fehler'); ?></div>
  <?php endif; ?>

  <div class="artikel-seite" data-artikel
       data-varianten="<?= Util::e(json_encode($variantenDaten, JSON_UNESCAPED_UNICODE)) ?>">

    <div>
      <div class="galerie-gross">
        <?php if ((string) $bilder[0]['url'] !== ''): ?>
          <img src="<?= Util::e(Theme::url((string) $bilder[0]['url'])) ?>"
               alt="<?= Util::e((string) ($bilder[0]['alt'] ?: $artikel['titel'])) ?>" data-hauptbild>
        <?php else: ?>
          <div class="kein-bild">Kein Bild</div>
        <?php endif; ?>
      </div>
      <?php if (count($bilder) > 1): ?>
        <div class="galerie-klein">
          <?php foreach ($bilder as $nr => $bild): ?>
            <button type="button" data-kleinbild="<?= Util::e(Theme::url((string) $bild['url'])) ?>"
                    aria-current="<?= $nr === 0 ? 'true' : 'false' ?>">
              <img src="<?= Util::e(Theme::url((string) $bild['url'])) ?>"
                   alt="<?= Util::e((string) ($bild['alt'] ?: $artikel['titel'])) ?>" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="artikel-info">
      <?php if (Theme::anAus('hersteller_zeigen') && (string) $artikel['hersteller'] !== ''): ?>
        <p class="hersteller"><?= Util::e((string) $artikel['hersteller']) ?></p>
      <?php endif; ?>
      <h1><?= Util::e((string) $artikel['titel']) ?></h1>
      <?php if ((string) $artikel['untertitel'] !== ''): ?>
        <p class="untertitel"><?= Util::e((string) $artikel['untertitel']) ?></p>
      <?php endif; ?>

      <?php
      $preis   = (int) ($vorauswahl['preis'] ?? 0);
      $streich = (int) ($vorauswahl['streichpreis'] ?? 0);
      $sale    = Theme::anAus('streichpreis_zeigen') && $streich > $preis;
      ?>
      <div class="preis" data-preis>
        <span class="preis-jetzt <?= $sale ? 'preis-sale' : '' ?>"><?= Util::e(Util::geld($preis)) ?></span>
        <?php if ($sale): ?>
          <span class="preis-vorher"><?= Util::e(Util::geld($streich)) ?></span>
        <?php endif; ?>
      </div>
      <?php
      $grundpreis = Util::grundpreis(
          $preis,
          (int) ($vorauswahl['inhalt_menge'] ?? 0),
          (string) ($vorauswahl['inhalt_einheit'] ?? '')
      );
      ?>
      <?php if ($grundpreis !== ''): ?>
        <p class="grundpreis" data-grundpreis><?= Util::e($grundpreis) ?></p>
      <?php endif; ?>
      <p class="steuerhinweis">
        inkl. MwSt., zzgl.
        <a href="<?= Util::e(Config::url('seite.php?h=' . rawurlencode($versandseite))) ?>">Versandkosten</a>
      </p>

      <form method="post" action="<?= Util::e(Config::url('warenkorb.php')) ?>">
        <input type="hidden" name="aktion" value="hinzufuegen">
        <input type="hidden" name="zurueck" value="<?= Util::e($handle) ?>">
        <input type="hidden" name="varianten_id" value="<?= (int) ($vorauswahl['id'] ?? 0) ?>" data-variantenfeld>

        <?php foreach ($artikel['optionen'] as $nr => $option): ?>
          <?php $index = $nr + 1; ?>
          <div class="optionsgruppe">
            <div class="titel"><?= Util::e((string) $option['name']) ?></div>
            <div class="optionswerte">
              <?php foreach ($option['werte'] as $wert): ?>
                <?php
                $gewaehlt = $vorauswahl !== null
                    && (string) ($vorauswahl['option' . $index] ?? '') === (string) $wert;
                ?>
                <button type="button" data-optionswert="<?= Util::e((string) $wert) ?>"
                        data-optionsindex="<?= $index ?>"
                        aria-pressed="<?= $gewaehlt ? 'true' : 'false' ?>">
                  <?= Util::e((string) $wert) ?>
                </button>
              <?php endforeach; ?>
            </div>
            <noscript>
              <div style="margin-top:var(--a2)">
                <select name="option<?= $index ?>">
                  <?php foreach ($option['werte'] as $wert): ?>
                    <option value="<?= Util::e((string) $wert) ?>"><?= Util::e((string) $wert) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </noscript>
          </div>
        <?php endforeach; ?>

        <?php
        $frei = $bestaende[(int) ($vorauswahl['id'] ?? 0)] ?? null;
        $klasse = $ausverkauft || $frei === 0 ? 'lager-aus' : ($frei !== null && $frei <= 5 ? 'lager-knapp' : 'lager-da');
        $text = $ausverkauft || $frei === 0
            ? 'Ausverkauft'
            : ($frei === null ? 'Sofort lieferbar'
              : ($frei <= 5 ? 'Nur noch ' . $frei . ' auf Lager' : 'Auf Lager – sofort lieferbar'));
        ?>
        <p class="lagerhinweis <?= $klasse ?>" data-lagerhinweis><?= Util::e($text) ?></p>

        <div class="kaufzeile">
          <div class="menge">
            <button type="button" data-menge="-1" aria-label="Menge verringern">−</button>
            <input type="number" name="menge" value="1" min="1" max="99" aria-label="Menge">
            <button type="button" data-menge="1" aria-label="Menge erhöhen">+</button>
          </div>
          <button class="knopf" type="submit" data-kaufknopf <?= $ausverkauft ? 'disabled' : '' ?>>
            <?= $ausverkauft ? 'Ausverkauft' : 'In den Warenkorb' ?>
          </button>
        </div>
      </form>

      <?php
      /*
       * Was direkt am Kaufknopf steht, entscheidet mit über den Abbruch:
       * Lieferzeit, Rückgaberecht und die Frage, womit überhaupt bezahlt
       * werden kann. Alle drei Angaben kommen aus den Einstellungen bzw. aus
       * den tatsächlich freigeschalteten Zahlarten – nichts davon ist
       * Dekoration.
       */
      $zusagen = [];
      if (Theme::e('lieferzeit') !== '') {
          $zusagen[] = ['lieferung', Theme::e('lieferzeit')];
      }
      $widerruf = (int) Theme::e('widerruf_tage', '14');
      if ($widerruf > 0) {
          $zusagen[] = ['ruecksendung', $widerruf . ' Tage Widerrufsrecht'];
      }
      $zahlarten = array_map(static fn(array $z): string => (string) $z['name'], Zahlung::verfuegbare());
      if ($zahlarten !== []) {
          $zusagen[] = ['zahlung', 'Zahlung: ' . implode(', ', $zahlarten)];
      }
      ?>
      <?php if ($zusagen !== []): ?>
        <ul class="kaufzusagen">
          <?php foreach ($zusagen as [$art, $text]): ?>
            <li class="zusage-<?= Util::e($art) ?>"><?= Util::e($text) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ((string) $artikel['beschreibung'] !== ''): ?>
        <div class="rte"><?= $artikel['beschreibung'] ?></div>
      <?php endif; ?>

      <?php if ((string) ($vorauswahl['artikelnummer'] ?? '') !== ''): ?>
        <p class="klein nebentext" data-artikelnummer>
          Art.-Nr.: <?= Util::e((string) $vorauswahl['artikelnummer']) ?>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($verwandte !== []): ?>
    <?php $verwandteBestaende = Theme::bestaende(Theme::variantenIds($verwandte)); ?>
    <section class="abschnitt">
      <div class="abschnitt-kopf"><h2>Passt dazu</h2></div>
      <div class="raster">
        <?php foreach ($verwandte as $eintrag) { Theme::kachel($eintrag, $verwandteBestaende); } ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php Theme::fuss(); ?>
