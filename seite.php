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

$bausteine = (array) ($seite['bausteine'] ?? []);

Theme::kopf([
    'titel'        => (string) ($seite['seo_titel'] ?: $seite['titel']),
    'beschreibung' => (string) $seite['seo_text'],
    'canonical'    => Config::url('seite.php?h=' . rawurlencode($handle)),
]);

/*
 * Zwei Arten von Seiten: aus Bausteinen gebaute und einfache Textseiten.
 * Impressum und AGB brauchen keinen Baukasten, eine Themenseite schon –
 * deshalb entscheidet die Seite selbst, indem sie Bausteine hat oder nicht.
 */
if ($bausteine !== []) {
    foreach ($bausteine as $baustein) {
        Bausteine::rendern($baustein, $fassung);
    }
    // Ein Text im alten Feld geht nicht verloren, er steht unter den Bausteinen.
    if (trim((string) $seite['inhalt']) !== '') {
        echo '<div class="behaelter"><article class="schmal"><div class="rte">'
           . $seite['inhalt'] . '</div></article></div>';
    }
} else {
    ?>
    <div class="behaelter">
      <article class="schmal">
        <h1><?= Util::e((string) $seite['titel']) ?></h1>
        <div class="rte"><?= $seite['inhalt'] ?></div>
      </article>
    </div>
    <?php
}

Theme::fuss();
