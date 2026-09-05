<?php
/** Übersicht aller Kategorien. */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();
Theme::kopf(['titel' => 'Kategorien', 'canonical' => Config::url('kategorien.php')]);
?>
<div class="behaelter">
  <div class="seitenkopf"><h1>Kategorien</h1></div>
  <?php if ($fassung['kategorien'] === []): ?>
    <div class="leer"><p>Es sind noch keine Kategorien veröffentlicht.</p></div>
  <?php else: ?>
    <div class="raster" style="padding-bottom:var(--a8)">
      <?php foreach ($fassung['kategorien'] as $kategorie): ?>
        <article class="kachel">
          <a href="<?= Util::e(Config::url('kategorie.php?h=' . rawurlencode((string) $kategorie['handle']))) ?>">
            <div class="kachel-bild">
              <?php if ((string) $kategorie['bild_url'] !== ''): ?>
                <img src="<?= Util::e(Theme::url((string) $kategorie['bild_url'])) ?>"
                     alt="<?= Util::e((string) $kategorie['titel']) ?>" loading="lazy">
              <?php else: ?>
                <div class="kein-bild"><?= Util::e((string) $kategorie['titel']) ?></div>
              <?php endif; ?>
            </div>
            <h3><?= Util::e((string) $kategorie['titel']) ?></h3>
            <p class="hersteller"><?= count($kategorie['artikel_ids']) ?> Artikel</p>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php Theme::fuss(); ?>
