<?php
/**
 * Kategorie anlegen und bearbeiten.
 *
 * Manuell zusammengestellt oder regelbasiert. Bei automatischen Kategorien
 * zeigt die Vorschau sofort, welche Artikel die Regeln treffen – ohne sie
 * müsste man speichern und im Shop nachsehen.
 */

$seitentitel = 'Kategorie bearbeiten';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

$id  = Util::getInt('id');
$neu = $id === 0;

if (Util::isPost()) {
    Auth::csrfPruefen();
    try {
        $regeln = [];
        foreach (Util::postArray('regel_feld') as $nr => $feld) {
            $wert = trim((string) (Util::postArray('regel_wert')[$nr] ?? ''));
            if ($wert === '') {
                continue;
            }
            $regeln[] = [
                'feld'     => (string) $feld,
                'operator' => (string) (Util::postArray('regel_operator')[$nr] ?? 'ist'),
                'wert'     => $wert,
            ];
        }

        $daten = [
            'titel'        => Util::post('titel'),
            'handle'       => Util::post('handle'),
            'beschreibung' => Util::postRaw('beschreibung'),
            'bild_url'     => Util::post('bild_url'),
            'art'          => Util::post('art'),
            'regeln'       => $regeln,
            'regel_modus'  => Util::post('regel_modus'),
            'sortierung'   => Util::post('sortierung'),
            'sichtbar'     => Util::postBool('sichtbar'),
            'seo_titel'    => Util::post('seo_titel'),
            'seo_text'     => Util::post('seo_text'),
        ];
        if (Util::post('art') === 'manuell') {
            $daten['artikel_ids'] = array_map('intval', Util::postArray('artikel_ids'));
        }

        if ($neu) {
            $id = Kategorien::anlegen($daten);
            Util::redirect('kategorie.php?id=' . $id . '&meldung=' . rawurlencode('Kategorie angelegt.'));
        }
        Kategorien::speichern($id, $daten);
        Util::redirect('kategorie.php?id=' . $id . '&meldung=' . rawurlencode('Kategorie gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

if (!$neu && Util::get('aktion') === 'loeschen') {
    Auth::csrfPruefen();
    Kategorien::loeschen($id);
    Util::redirect('kategorien.php?meldung=' . rawurlencode('Kategorie gelöscht.'));
}

$kategorie = $neu ? null : Kategorien::holen($id);
if (!$neu && $kategorie === null) {
    Util::redirect('kategorien.php?meldung=' . rawurlencode('Kategorie nicht gefunden.') . '&art=fehler');
}

$werte = $kategorie ?? [
    'titel' => '', 'handle' => '', 'beschreibung' => '', 'bild_url' => '', 'art' => 'manuell',
    'regelliste' => [], 'regel_modus' => 'alle', 'sortierung' => 'manuell', 'sichtbar' => 0,
    'seo_titel' => '', 'seo_text' => '',
];
$mitglieder = $neu ? [] : Kategorien::artikel($kategorie, false, 200);
$alleArtikel = $neu ? [] : Artikel::liste(['limit' => 250, 'sortierung' => 'titel'])['zeilen'];
$mitgliedIds = array_map(static fn($a) => (int) $a['id'], $mitglieder);
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <a class="bk-zurueck" href="kategorien.php">← Alle Kategorien</a>
    <h1><?= $neu ? 'Neue Kategorie' : Util::e((string) $werte['titel']) ?></h1>
  </div>
  <div class="bk-aktionen">
    <?php if (!$neu): ?>
      <a class="bk-knopf" target="_blank" rel="noopener"
         href="<?= Util::e(Config::url('kategorie.php?h=' . rawurlencode((string) $werte['handle']))) ?>">Im Shop ansehen ↗</a>
    <?php endif; ?>
    <button class="bk-knopf bk-knopf-voll" type="submit" form="katform">Speichern</button>
  </div>
</div>

<form id="katform" method="post" class="bk-zwei">
  <?= Auth::csrfFeld() ?>

  <div>
    <section class="bk-karte"><div class="bk-karte-inhalt">
      <div class="bk-feld">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" required data-titel-quelle value="<?= Util::e((string) $werte['titel']) ?>">
      </div>
      <div class="bk-feld">
        <label for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung" rows="5"><?= Util::e((string) $werte['beschreibung']) ?></textarea>
      </div>
      <div class="bk-feld" style="margin:0">
        <label for="bild_url">Titelbild (Adresse)</label>
        <input type="text" id="bild_url" name="bild_url" value="<?= Util::e((string) $werte['bild_url']) ?>"
               placeholder="uploads/bild.jpg">
      </div>
    </div></section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Artikel</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld">
          <label for="art">Zusammenstellung</label>
          <select id="art" name="art" onchange="
            document.getElementById('regelteil').hidden = this.value !== 'automatisch';
            document.getElementById('manuellteil').hidden = this.value !== 'manuell';">
            <option value="manuell" <?= (string) $werte['art'] === 'manuell' ? 'selected' : '' ?>>
              Manuell – ich wähle die Artikel selbst</option>
            <option value="automatisch" <?= (string) $werte['art'] === 'automatisch' ? 'selected' : '' ?>>
              Automatisch – Regeln bestimmen die Artikel</option>
          </select>
        </div>

        <div id="regelteil" <?= (string) $werte['art'] !== 'automatisch' ? 'hidden' : '' ?>>
          <div class="bk-feld">
            <label for="regel_modus">Artikel müssen</label>
            <select id="regel_modus" name="regel_modus">
              <option value="alle" <?= (string) $werte['regel_modus'] === 'alle' ? 'selected' : '' ?>>alle Bedingungen erfüllen</option>
              <option value="eine" <?= (string) $werte['regel_modus'] === 'eine' ? 'selected' : '' ?>>mindestens eine Bedingung erfüllen</option>
            </select>
          </div>
          <div id="regelliste">
            <?php foreach ($werte['regelliste'] as $regel): ?>
              <div class="bk-block">
                <div class="bk-feldzeile-drei" style="margin-bottom:8px">
                  <select name="regel_feld[]">
                    <?php foreach (Kategorien::FELDER as $wert => $label): ?>
                      <option value="<?= Util::e($wert) ?>" <?= (string) $regel['feld'] === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="regel_operator[]">
                    <?php foreach (Kategorien::OPERATOREN as $wert => $label): ?>
                      <option value="<?= Util::e($wert) ?>" <?= (string) $regel['operator'] === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="regel_wert[]" value="<?= Util::e((string) $regel['wert']) ?>" placeholder="Wert">
                </div>
                <button class="bk-knopf bk-knopf-klein bk-knopf-leer bk-knopf-rot" type="button"
                        data-zeile-weg=".bk-block">Bedingung entfernen</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="bk-knopf bk-knopf-klein" type="button"
                  data-zeile-hinzu="#regelliste" data-vorlage="#regelvorlage">Bedingung hinzufügen</button>
          <p class="bk-tipp" style="margin-top:8px">
            Beim Speichern wird die Kategorie neu ausgewertet. Beispiel: Schlagwort ist „neu“.
          </p>
        </div>

        <div id="manuellteil" <?= (string) $werte['art'] !== 'manuell' ? 'hidden' : '' ?>>
          <?php if ($neu): ?>
            <p class="bk-tipp">Nach dem Speichern kannst du hier Artikel zuordnen.</p>
          <?php else: ?>
            <p class="bk-tipp">Markiere die Artikel, die in dieser Kategorie erscheinen sollen.</p>
            <div style="max-height:340px;overflow-y:auto;border:1px solid var(--rahmen);border-radius:var(--ecken-klein);padding:10px">
              <?php foreach ($alleArtikel as $artikel): ?>
                <label class="bk-haken">
                  <input type="checkbox" name="artikel_ids[]" value="<?= (int) $artikel['id'] ?>"
                         <?= in_array((int) $artikel['id'], $mitgliedIds, true) ? 'checked' : '' ?>>
                  <span><?= Util::e((string) $artikel['titel']) ?>
                    <span class="bk-neben">· <?= Util::e(Artikel::STATUS[(string) $artikel['status']] ?? '') ?>
                      · <?= Util::e(Util::geld((int) $artikel['preis_min'])) ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!$neu && (string) $werte['art'] === 'automatisch'): ?>
          <div style="margin-top:16px">
            <h3 style="font-size:13px;margin-bottom:8px">Aktuell erfasste Artikel (<?= count($mitglieder) ?>)</h3>
            <?php if ($mitglieder === []): ?>
              <p class="bk-tipp">Die Regeln treffen derzeit keinen Artikel.</p>
            <?php else: ?>
              <div class="bk-tabelle-rahmen" style="border:1px solid var(--rahmen);border-radius:var(--ecken-klein)">
                <table><tbody>
                  <?php foreach (array_slice($mitglieder, 0, 30) as $artikel): ?>
                    <tr>
                      <td><?= Util::e((string) $artikel['titel']) ?></td>
                      <td class="bk-zahl-rechts bk-neben"><?= Util::e(Util::geld((int) $artikel['preis_min'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody></table>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

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
        <span>Im Shop sichtbar</span>
      </label>
      <div class="bk-tipp" style="margin-left:25px">Wird erst nach dem Veröffentlichen wirksam.</div>
    </div></section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Sortierung</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld" style="margin:0">
          <label for="sortierung">Reihenfolge im Shop</label>
          <select id="sortierung" name="sortierung">
            <?php foreach (Kategorien::SORTIERUNGEN as $wert => $label): ?>
              <option value="<?= Util::e($wert) ?>" <?= (string) $werte['sortierung'] === $wert ? 'selected' : '' ?>>
                <?= Util::e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <?php if (!$neu): ?>
      <section class="bk-karte"><div class="bk-karte-inhalt">
        <a class="bk-knopf bk-knopf-rot" style="width:100%;justify-content:center"
           href="kategorie.php?id=<?= $id ?>&aktion=loeschen&_token=<?= Util::e(Auth::csrfToken()) ?>"
           data-frage="Diese Kategorie löschen? Die enthaltenen Artikel bleiben bestehen.">
          Kategorie löschen
        </a>
      </div></section>
    <?php endif; ?>
  </div>
</form>

<template id="regelvorlage">
  <div class="bk-block">
    <div class="bk-feldzeile-drei" style="margin-bottom:8px">
      <select name="regel_feld[]">
        <?php foreach (Kategorien::FELDER as $wert => $label): ?>
          <option value="<?= Util::e($wert) ?>"><?= Util::e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="regel_operator[]">
        <?php foreach (Kategorien::OPERATOREN as $wert => $label): ?>
          <option value="<?= Util::e($wert) ?>"><?= Util::e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="regel_wert[]" placeholder="Wert">
    </div>
    <button class="bk-knopf bk-knopf-klein bk-knopf-leer bk-knopf-rot" type="button"
            data-zeile-weg=".bk-block">Bedingung entfernen</button>
  </div>
</template>

<?php require __DIR__ . '/partials/footer.php'; ?>
