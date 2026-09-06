<?php /** Reiter „Rechtliches“. */ ?>

<div class="bk-hinweis bk-hinweis-warnung">
  <strong>Die mitgelieferten Rechtstexte sind Platzhalter</strong>
  Impressum, AGB, Datenschutz und Widerruf müssen durch geprüfte Texte ersetzt werden –
  im deutschsprachigen Handel sind sie sonst abmahnfähig. Bearbeiten unter
  <a href="seiten.php">Seiten</a>.
</div>

<form method="post">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="recht">
  <input type="hidden" name="reiter" value="recht">

  <p class="bk-tipp">Welche Seite hinter welchem Pflichtlink im Shop-Fuß steht.
    Eingetragen wird die Adresse der Seite (ihr „Handle“).</p>

  <div class="bk-feldzeile">
    <div class="bk-feld"><label for="seite_impressum">Impressum</label>
      <input type="text" id="seite_impressum" name="seite_impressum" value="<?= Util::e($e('seite_impressum')) ?>"></div>
    <div class="bk-feld"><label for="seite_datenschutz">Datenschutz</label>
      <input type="text" id="seite_datenschutz" name="seite_datenschutz" value="<?= Util::e($e('seite_datenschutz')) ?>"></div>
  </div>
  <div class="bk-feldzeile">
    <div class="bk-feld"><label for="seite_agb">AGB</label>
      <input type="text" id="seite_agb" name="seite_agb" value="<?= Util::e($e('seite_agb')) ?>"></div>
    <div class="bk-feld"><label for="seite_widerruf">Widerruf</label>
      <input type="text" id="seite_widerruf" name="seite_widerruf" value="<?= Util::e($e('seite_widerruf')) ?>"></div>
  </div>
  <div class="bk-feld"><label for="seite_versand">Versand &amp; Zahlung</label>
    <input type="text" id="seite_versand" name="seite_versand" value="<?= Util::e($e('seite_versand')) ?>"></div>

  <h3 style="margin:22px 0 12px;padding-top:16px;border-top:1px solid var(--rahmen)">Angaben für das Impressum</h3>
  <div class="bk-feldzeile">
    <div class="bk-feld"><label for="ust_id">Umsatzsteuer-ID</label>
      <input type="text" id="ust_id" name="ust_id" value="<?= Util::e($e('ust_id')) ?>" placeholder="DE000000000"></div>
    <div class="bk-feld"><label for="handelsregister">Handelsregister</label>
      <input type="text" id="handelsregister" name="handelsregister" value="<?= Util::e($e('handelsregister')) ?>"></div>
  </div>
  <div class="bk-feld"><label for="geschaeftsfuehrung">Geschäftsführung</label>
    <input type="text" id="geschaeftsfuehrung" name="geschaeftsfuehrung" value="<?= Util::e($e('geschaeftsfuehrung')) ?>"></div>

  <button class="bk-knopf bk-knopf-voll" type="submit">Speichern</button>
</form>
