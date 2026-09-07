<?php
/**
 * Startseite des Shops.
 *
 * Zwei Wege, wie bei seite.php: Liegen im Backend unter „Startseite“
 * Bausteine, bauen die die Seite. Sonst greift der eingebaute Aufbau darunter
 * – Bühne, neue Artikel, Kategorien. So steht nach einem Update niemand vor
 * einer leeren Startseite, nur weil es die Möglichkeit jetzt gibt.
 */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();

$bausteine = (array) ($fassung['startseite']['bausteine'] ?? []);
if ($bausteine !== []) {
    Theme::kopf([
        'titel'        => Theme::e('start_seo_titel'),
        'beschreibung' => Theme::e('start_seo_text'),
        'canonical'    => Config::url(),
    ]);
    foreach ($bausteine as $baustein) {
        Bausteine::rendern($baustein, $fassung);
    }
    Theme::fuss();
    exit;
}

$neueste = array_slice($fassung['artikel'], 0, 8);
$bestaende = Theme::bestaende(Theme::variantenIds($neueste));
$noten     = Theme::bewertungen($neueste);

$buehnenbild = Theme::e('start_bild');
$stil = $buehnenbild !== ''
    ? 'background-image:linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.35)),url(\''
      . Util::e(str_replace(["'", '"', '\\'], '', Theme::url($buehnenbild))) . '\')'
    : '';

Theme::kopf(['canonical' => Config::url()]);
?>

<section class="buehne <?= $buehnenbild !== '' ? 'mit-bild' : '' ?>" style="<?= $stil ?>">
  <div class="behaelter buehne-innen">
    <h1><?= Util::e(Theme::e('start_titel')) ?></h1>
    <p><?= Util::e(Theme::e('start_text')) ?></p>
    <?php if (Theme::e('start_knopf') !== ''): ?>
      <a class="knopf" href="<?= Util::e(Theme::url(Theme::e('start_knopf_url'))) ?>">
        <?= Util::e(Theme::e('start_knopf')) ?>
      </a>
    <?php endif; ?>
  </div>
  <?php
  /*
   * Runder Stempel auf der Bühne, etwa "seit 1998 · Brandenburg". Kleine
   * Betriebe verkaufen über Herkunft und Dauer – deshalb steht das nicht
   * kleingedruckt im Impressum. Ohne Text im Backend erscheint nichts.
   */
  ?>
  <?php if (Theme::e('stempel_text') !== ''): ?>
    <div class="stempel">
      <span class="stempel-text"><?= Util::e(Theme::e('stempel_text')) ?></span>
      <?php if (Theme::e('stempel_ort') !== ''): ?>
        <span class="stempel-ort"><?= Util::e(Theme::e('stempel_ort')) ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<section class="abschnitt">
  <div class="behaelter">
    <div class="abschnitt-kopf">
      <h2>Neu im Shop</h2>
      <a href="<?= Util::e(Config::url('kategorien.php')) ?>">Alle Kategorien</a>
    </div>
    <?php if ($neueste !== []): ?>
      <div class="raster">
        <?php foreach ($neueste as $artikel) { Theme::kachel($artikel, $bestaende, $noten); } ?>
      </div>
    <?php else: ?>
      <div class="leer">
        <p>Noch keine Artikel veröffentlicht.</p>
        <p class="klein">Lege im Backend Artikel an und klicke dort auf „Veröffentlichen“.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($fassung['kategorien'] !== []): ?>
<section class="abschnitt" style="background:var(--flaeche)">
  <div class="behaelter">
    <div class="abschnitt-kopf"><h2>Kategorien</h2></div>
    <div class="raster">
      <?php foreach (array_slice($fassung['kategorien'], 0, 8) as $kategorie): ?>
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
  </div>
</section>
<?php endif; ?>

<?php Theme::fuss(); ?>
