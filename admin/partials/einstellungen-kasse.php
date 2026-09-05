<?php /** Reiter „Kasse“. */ ?>
<form method="post">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="kasse">
  <input type="hidden" name="reiter" value="kasse">

  <label class="ad-haken">
    <input type="checkbox" name="agb_pflicht" value="1" <?= Settings::bool('agb_pflicht') ? 'checked' : '' ?>>
    <span>Zustimmung zu AGB und Widerrufsbelehrung verlangen
      <span class="ad-tipp" style="display:block">Im deutschsprachigen Handel üblich und rechtlich sicherer.</span>
    </span>
  </label>
  <label class="ad-haken">
    <input type="checkbox" name="telefon_pflicht" value="1" <?= Settings::bool('telefon_pflicht') ? 'checked' : '' ?>>
    <span>Telefonnummer verlangen</span>
  </label>

  <div class="ad-feldzeile" style="margin-top:16px">
    <div class="ad-feld">
      <label for="bestellnummer_start">Erste Bestellnummer</label>
      <input type="number" id="bestellnummer_start" name="bestellnummer_start"
             value="<?= Settings::int('bestellnummer_start', 1000) ?>">
      <div class="ad-tipp">Gilt nur, solange es noch keine Bestellungen gibt.</div>
    </div>
    <div class="ad-feld">
      <label for="mindestbestellwert">Mindestbestellwert</label>
      <div class="ad-euro"><input type="text" id="mindestbestellwert" name="mindestbestellwert"
             value="<?= Util::e(Util::geldFeld(Settings::int('mindestbestellwert'))) ?>" inputmode="decimal"></div>
      <div class="ad-tipp">0 bedeutet: kein Mindestwert.</div>
    </div>
  </div>

  <div class="ad-feld">
    <label for="danke_text">Text auf der Dankeseite</label>
    <textarea id="danke_text" name="danke_text" rows="3"><?= Util::e($e('danke_text')) ?></textarea>
  </div>

  <button class="ad-knopf ad-knopf-voll" type="submit">Speichern</button>
</form>
