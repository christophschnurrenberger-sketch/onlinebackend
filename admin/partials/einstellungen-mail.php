<?php /** Reiter „E-Mail“. */ ?>

<div class="ad-hinweis ad-hinweis-info">
  <strong>Zwei Wege für den Versand</strong>
  <code>mail()</code> ist auf den meisten Webhostern eingerichtet und braucht keine Zugangsdaten.
  Über SMTP kommen die Mails vom richtigen Absenderserver und landen seltener im Spam –
  dafür brauchst du einen Postfach-Zugang.
</div>

<form method="post">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="mail">
  <input type="hidden" name="reiter" value="mail">

  <div class="ad-feldzeile">
    <div class="ad-feld"><label for="mail_absender">Absenderadresse</label>
      <input type="email" id="mail_absender" name="mail_absender" value="<?= Util::e($e('mail_absender')) ?>">
      <div class="ad-tipp">Sollte zur Domain des Shops gehören – sonst blocken viele Empfänger.</div></div>
    <div class="ad-feld"><label for="mail_absender_name">Absendername</label>
      <input type="text" id="mail_absender_name" name="mail_absender_name" value="<?= Util::e($e('mail_absender_name')) ?>"></div>
  </div>

  <div class="ad-feld">
    <label for="mail_methode">Versandart</label>
    <select id="mail_methode" name="mail_methode"
            onchange="document.getElementById('smtpteil').hidden = this.value !== 'smtp'">
      <option value="mail" <?= $e('mail_methode') === 'mail' ? 'selected' : '' ?>>PHP mail() – ohne Zugangsdaten</option>
      <option value="smtp" <?= $e('mail_methode') === 'smtp' ? 'selected' : '' ?>>SMTP – eigener Postfach-Zugang</option>
    </select>
  </div>

  <div id="smtpteil" <?= $e('mail_methode') !== 'smtp' ? 'hidden' : '' ?>>
    <div class="ad-feldzeile">
      <div class="ad-feld"><label for="smtp_host">SMTP-Server</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?= Util::e($e('smtp_host')) ?>"
               placeholder="smtp.example.com"></div>
      <div class="ad-feld"><label for="smtp_port">Port</label>
        <input type="number" id="smtp_port" name="smtp_port" value="<?= Settings::int('smtp_port', 587) ?>"></div>
    </div>
    <div class="ad-feldzeile">
      <div class="ad-feld"><label for="smtp_user">Benutzername</label>
        <input type="text" id="smtp_user" name="smtp_user" value="<?= Util::e($e('smtp_user')) ?>" autocomplete="off"></div>
      <div class="ad-feld"><label for="smtp_pass">Passwort</label>
        <input type="password" id="smtp_pass" name="smtp_pass" autocomplete="off"
               placeholder="<?= Settings::hatGeheimnis('smtp_pass') ? '•••••• (gespeichert)' : '' ?>"></div>
    </div>
    <div class="ad-feld">
      <label for="smtp_sicherheit">Verschlüsselung</label>
      <select id="smtp_sicherheit" name="smtp_sicherheit">
        <option value="tls" <?= $e('smtp_sicherheit') === 'tls' ? 'selected' : '' ?>>STARTTLS (Port 587)</option>
        <option value="ssl" <?= $e('smtp_sicherheit') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
        <option value="keine" <?= $e('smtp_sicherheit') === 'keine' ? 'selected' : '' ?>>Keine (nicht empfohlen)</option>
      </select>
    </div>
  </div>

  <label class="ad-haken">
    <input type="checkbox" name="mail_bestellung_an_betreiber" value="1"
           <?= Settings::bool('mail_bestellung_an_betreiber') ? 'checked' : '' ?>>
    <span>Bei jeder Bestellung eine Benachrichtigung an den Shop schicken</span>
  </label>

  <button class="ad-knopf ad-knopf-voll" type="submit">Speichern</button>
</form>

<form method="post" class="ad-block" style="margin-top:20px">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="mail-test">
  <input type="hidden" name="reiter" value="mail">
  <h4 style="font-size:13px;margin:0 0 10px">Testmail senden</h4>
  <div class="ad-feldzeile" style="align-items:end">
    <div class="ad-feld" style="margin:0"><label for="test_an">An diese Adresse</label>
      <input type="email" id="test_an" name="test_an" required
             value="<?= Util::e($e('shop_email') ?: (string) $benutzer['email']) ?>"></div>
    <div><button class="ad-knopf" type="submit">Testmail senden</button></div>
  </div>
</form>
