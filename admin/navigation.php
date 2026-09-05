<?php
/**
 * Navigation.
 *
 * Zwei Menüs: Kopf- und Fußzeile. Das Kopfmenü darf eine zweite Ebene haben –
 * tiefer verschachtelte Shop-Navigationen sind auf Mobilgeräten unbedienbar.
 */

$seitentitel = 'Navigation';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

if (Util::isPost()) {
    Auth::csrfPruefen();

    foreach (['haupt', 'fuss'] as $menue) {
        $punkte  = [];
        $labels  = Util::postArray($menue . '_label');
        $urls    = Util::postArray($menue . '_url');
        $eltern  = Util::postArray($menue . '_eltern');

        // Erst die Wurzelpunkte anlegen, dann die Kinder einhängen: die
        // Reihenfolge im Formular kann beliebig sein.
        $wurzeln = [];
        foreach ($labels as $nr => $label) {
            if (trim((string) $label) === '' || (string) ($eltern[$nr] ?? '') !== '') {
                continue;
            }
            $wurzeln[(string) $nr] = ['label' => (string) $label, 'url' => (string) ($urls[$nr] ?? ''), 'kinder' => []];
        }
        foreach ($labels as $nr => $label) {
            $elternNr = (string) ($eltern[$nr] ?? '');
            if (trim((string) $label) === '' || $elternNr === '' || !isset($wurzeln[$elternNr])) {
                continue;
            }
            $wurzeln[$elternNr]['kinder'][] = ['label' => (string) $label, 'url' => (string) ($urls[$nr] ?? '')];
        }
        Inhalte::menueSetzen($menue, array_values($wurzeln));
    }

    Util::redirect('navigation.php?meldung=' . rawurlencode('Navigation gespeichert.'));
}

$menues = ['haupt' => 'Hauptmenü (Kopfzeile)', 'fuss' => 'Fußzeile'];
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Navigation</h1>
    <div class="ad-untertitel">Menüpunkte für Kopf- und Fußzeile des Shops</div>
  </div>
  <div class="ad-aktionen">
    <button class="ad-knopf ad-knopf-voll" type="submit" form="navform">Speichern</button>
  </div>
</div>

<div class="ad-hinweis ad-hinweis-info">
  <strong>Adressen im Shop</strong>
  Kategorie: <code>kategorie.php?h=handle</code> ·
  Artikel: <code>artikel.php?h=handle</code> ·
  Seite: <code>seite.php?h=handle</code> ·
  Journal: <code>journal.php</code> · Startseite: <code>index.php</code>
</div>

<form id="navform" method="post">
  <?= Auth::csrfFeld() ?>

  <?php foreach ($menues as $schluessel => $bezeichnung): ?>
    <?php $punkte = Inhalte::menue($schluessel); ?>
    <section class="ad-karte">
      <div class="ad-karte-kopf">
        <h2><?= Util::e($bezeichnung) ?></h2>
        <button class="ad-knopf ad-knopf-klein" type="button"
                data-zeile-hinzu="#<?= $schluessel ?>_liste"
                data-vorlage="#<?= $schluessel ?>_vorlage">Punkt hinzufügen</button>
      </div>
      <div class="ad-karte-inhalt">
        <div id="<?= $schluessel ?>_liste">
          <?php $nr = 0; ?>
          <?php foreach ($punkte as $punkt): ?>
            <?php $eigeneNr = $nr++; ?>
            <div class="ad-block">
              <div class="ad-feldzeile" style="margin-bottom:8px">
                <div class="ad-feld" style="margin:0">
                  <label>Beschriftung</label>
                  <input type="text" name="<?= $schluessel ?>_label[<?= $eigeneNr ?>]"
                         value="<?= Util::e((string) $punkt['label']) ?>">
                </div>
                <div class="ad-feld" style="margin:0">
                  <label>Adresse</label>
                  <input type="text" name="<?= $schluessel ?>_url[<?= $eigeneNr ?>]"
                         value="<?= Util::e((string) $punkt['url']) ?>">
                </div>
              </div>
              <input type="hidden" name="<?= $schluessel ?>_eltern[<?= $eigeneNr ?>]" value="">
              <div class="ad-knopfgruppe">
                <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
                        data-zeile-weg=".ad-block">Entfernen</button>
              </div>

              <?php foreach ($punkt['kinder'] as $kind): ?>
                <?php $kindNr = $nr++; ?>
                <div class="ad-block" style="margin:10px 0 0 24px;background:var(--flaeche)">
                  <div class="ad-feldzeile" style="margin-bottom:8px">
                    <div class="ad-feld" style="margin:0">
                      <label>Unterpunkt</label>
                      <input type="text" name="<?= $schluessel ?>_label[<?= $kindNr ?>]"
                             value="<?= Util::e((string) $kind['label']) ?>">
                    </div>
                    <div class="ad-feld" style="margin:0">
                      <label>Adresse</label>
                      <input type="text" name="<?= $schluessel ?>_url[<?= $kindNr ?>]"
                             value="<?= Util::e((string) $kind['url']) ?>">
                    </div>
                  </div>
                  <input type="hidden" name="<?= $schluessel ?>_eltern[<?= $kindNr ?>]" value="<?= $eigeneNr ?>">
                  <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
                          data-zeile-weg=".ad-block">Entfernen</button>
                </div>
              <?php endforeach; ?>

              <?php if ($schluessel === 'haupt'): ?>
                <p class="ad-tipp" style="margin:8px 0 0">
                  Unterpunkte lassen sich nach dem Speichern über den Knopf oben ergänzen
                  und dann diesem Punkt zuordnen.
                </p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($punkte === []): ?>
          <p class="ad-tipp">Noch keine Menüpunkte.</p>
        <?php endif; ?>
      </div>
    </section>

    <template id="<?= $schluessel ?>_vorlage">
      <div class="ad-block">
        <div class="ad-feldzeile" style="margin-bottom:8px">
          <div class="ad-feld" style="margin:0"><label>Beschriftung</label>
            <input type="text" name="<?= $schluessel ?>_label[__N__]" value="Neuer Punkt"></div>
          <div class="ad-feld" style="margin:0"><label>Adresse</label>
            <input type="text" name="<?= $schluessel ?>_url[__N__]" value="index.php"></div>
        </div>
        <input type="hidden" name="<?= $schluessel ?>_eltern[__N__]" value="">
        <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
                data-zeile-weg=".ad-block">Entfernen</button>
      </div>
    </template>
  <?php endforeach; ?>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
