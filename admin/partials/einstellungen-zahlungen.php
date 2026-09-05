<?php /** Reiter „Zahlungen“. */ ?>

<div class="ad-hinweis ad-hinweis-info">
  <strong>Zugangsdaten werden verschlüsselt gespeichert</strong>
  Sie werden hier nie wieder angezeigt. Ein leeres Feld lässt den gespeicherten Wert unverändert –
  zum Ändern einfach den neuen Wert eintragen.
</div>

<form method="post">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="zahlungen">
  <input type="hidden" name="reiter" value="zahlungen">

  <?php $aktive = Settings::liste('zahlarten'); ?>
  <?php foreach (Zahlung::ARTEN as $art => $vorgabeName): ?>
    <?php $bereit = Zahlung::einsatzbereit($art); ?>
    <div class="ad-block">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px">
        <label class="ad-haken" style="margin:0;font-weight:600">
          <input type="checkbox" name="an_<?= $art ?>" value="1"
                 <?= in_array($art, $aktive, true) ? 'checked' : '' ?> <?= $bereit ? '' : 'disabled' ?>>
          <span><?= Util::e($vorgabeName) ?></span>
        </label>
        <?php if ($bereit): ?>
          <span class="ad-marke ad-marke-gruen"><i></i>Einsatzbereit</span>
        <?php else: ?>
          <span class="ad-marke ad-marke-gelb"><i></i>Zugangsdaten fehlen</span>
        <?php endif; ?>
      </div>

      <div class="ad-feld">
        <label for="name_<?= $art ?>">Beschriftung an der Kasse</label>
        <input type="text" id="name_<?= $art ?>" name="name_<?= $art ?>"
               value="<?= Util::e(Settings::get('zahlart_' . $art . '_name') ?: $vorgabeName) ?>">
      </div>

      <?php if (in_array($art, ['rechnung', 'vorkasse', 'nachnahme', 'test'], true)): ?>
        <div class="ad-feld" style="margin:0">
          <label for="text_<?= $art ?>">Hinweis für Kunden</label>
          <textarea id="text_<?= $art ?>" name="text_<?= $art ?>" rows="2"><?= Util::e(Settings::get('zahlart_' . $art . '_text')) ?></textarea>
        </div>
      <?php endif; ?>

      <?php if ($art === 'stripe'): ?>
        <div class="ad-feldzeile">
          <div class="ad-feld">
            <label for="stripe_secret">Geheimer Schlüssel</label>
            <input type="password" id="stripe_secret" name="stripe_secret" autocomplete="off"
                   placeholder="<?= Settings::hatGeheimnis('stripe_secret') ? '•••••• (gespeichert)' : 'sk_live_…' ?>">
          </div>
          <div class="ad-feld">
            <label for="stripe_public">Öffentlicher Schlüssel</label>
            <input type="text" id="stripe_public" name="stripe_public" value="<?= Util::e($e('stripe_public')) ?>"
                   placeholder="pk_live_…">
          </div>
        </div>
        <div class="ad-feld" style="margin:0">
          <label for="stripe_webhook">Webhook-Geheimnis</label>
          <input type="password" id="stripe_webhook" name="stripe_webhook" autocomplete="off"
                 placeholder="<?= Settings::hatGeheimnis('stripe_webhook') ? '•••••• (gespeichert)' : 'whsec_…' ?>">
          <div class="ad-tipp">Ohne dieses Geheimnis werden eingehende Zahlungsmeldungen abgelehnt –
            das ist Absicht.</div>
        </div>
      <?php endif; ?>

      <?php if ($art === 'paypal'): ?>
        <div class="ad-feldzeile">
          <div class="ad-feld">
            <label for="paypal_id">Client-ID</label>
            <input type="text" id="paypal_id" name="paypal_id" value="<?= Util::e($e('paypal_id')) ?>">
          </div>
          <div class="ad-feld">
            <label for="paypal_secret">Secret</label>
            <input type="password" id="paypal_secret" name="paypal_secret" autocomplete="off"
                   placeholder="<?= Settings::hatGeheimnis('paypal_secret') ? '•••••• (gespeichert)' : '' ?>">
          </div>
        </div>
        <div class="ad-feldzeile" style="margin:0">
          <div class="ad-feld" style="margin:0">
            <label for="paypal_modus">Modus</label>
            <select id="paypal_modus" name="paypal_modus">
              <option value="sandbox" <?= $e('paypal_modus') === 'sandbox' ? 'selected' : '' ?>>Sandbox (zum Testen)</option>
              <option value="live" <?= $e('paypal_modus') === 'live' ? 'selected' : '' ?>>Live (echtes Geld)</option>
            </select>
          </div>
          <div class="ad-feld" style="margin:0">
            <label for="paypal_webhook">Webhook-ID</label>
            <input type="password" id="paypal_webhook" name="paypal_webhook" autocomplete="off"
                   placeholder="<?= Settings::hatGeheimnis('paypal_webhook') ? '•••••• (gespeichert)' : '' ?>">
          </div>
        </div>
      <?php endif; ?>

      <?php if ($art === 'vorkasse'): ?>
        <div class="ad-feld" style="margin:12px 0 0">
          <label for="bankverbindung">Bankverbindung</label>
          <textarea id="bankverbindung" name="bankverbindung" rows="3"
                    placeholder="Mein Shop GmbH&#10;IBAN: DE00 0000 0000 0000 0000 00&#10;BIC: XXXXDEXXXXX"><?= Util::e($e('bankverbindung')) ?></textarea>
          <div class="ad-tipp">Erscheint in der Bestellbestätigung und auf der Statusseite.</div>
        </div>
      <?php endif; ?>

      <?php if ($art === 'test'): ?>
        <div class="ad-hinweis ad-hinweis-warnung" style="margin:12px 0 0">
          Die Testzahlung schließt Bestellungen sofort ab, ohne dass Geld fließt.
          <strong>Vor dem Livegang abschalten.</strong>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <button class="ad-knopf ad-knopf-voll" type="submit">Speichern</button>
</form>

<h3 style="margin:26px 0 10px;padding-top:18px;border-top:1px solid var(--rahmen)">Webhook-Adressen</h3>
<p class="ad-tipp">Diese Adressen beim jeweiligen Anbieter eintragen, damit Zahlungen auch dann ankommen,
  wenn der Kunde den Browser vorzeitig schließt.</p>
<table>
  <tbody>
    <tr><td style="width:100px">Stripe</td>
        <td><div class="ad-code-kasten"><?= Util::e(Config::url('webhook.php?anbieter=stripe')) ?></div></td></tr>
    <tr><td>PayPal</td>
        <td><div class="ad-code-kasten"><?= Util::e(Config::url('webhook.php?anbieter=paypal')) ?></div></td></tr>
  </tbody>
</table>
