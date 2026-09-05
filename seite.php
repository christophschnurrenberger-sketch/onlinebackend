<?php
/** Eine Inhaltsseite (Impressum, AGB, …). */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Theme::fassung();
$handle  = Util::get('h');

$seite = null;
foreach ($fassung['seiten'] as $eintrag) {
    if ((string) $eintrag['handle'] === $handle) {
        $seite = $eintrag;
        break;
    }
}

if ($seite === null) {
    http_response_code(404);
    Theme::kopf(['titel' => 'Nicht gefunden', 'noindex' => true]);
    echo '<div class="behaelter"><div class="leer"><h1>Seite nicht gefunden</h1>'
       . '<p>Diese Seite gibt es nicht (mehr).</p>'
       . '<a class="knopf" href="' . Util::e(Config::url()) . '">Zur Startseite</a></div></div>';
    Theme::fuss();
    exit;
}

Theme::kopf([
    'titel'        => (string) ($seite['seo_titel'] ?: $seite['titel']),
    'beschreibung' => (string) $seite['seo_text'],
    'canonical'    => Config::url('seite.php?h=' . rawurlencode($handle)),
]);
?>
<div class="behaelter">
  <article class="schmal">
    <h1><?= Util::e((string) $seite['titel']) ?></h1>
    <div class="rte"><?= $seite['inhalt'] ?></div>
  </article>
</div>
<?php Theme::fuss(); ?>
