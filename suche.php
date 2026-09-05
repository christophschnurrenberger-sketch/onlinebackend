<?php
/**
 * Suche über den veröffentlichten Katalog.
 *
 * Eine einfache lineare Suche über die Fassung. Bis einige tausend Artikel ist
 * das schneller als jeder Index und immer aktuell.
 */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();
$begriff = mb_substr(trim(Util::get('q')), 0, 100, 'UTF-8');
$treffer = [];

if ($begriff !== '') {
    $nadel = mb_strtolower($begriff, 'UTF-8');
    $bewertet = [];

    foreach ($fassung['artikel'] as $artikel) {
        $titel = mb_strtolower((string) $artikel['titel'], 'UTF-8');
        $heu   = mb_strtolower(implode(' ', [
            $artikel['titel'], $artikel['untertitel'], $artikel['hersteller'],
            $artikel['typ'], implode(' ', $artikel['schlagworte']),
        ]), 'UTF-8');

        $punkte = 0;
        if (str_contains($titel, $nadel)) {
            $punkte = 3;
        } elseif (str_contains($heu, $nadel)) {
            $punkte = 2;
        } else {
            // Artikelnummer als zweite Chance – Kunden mit Katalog suchen danach.
            foreach ($artikel['varianten'] as $variante) {
                if (str_contains(mb_strtolower((string) $variante['artikelnummer'], 'UTF-8'), $nadel)) {
                    $punkte = 1;
                    break;
                }
            }
        }
        if ($punkte > 0) {
            $bewertet[] = ['punkte' => $punkte, 'artikel' => $artikel];
        }
    }

    usort($bewertet, static fn($a, $b) => $b['punkte'] <=> $a['punkte']);
    $treffer = array_column(array_slice($bewertet, 0, 60), 'artikel');
}

$bestaende = Theme::bestaende(Theme::variantenIds($treffer));

Theme::kopf(['titel' => $begriff !== '' ? 'Suche: ' . $begriff : 'Suche', 'noindex' => true]);
?>
<div class="behaelter">
  <div class="seitenkopf">
    <h1>Suche</h1>
    <form action="<?= Util::e(Config::url('suche.php')) ?>" role="search" style="max-width:420px">
      <div class="feld">
        <label class="nur-vorlesen" for="sq">Suchbegriff</label>
        <input type="search" id="sq" name="q" value="<?= Util::e($begriff) ?>" placeholder="Wonach suchst du?">
      </div>
    </form>
  </div>

  <?php if ($begriff !== ''): ?>
    <p class="trefferzahl"><?= count($treffer) ?> Treffer für „<?= Util::e($begriff) ?>“</p>
  <?php endif; ?>

  <?php if ($treffer !== []): ?>
    <div class="raster" style="padding-bottom:var(--a8)">
      <?php foreach ($treffer as $artikel) { Theme::kachel($artikel, $bestaende); } ?>
    </div>
  <?php elseif ($begriff !== ''): ?>
    <div class="leer"><p>Keine Treffer. Versuch es mit einem anderen Begriff.</p></div>
  <?php endif; ?>
</div>
<?php Theme::fuss(); ?>
