<?php
/**
 * Die Startseite aus Bausteinen.
 *
 * Die Startseite war lange die einzige Stelle, an die der Betreiber nicht
 * herankam: Sie lag fest in index.php. Jetzt ist sie derselbe Baukasten wie
 * jede andere Seite – nur ohne Kürzel, ohne Löschknopf und ohne Eintrag im
 * Seitenverzeichnis. Ihre Bausteine hängen an Bausteine::START.
 *
 * Solange kein Baustein angelegt ist, zeigt der Shop weiter den eingebauten
 * Aufbau (Bühne, neue Artikel, Kategorien). Niemand steht also plötzlich vor
 * einer leeren Startseite, nur weil es die Möglichkeit jetzt gibt.
 */

$seitentitel = 'Startseite';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    try {
        if (Util::post('aktion') === 'leeren') {
            Bausteine::setzen(Bausteine::START, []);
            Util::redirect('startseite.php?meldung=' . rawurlencode(
                'Bausteine entfernt – der Shop zeigt wieder die eingebaute Startseite.'));
        }

        Settings::setMany([
            'start_seo_titel' => Util::post('start_seo_titel'),
            'start_seo_text'  => Util::post('start_seo_text'),
        ]);

        $roh = Util::postArray('bs');
        usort($roh, static fn($a, $b): int => (int) ($a['pos'] ?? 0) <=> (int) ($b['pos'] ?? 0));
        Bausteine::setzen(Bausteine::START, array_map(static fn($b): array => [
            'typ'   => (string) ($b['typ'] ?? ''),
            'daten' => (array) ($b['daten'] ?? []),
        ], $roh));

        Util::redirect('startseite.php?meldung=' . rawurlencode('Startseite gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$bausteine = Bausteine::zurSeite(Bausteine::START);
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Startseite</h1>
    <div class="bk-untertitel">
      <?= $bausteine === []
          ? 'Zurzeit zeigt der Shop den eingebauten Aufbau'
          : count($bausteine) . ' Baustein' . (count($bausteine) === 1 ? '' : 'e') ?>
    </div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf" target="_blank" rel="noopener" href="<?= Util::e(Config::url()) ?>">Im Shop ansehen ↗</a>
    <button class="bk-knopf bk-knopf-voll" type="submit" form="inhaltform">Speichern</button>
  </div>
</div>

<?php if ($bausteine === []): ?>
  <div class="bk-hinweis bk-hinweis-info">
    <strong>Noch kein Baustein – der Shop zeigt die eingebaute Startseite.</strong>
    Sobald hier der erste Baustein steht, ersetzt er sie vollständig. Wer das
    Eingebaute nachbauen will, nimmt <em>Bühne mit Bild</em>, <em>Artikelraster</em>
    und <em>Kategorienraster</em>.
  </div>
<?php endif; ?>

<form id="inhaltform" method="post" class="bk-zwei bk-baukasten">
  <?= Auth::csrfFeld() ?>
  <div>
    <?php require __DIR__ . '/partials/bausteine.php'; ?>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Suchmaschinen</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld">
          <label for="start_seo_titel">SEO-Titel</label>
          <input type="text" id="start_seo_titel" name="start_seo_titel"
                 value="<?= Util::e(Settings::get('start_seo_titel')) ?>"
                 placeholder="<?= Util::e(Settings::get('shop_name') . ' – ' . Settings::get('shop_slogan')) ?>">
          <div class="bk-tipp">Leer lassen nimmt Shopname und Slogan. Rund 55 Zeichen zeigt Google an.</div>
        </div>
        <div class="bk-feld" style="margin:0">
          <label for="start_seo_text">SEO-Beschreibung</label>
          <textarea id="start_seo_text" name="start_seo_text" rows="3"><?= Util::e(Settings::get('start_seo_text')) ?></textarea>
          <div class="bk-tipp">Der Satz unter dem Titel im Suchergebnis. Rund 150 Zeichen.</div>
        </div>
      </div>
    </section>
  </div>

  <div>
    <?php require __DIR__ . '/partials/vorschaukarte.php'; ?>

    <?php if ($bausteine !== []): ?>
      <section class="bk-karte"><div class="bk-karte-inhalt">
        <button class="bk-knopf bk-knopf-rot" type="submit" name="aktion" value="leeren"
                formnovalidate style="width:100%"
                data-frage="Alle Bausteine der Startseite entfernen? Der Shop zeigt danach wieder den eingebauten Aufbau.">
          Auf die eingebaute Startseite zurück
        </button>
      </div></section>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
