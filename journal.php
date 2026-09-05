<?php
/** Journal – Übersicht der Beiträge, oder ein einzelner Beitrag über ?h=. */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();
$handle  = Util::get('h');

if ($handle !== '') {
    $beitrag = null;
    foreach ($fassung['beitraege'] as $eintrag) {
        if ((string) $eintrag['handle'] === $handle) {
            $beitrag = $eintrag;
            break;
        }
    }
    if ($beitrag === null) {
        http_response_code(404);
        Theme::kopf(['titel' => 'Nicht gefunden', 'noindex' => true]);
        echo '<div class="behaelter"><div class="leer"><h1>Beitrag nicht gefunden</h1>'
           . '<a class="knopf" href="' . Util::e(Config::url('journal.php')) . '">Zum Journal</a></div></div>';
        Theme::fuss();
        exit;
    }

    Theme::kopf([
        'titel'        => (string) ($beitrag['seo_titel'] ?: $beitrag['titel']),
        'beschreibung' => (string) $beitrag['seo_text'],
        'bild'         => (string) $beitrag['bild_url'],
        'canonical'    => Config::url('journal.php?h=' . rawurlencode($handle)),
    ]);
    ?>
    <div class="behaelter">
      <article class="schmal">
        <nav class="brotkrumen">
          <a href="<?= Util::e(Config::url()) ?>">Start</a> /
          <a href="<?= Util::e(Config::url('journal.php')) ?>">Journal</a>
        </nav>
        <h1><?= Util::e((string) $beitrag['titel']) ?></h1>
        <p class="nebentext">
          <?php if ((string) $beitrag['sichtbar_seit'] !== ''): ?>
            <?= Util::e(Util::dt((string) $beitrag['sichtbar_seit'], 'd.m.Y')) ?>
          <?php endif; ?>
          <?php if ((string) $beitrag['autor'] !== ''): ?> · <?= Util::e((string) $beitrag['autor']) ?><?php endif; ?>
        </p>
        <?php if ((string) $beitrag['bild_url'] !== ''): ?>
          <img src="<?= Util::e(Theme::url((string) $beitrag['bild_url'])) ?>"
               alt="<?= Util::e((string) $beitrag['titel']) ?>"
               style="border-radius:var(--ecken);margin:var(--a5) 0">
        <?php endif; ?>
        <div class="rte"><?= $beitrag['inhalt'] ?></div>
      </article>
    </div>
    <?php
    Theme::fuss();
    exit;
}

Theme::kopf(['titel' => 'Journal', 'canonical' => Config::url('journal.php')]);
?>
<div class="behaelter">
  <div class="seitenkopf"><h1>Journal</h1></div>
  <?php if ($fassung['beitraege'] === []): ?>
    <div class="leer"><p>Noch keine Beiträge veröffentlicht.</p></div>
  <?php else: ?>
    <div class="beitragsraster" style="padding-bottom:var(--a8)">
      <?php foreach ($fassung['beitraege'] as $beitrag): ?>
        <article class="beitragskachel">
          <a href="<?= Util::e(Config::url('journal.php?h=' . rawurlencode((string) $beitrag['handle']))) ?>">
            <div class="bild">
              <?php if ((string) $beitrag['bild_url'] !== ''): ?>
                <img src="<?= Util::e(Theme::url((string) $beitrag['bild_url'])) ?>"
                     alt="<?= Util::e((string) $beitrag['titel']) ?>" loading="lazy">
              <?php endif; ?>
            </div>
            <?php if ((string) $beitrag['sichtbar_seit'] !== ''): ?>
              <time datetime="<?= Util::e((string) $beitrag['sichtbar_seit']) ?>">
                <?= Util::e(Util::dt((string) $beitrag['sichtbar_seit'], 'd.m.Y')) ?>
              </time>
            <?php endif; ?>
            <h3><?= Util::e((string) $beitrag['titel']) ?></h3>
            <p class="nebentext klein"><?= Util::e((string) $beitrag['anriss']) ?></p>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php Theme::fuss(); ?>
