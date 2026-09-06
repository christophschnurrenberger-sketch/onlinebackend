<?php
/**
 * Gemeinsame Maske für Seiten und Journal-Beiträge.
 *
 * Beide sind fast gleich aufgebaut; der Unterschied sind nur ein paar Felder.
 * Eingebunden von seiten.php und journal.php, die $typ vorher setzen.
 *
 * Erwartet: $typ ('seiten' oder 'beitraege')
 */

$istBeitrag = $typ === 'beitraege';
$datei      = $istBeitrag ? 'journal.php' : 'seiten.php';
$einzahl    = $istBeitrag ? 'Beitrag' : 'Seite';
$shopLink   = static fn(string $handle): string => $istBeitrag
    ? Config::url('journal.php?h=' . rawurlencode($handle))
    : Config::url('seite.php?h=' . rawurlencode($handle));

$id       = Util::getInt('id');
$anlegen  = Util::get('neu') !== '';
$maske    = $id > 0 || $anlegen;

/* --- Speichern ------------------------------------------------------------ */

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    try {
        if (Util::post('aktion') === 'loeschen') {
            $istBeitrag ? Inhalte::beitragLoeschen($id) : Inhalte::seiteLoeschen($id);
            Util::redirect($datei . '?meldung=' . rawurlencode($einzahl . ' gelöscht.'));
        }

        $daten = [
            'titel'     => Util::post('titel'),
            'handle'    => Util::post('handle'),
            'inhalt'    => Util::postRaw('inhalt'),
            'sichtbar'  => Util::postBool('sichtbar'),
            'seo_titel' => Util::post('seo_titel'),
            'seo_text'  => Util::post('seo_text'),
        ];
        if ($istBeitrag) {
            $daten['anriss']   = Util::post('anriss');
            $daten['bild_url'] = Util::post('bild_url');
            $daten['autor']    = Util::post('autor');
        }

        $neueId = $istBeitrag
            ? Inhalte::beitragSpeichern($id > 0 ? $id : null, $daten)
            : Inhalte::seiteSpeichern($id > 0 ? $id : null, $daten);

        /*
         * Bausteine der Seite. Maßgeblich ist das versteckte Feld "pos" und
         * nicht die Reihenfolge im Formular: beim Umsortieren mit der Maus
         * wandert die Karte im Dokument, ihre Feldnamen bleiben aber, wie sie
         * sind.
         */
        if (!$istBeitrag) {
            $roh = Util::postArray('bs');
            usort($roh, static fn($a, $b): int => (int) ($a['pos'] ?? 0) <=> (int) ($b['pos'] ?? 0));
            Bausteine::setzen($neueId, array_map(static fn($b): array => [
                'typ'   => (string) ($b['typ'] ?? ''),
                'daten' => (array) ($b['daten'] ?? []),
            ], $roh));
        }

        Util::redirect($datei . '?id=' . $neueId . '&meldung=' . rawurlencode($einzahl . ' gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

/* --- Bearbeitungsmaske ----------------------------------------------------- */

if ($maske) {
    $eintrag = $id > 0
        ? ($istBeitrag ? Inhalte::beitrag($id) : Inhalte::seite($id))
        : null;

    if ($id > 0 && $eintrag === null) {
        Util::redirect($datei . '?meldung=' . rawurlencode('Eintrag nicht gefunden.') . '&art=fehler');
    }

    $werte = $eintrag ?? [
        'titel' => '', 'handle' => '', 'inhalt' => '', 'sichtbar' => 0,
        'seo_titel' => '', 'seo_text' => '', 'anriss' => '', 'bild_url' => '', 'autor' => '',
        'sichtbar_seit' => null,
    ];
    ?>

    <div class="bk-seitenkopf">
      <div class="bk-titel">
        <a class="bk-zurueck" href="<?= Util::e($datei) ?>">← Alle <?= $istBeitrag ? 'Beiträge' : 'Seiten' ?></a>
        <h1><?= $id > 0 ? Util::e((string) $werte['titel']) : 'Neue' . ($istBeitrag ? 'r Beitrag' : ' Seite') ?></h1>
      </div>
      <div class="bk-aktionen">
        <?php if ($id > 0 && (int) $werte['sichtbar'] === 1): ?>
          <a class="bk-knopf" target="_blank" rel="noopener"
             href="<?= Util::e($shopLink((string) $werte['handle'])) ?>">Im Shop ansehen ↗</a>
        <?php endif; ?>
        <button class="bk-knopf bk-knopf-voll" type="submit" form="inhaltform">Speichern</button>
      </div>
    </div>

    <form id="inhaltform" method="post" class="bk-zwei">
      <?= Auth::csrfFeld() ?>
      <div>
        <section class="bk-karte"><div class="bk-karte-inhalt">
          <div class="bk-feld">
            <label for="titel">Titel</label>
            <input type="text" id="titel" name="titel" required data-titel-quelle
                   value="<?= Util::e((string) $werte['titel']) ?>">
          </div>
          <?php if ($istBeitrag): ?>
            <div class="bk-feld">
              <label for="anriss">Kurzfassung</label>
              <input type="text" id="anriss" name="anriss" value="<?= Util::e((string) $werte['anriss']) ?>">
              <div class="bk-tipp">Erscheint in der Übersicht. Leer lassen, um sie aus dem Text zu erzeugen.</div>
            </div>
          <?php endif; ?>
          <div class="bk-feld" style="margin:0">
            <label for="inhalt">Inhalt</label>
            <textarea id="inhalt" name="inhalt" rows="<?= $istBeitrag ? 20 : 8 ?>"><?= Util::e((string) $werte['inhalt']) ?></textarea>
            <div class="bk-tipp">Einfaches HTML: &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;strong&gt;, &lt;a&gt;.
              Skripte werden beim Speichern entfernt.
              <?php if (!$istBeitrag): ?>
                Für gestaltete Seiten reichen die <strong>Bausteine</strong> darunter – dieses Feld
                kann dann leer bleiben.
              <?php endif; ?></div>
          </div>
        </div></section>

        <?php if (!$istBeitrag): ?>
          <?php $bausteine = $id > 0 ? Bausteine::zurSeite($id) : []; ?>
          <?php require __DIR__ . '/bausteine.php'; ?>
        <?php endif; ?>

        <section class="bk-karte">
          <div class="bk-karte-kopf"><h2>Suchmaschinen</h2></div>
          <div class="bk-karte-inhalt">
            <div class="bk-feld">
              <label for="handle">Adresse im Shop</label>
              <input type="text" id="handle" name="handle" data-handle-ziel value="<?= Util::e((string) $werte['handle']) ?>">
            </div>
            <div class="bk-feld">
              <label for="seo_titel">SEO-Titel</label>
              <input type="text" id="seo_titel" name="seo_titel" value="<?= Util::e((string) $werte['seo_titel']) ?>">
            </div>
            <div class="bk-feld" style="margin:0">
              <label for="seo_text">SEO-Beschreibung</label>
              <textarea id="seo_text" name="seo_text" rows="3"><?= Util::e((string) $werte['seo_text']) ?></textarea>
            </div>
          </div>
        </section>
      </div>

      <div>
        <section class="bk-karte"><div class="bk-karte-inhalt">
          <label class="bk-haken" style="margin:0">
            <input type="checkbox" name="sichtbar" value="1" <?= (int) $werte['sichtbar'] === 1 ? 'checked' : '' ?>>
            <span>Veröffentlicht</span>
          </label>
          <div class="bk-tipp" style="margin-left:25px">Im Shop erst nach dem nächsten Veröffentlichen sichtbar.</div>
        </div></section>

        <?php if ($istBeitrag): ?>
          <section class="bk-karte">
            <div class="bk-karte-kopf"><h2>Beitrag</h2></div>
            <div class="bk-karte-inhalt">
              <div class="bk-feld">
                <label for="autor">Autor</label>
                <input type="text" id="autor" name="autor" value="<?= Util::e((string) $werte['autor']) ?>">
              </div>
              <div class="bk-feld" style="margin:0">
                <label for="bild_url">Titelbild (Adresse)</label>
                <input type="text" id="bild_url" name="bild_url" value="<?= Util::e((string) $werte['bild_url']) ?>"
                       placeholder="uploads/bild.jpg">
              </div>
              <?php if (!empty($werte['sichtbar_seit'])): ?>
                <div class="bk-tipp" style="margin-top:8px">
                  Veröffentlicht am <?= Util::e(Util::dt((string) $werte['sichtbar_seit'], 'd.m.Y')) ?>
                </div>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($id > 0 && Auth::darf('pflegen')): ?>
          <section class="bk-karte"><div class="bk-karte-inhalt">
            <button class="bk-knopf bk-knopf-rot" type="submit" name="aktion" value="loeschen"
                    formnovalidate style="width:100%"
                    onclick="return confirm('Diesen Eintrag endgültig löschen?')">Löschen</button>
          </div></section>
        <?php endif; ?>
      </div>
    </form>

    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/* --- Liste ----------------------------------------------------------------- */

$eintraege = $istBeitrag ? Inhalte::beitraege() : Inhalte::seiten();
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1><?= $istBeitrag ? 'Journal' : 'Seiten' ?></h1>
    <div class="bk-untertitel"><?= count($eintraege) ?> Einträge</div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf bk-knopf-voll" href="<?= Util::e($datei) ?>?neu=1"><?= Util::e($einzahl) ?> anlegen</a>
  </div>
</div>

<section class="bk-karte"><div class="bk-karte-inhalt eng">
  <?php if ($eintraege === []): ?>
    <div class="bk-leer">
      <h3>Noch keine <?= $istBeitrag ? 'Beiträge' : 'Seiten' ?></h3>
      <p><?= $istBeitrag
          ? 'Beiträge erscheinen im Shop unter „Journal“.'
          : 'Impressum, AGB und Datenschutz gehören in jeden Shop – der Installer legt Entwürfe dafür an.' ?></p>
      <a class="bk-knopf bk-knopf-voll" href="<?= Util::e($datei) ?>?neu=1"><?= Util::e($einzahl) ?> anlegen</a>
    </div>
  <?php else: ?>
    <div class="bk-tabelle-rahmen"><table>
      <thead><tr><th>Titel</th><th>Adresse</th><th>Status</th><th>Zuletzt geändert</th></tr></thead>
      <tbody>
        <?php foreach ($eintraege as $eintrag): ?>
          <tr class="bk-klick" style="cursor:pointer" onclick="location='<?= Util::e($datei) ?>?id=<?= (int) $eintrag['id'] ?>'">
            <td>
              <div class="bk-haupt"><?= Util::e((string) $eintrag['titel']) ?></div>
              <?php if ($istBeitrag && (string) $eintrag['anriss'] !== ''): ?>
                <div class="bk-neben"><?= Util::e(Util::kuerzen((string) $eintrag['anriss'], 80)) ?></div>
              <?php endif; ?>
            </td>
            <td class="bk-neben"><?= Util::e((string) $eintrag['handle']) ?></td>
            <td>
              <?php if ((int) $eintrag['sichtbar'] === 1): ?>
                <span class="bk-marke bk-marke-gruen"><i></i>Veröffentlicht</span>
              <?php else: ?>
                <span class="bk-marke"><i></i>Entwurf</span>
              <?php endif; ?>
            </td>
            <td class="bk-neben"><?= Util::e(Util::dt((string) $eintrag['geaendert'], 'd.m.Y')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div></section>

<?php require __DIR__ . '/footer.php'; ?>
