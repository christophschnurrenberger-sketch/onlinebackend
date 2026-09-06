<?php
/**
 * Der Seitenbaukasten im Backend.
 *
 * Eingebunden von inhalte.php, wenn eine Seite bearbeitet wird. Erwartet:
 *   $bausteine  Reihe der vorhandenen Bausteine
 *
 * Die Bausteine liegen als Karten untereinander und lassen sich mit der Maus
 * umsortieren (admin.js). Jede Karte trägt ein verstecktes Feld "pos"; beim
 * Speichern zählt diese Zahl, nicht die Reihenfolge im Formular. Ohne
 * JavaScript bleibt die Reihenfolge, wie sie ist – bearbeiten lässt sich alles
 * trotzdem.
 */

/** Zeichnet ein einzelnes Feld. */
$bsFeld = static function (string $name, array $feld, string $wert): void {
    [$art, $label] = $feld;
    $tipp = $feld[2] ?? '';
    // Die Kennung wird aus dem Feldnamen gebaut, nicht gehasht: nur so trägt
    // sie die laufende Nummer mit, die admin.js in den Vorlagen ersetzt.
    $id   = 'f-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
    echo '<div class="bk-feld"><label for="' . $id . '">' . Util::e($label) . '</label>';
    if ($art === 'mehrzeilig') {
        echo '<textarea id="' . $id . '" name="' . Util::e($name) . '" rows="3">' . Util::e($wert) . '</textarea>';
    } elseif ($art === 'html') {
        echo '<textarea id="' . $id . '" name="' . Util::e($name) . '" rows="8" class="bk-code">' . Util::e($wert) . '</textarea>';
    } elseif ($art === 'zahl') {
        echo '<input type="number" id="' . $id . '" name="' . Util::e($name) . '" value="' . Util::e($wert) . '">';
    } else {
        echo '<input type="text" id="' . $id . '" name="' . Util::e($name) . '" value="' . Util::e($wert) . '"'
           . ($art === 'bild' ? ' placeholder="uploads/bild.jpg"' : '') . '>';
    }
    if ($tipp !== '') {
        echo '<div class="bk-tipp">' . Util::e($tipp) . '</div>';
    }
    echo '</div>';
};

/** Zeichnet eine Bausteinkarte. */
$bsKarte = static function (string $typ, int $index, array $daten) use ($bsFeld): void {
    $muster = Bausteine::TYPEN[$typ];
    $p      = 'bs[' . $index . ']';
    ?>
    <div class="bk-baustein" data-sortier-element draggable="true">
      <div class="bk-baustein-kopf">
        <span class="bk-griff" title="Zum Verschieben ziehen">⠿</span>
        <?php /* Ziehen geht mit der Maus. Für Telefon und Tastatur zusätzlich
                 zwei Knöpfe – sonst wäre die Reihenfolge dort nicht änderbar. */ ?>
        <button class="bk-knopf bk-knopf-klein bk-knopf-leer" type="button"
                data-sortier-hoch title="Nach oben">↑</button>
        <button class="bk-knopf bk-knopf-klein bk-knopf-leer" type="button"
                data-sortier-runter title="Nach unten">↓</button>
        <strong><?= Util::e($muster['name']) ?></strong>
        <span class="bk-tipp"><?= Util::e($muster['text']) ?></span>
        <button class="bk-knopf bk-knopf-klein bk-knopf-leer bk-knopf-rot" type="button"
                data-zeile-weg=".bk-baustein" title="Baustein entfernen">✕</button>
      </div>
      <input type="hidden" name="<?= $p ?>[typ]" value="<?= Util::e($typ) ?>">
      <input type="hidden" name="<?= $p ?>[pos]" value="<?= $index ?>" data-sortier-pos>
      <div class="bk-baustein-felder">
        <?php foreach ($muster['felder'] as $schluessel => $feld): ?>
          <?php $bsFeld($p . '[daten][' . $schluessel . ']', $feld, (string) ($daten[$schluessel] ?? '')); ?>
        <?php endforeach; ?>
      </div>

      <?php if (isset($muster['liste'])): ?>
        <?php
        $liste  = $muster['liste'];
        $zeilen = (array) ($daten[$liste['schluessel']] ?? []);
        // Vorhandene Zeilen plus drei leere: so lässt sich nachtragen, ohne
        // dass die Maske mit zwölf leeren Blöcken anfängt.
        $anzahl = min((int) $liste['max'], count($zeilen) + 3);
        ?>
        <div class="bk-baustein-liste">
          <div class="bk-tipp" style="margin-bottom:8px">
            <?= Util::e($liste['name']) ?> – leere Zeilen werden beim Speichern verworfen.
          </div>
          <?php for ($j = 0; $j < $anzahl; $j++): ?>
            <div class="bk-baustein-zeile">
              <?php foreach ($liste['felder'] as $schluessel => $feld): ?>
                <?php $bsFeld(
                    $p . '[daten][' . $liste['schluessel'] . '][' . $j . '][' . $schluessel . ']',
                    $feld,
                    (string) ($zeilen[$j][$schluessel] ?? '')
                ); ?>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
};
?>

<section class="bk-karte">
  <div class="bk-karte-kopf">
    <h2>Bausteine</h2>
    <div class="bk-baustein-neu">
      <select id="bausteinwahl">
        <?php foreach (Bausteine::TYPEN as $schluessel => $muster): ?>
          <option value="<?= Util::e($schluessel) ?>"><?= Util::e($muster['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="bk-knopf bk-knopf-klein" type="button" data-baustein-hinzu>Hinzufügen</button>
    </div>
  </div>
  <div class="bk-karte-inhalt">
    <div class="bk-hinweis bk-hinweis-info" style="margin-top:0">
      Bausteine bauen die Seite von oben nach unten. Mit der Maus am Griff
      <span class="bk-griff">⠿</span> lassen sie sich umsortieren. Hat eine Seite keinen
      einzigen Baustein, erscheint sie als schlichte Textseite – richtig für Impressum und AGB.
    </div>

    <div id="bausteinliste" data-sortier>
      <?php foreach ($bausteine as $i => $baustein): ?>
        <?php $bsKarte((string) $baustein['typ'], $i, (array) $baustein['daten']); ?>
      <?php endforeach; ?>
    </div>

    <?php if ($bausteine === []): ?>
      <p class="bk-tipp" id="bausteine-leer">Noch keine Bausteine. Oben rechts einen auswählen und hinzufügen.</p>
    <?php endif; ?>
  </div>
</section>

<?php /* Vorlagen für neue Bausteine. __I__ ersetzt admin.js durch die laufende Nummer. */ ?>
<?php foreach (Bausteine::TYPEN as $schluessel => $muster): ?>
  <template id="bs-vorlage-<?= Util::e($schluessel) ?>">
    <?php
    ob_start();
    $bsKarte($schluessel, 999999, []);
    echo str_replace('999999', '__I__', (string) ob_get_clean());
    ?>
  </template>
<?php endforeach; ?>
