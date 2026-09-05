<?php
/** Eine Kategorie mit ihren Artikeln. */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();
$handle  = Util::get('h');

$kategorie = null;
foreach ($fassung['kategorien'] as $eintrag) {
    if ((string) $eintrag['handle'] === $handle) {
        $kategorie = $eintrag;
        break;
    }
}

if ($kategorie === null) {
    http_response_code(404);
    Theme::kopf(['titel' => 'Nicht gefunden', 'noindex' => true]);
    echo '<div class="behaelter"><div class="leer"><h1>Kategorie nicht gefunden</h1>'
       . '<p>Diese Kategorie gibt es nicht (mehr).</p>'
       . '<a class="knopf" href="' . Util::e(Config::url('kategorien.php')) . '">Alle Kategorien</a></div></div>';
    Theme::fuss();
    exit;
}

/* Artikel dieser Kategorie aus der Fassung holen. */
$nachId = [];
foreach ($fassung['artikel'] as $artikel) {
    $nachId[(int) $artikel['id']] = $artikel;
}
$artikel = [];
foreach ($kategorie['artikel_ids'] as $id) {
    if (isset($nachId[(int) $id])) {
        $artikel[] = $nachId[(int) $id];
    }
}

/* Sortierung – die Auswahl des Besuchers geht vor die gespeicherte. */
$sortierungen = [
    'standard'  => 'Empfohlen',
    'titel'     => 'Name A–Z',
    'preis-auf' => 'Preis aufsteigend',
    'preis-ab'  => 'Preis absteigend',
    'neueste'   => 'Neueste zuerst',
];
$sortierung = Util::einesVon(Util::get('sort', 'standard'), array_keys($sortierungen), 'standard');

usort($artikel, static function (array $a, array $b) use ($sortierung): int {
    return match ($sortierung) {
        'titel'     => strcoll((string) $a['titel'], (string) $b['titel']),
        'preis-auf' => (int) $a['preis_min'] <=> (int) $b['preis_min'],
        'preis-ab'  => (int) $b['preis_min'] <=> (int) $a['preis_min'],
        'neueste'   => strcmp((string) $b['aktiv_seit'], (string) $a['aktiv_seit']),
        default     => 0,
    };
});

/* Seitenweise ausgeben. */
const PRO_SEITE = 24;
$seite    = max(1, Util::getInt('s', 1));
$seiten   = max(1, (int) ceil(count($artikel) / PRO_SEITE));
$seite    = min($seite, $seiten);
$sichtbar = array_slice($artikel, ($seite - 1) * PRO_SEITE, PRO_SEITE);
$bestaende = Theme::bestaende(Theme::variantenIds($sichtbar));

Theme::kopf([
    'titel'        => (string) ($kategorie['seo_titel'] ?: $kategorie['titel']),
    'beschreibung' => (string) $kategorie['seo_text'],
    'bild'         => (string) $kategorie['bild_url'],
    'canonical'    => Config::url('kategorie.php?h=' . rawurlencode($handle)),
]);
?>
<div class="behaelter">
  <nav class="brotkrumen">
    <a href="<?= Util::e(Config::url()) ?>">Start</a> /
    <a href="<?= Util::e(Config::url('kategorien.php')) ?>">Kategorien</a> /
    <?= Util::e((string) $kategorie['titel']) ?>
  </nav>

  <div class="seitenkopf">
    <h1><?= Util::e((string) $kategorie['titel']) ?></h1>
    <?php if ((string) $kategorie['beschreibung'] !== ''): ?>
      <div class="rte"><?= $kategorie['beschreibung'] ?></div>
    <?php endif; ?>
  </div>

  <div class="werkzeugleiste">
    <span class="trefferzahl"><?= count($artikel) ?> Artikel</span>
    <form method="get" action="<?= Util::e(Config::url('kategorie.php')) ?>">
      <input type="hidden" name="h" value="<?= Util::e($handle) ?>">
      <label class="nur-vorlesen" for="sort">Sortierung</label>
      <select id="sort" name="sort" onchange="this.form.submit()">
        <?php foreach ($sortierungen as $wert => $label): ?>
          <option value="<?= Util::e($wert) ?>" <?= $wert === $sortierung ? 'selected' : '' ?>>
            <?= Util::e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="knopf knopf-klein knopf-leer" type="submit">Sortieren</button></noscript>
    </form>
  </div>

  <?php if ($sichtbar !== []): ?>
    <div class="raster">
      <?php foreach ($sichtbar as $eintrag) { Theme::kachel($eintrag, $bestaende); } ?>
    </div>
  <?php else: ?>
    <div class="leer"><p>In dieser Kategorie sind noch keine Artikel.</p></div>
  <?php endif; ?>

  <?php if ($seiten > 1): ?>
    <nav class="blaetterei" aria-label="Seiten">
      <?php
      $link = static fn(int $nr): string => Config::url('kategorie.php?h=' . rawurlencode($handle)
          . '&sort=' . rawurlencode($sortierung) . '&s=' . $nr);
      if ($seite > 1): ?>
        <a href="<?= Util::e($link($seite - 1)) ?>" rel="prev">Zurück</a>
      <?php endif; ?>
      <?php for ($nr = 1; $nr <= $seiten; $nr++): ?>
        <?php if ($nr === $seite): ?>
          <span aria-current="page"><?= $nr ?></span>
        <?php elseif ($nr === 1 || $nr === $seiten || abs($nr - $seite) <= 2): ?>
          <a href="<?= Util::e($link($nr)) ?>"><?= $nr ?></a>
        <?php elseif (abs($nr - $seite) === 3): ?>
          <span>…</span>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($seite < $seiten): ?>
        <a href="<?= Util::e($link($seite + 1)) ?>" rel="next">Weiter</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
<?php Theme::fuss(); ?>
