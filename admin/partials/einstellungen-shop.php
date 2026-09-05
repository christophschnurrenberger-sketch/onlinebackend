<?php /** Reiter „Shop-Daten“. */ ?>
<form method="post">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="shop">
  <input type="hidden" name="reiter" value="shop">

  <div class="ad-feldzeile">
    <div class="ad-feld"><label for="shop_name">Shopname</label>
      <input type="text" id="shop_name" name="shop_name" required value="<?= Util::e($e('shop_name')) ?>"></div>
    <div class="ad-feld"><label for="shop_slogan">Slogan</label>
      <input type="text" id="shop_slogan" name="shop_slogan" value="<?= Util::e($e('shop_slogan')) ?>"></div>
  </div>
  <div class="ad-feld">
    <label for="shop_beschreibung">Kurzbeschreibung</label>
    <textarea id="shop_beschreibung" name="shop_beschreibung" rows="2"><?= Util::e($e('shop_beschreibung')) ?></textarea>
    <div class="ad-tipp">Erscheint als Standardbeschreibung bei Suchmaschinen und beim Teilen.</div>
  </div>
  <div class="ad-feldzeile">
    <div class="ad-feld"><label for="shop_email">E-Mail</label>
      <input type="email" id="shop_email" name="shop_email" value="<?= Util::e($e('shop_email')) ?>"></div>
    <div class="ad-feld"><label for="shop_telefon">Telefon</label>
      <input type="text" id="shop_telefon" name="shop_telefon" value="<?= Util::e($e('shop_telefon')) ?>"></div>
  </div>
  <div class="ad-feldzeile">
    <div class="ad-feld">
      <label for="waehrung">Währung</label>
      <select id="waehrung" name="waehrung">
        <?php foreach (['EUR' => 'Euro (€)', 'CHF' => 'Schweizer Franken', 'USD' => 'US-Dollar', 'GBP' => 'Britisches Pfund'] as $wert => $label): ?>
          <option value="<?= $wert ?>" <?= $e('waehrung') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ad-feld">
      <label for="land">Standardland</label>
      <select id="land" name="land">
        <?php foreach (Versand::LAENDERNAMEN as $code => $name): ?>
          <option value="<?= $code ?>" <?= $e('land') === $code ? 'selected' : '' ?>><?= Util::e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="ad-feldzeile">
    <div class="ad-feld"><label for="logo_url">Logo (Adresse)</label>
      <input type="text" id="logo_url" name="logo_url" value="<?= Util::e($e('logo_url')) ?>" placeholder="uploads/logo.png"></div>
    <div class="ad-feld"><label for="favicon_url">Favicon (Adresse)</label>
      <input type="text" id="favicon_url" name="favicon_url" value="<?= Util::e($e('favicon_url')) ?>"></div>
  </div>

  <h3 style="margin:22px 0 12px;padding-top:16px;border-top:1px solid var(--rahmen)">Anschrift</h3>
  <div class="ad-feld"><label for="firma">Firma</label>
    <input type="text" id="firma" name="firma" value="<?= Util::e($e('firma')) ?>"></div>
  <div class="ad-feld"><label for="strasse">Straße und Hausnummer</label>
    <input type="text" id="strasse" name="strasse" value="<?= Util::e($e('strasse')) ?>"></div>
  <div class="ad-feldzeile">
    <div class="ad-feld"><label for="plz">PLZ</label>
      <input type="text" id="plz" name="plz" value="<?= Util::e($e('plz')) ?>"></div>
    <div class="ad-feld"><label for="ort">Ort</label>
      <input type="text" id="ort" name="ort" value="<?= Util::e($e('ort')) ?>"></div>
  </div>

  <h3 style="margin:22px 0 12px;padding-top:16px;border-top:1px solid var(--rahmen)">Soziale Netzwerke</h3>
  <div class="ad-feldzeile">
    <div class="ad-feld"><label for="social_instagram">Instagram</label>
      <input type="url" id="social_instagram" name="social_instagram" value="<?= Util::e($e('social_instagram')) ?>"></div>
    <div class="ad-feld"><label for="social_facebook">Facebook</label>
      <input type="url" id="social_facebook" name="social_facebook" value="<?= Util::e($e('social_facebook')) ?>"></div>
  </div>

  <button class="ad-knopf ad-knopf-voll" type="submit">Speichern</button>
</form>
