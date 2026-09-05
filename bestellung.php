<?php
/**
 * Bestellstatus für Kunden.
 *
 * Zugang über den langen Zufallstoken aus der Bestätigungsmail – kein Konto
 * nötig, aber auch nicht erratbar.
 */

require __DIR__ . '/lib/bootstrap.php';

Theme::fassung();

$bestellung = Bestellungen::nachToken(Util::get('t'));
if ($bestellung === null) {
    http_response_code(404);
    Theme::kopf(['titel' => 'Nicht gefunden', 'noindex' => true]);
    echo '<div class="behaelter"><div class="leer"><h1>Bestellung nicht gefunden</h1>'
       . '<p>Bitte den Link aus der Bestätigungsmail verwenden.</p>'
       . '<a class="knopf" href="' . Util::e(Config::url()) . '">Zur Startseite</a></div></div>';
    Theme::fuss();
    exit;
}

$neu     = Util::get('neu') !== '';
$adresse = $bestellung['lieferadresse_daten'];

$pillenklasse = match (true) {
    (string) $bestellung['status'] === 'storniert' => 'pille-storniert',
    (string) $bestellung['zahlstatus'] === 'bezahlt' => 'pille-bezahlt',
    default => 'pille-offen',
};

Theme::kopf(['titel' => 'Bestellung ' . $bestellung['nummer'], 'noindex' => true]);
?>
<div class="behaelter">
  <div class="bestellseite">
    <?php if ($neu): ?>
      <div class="meldung meldung-erfolg">
        <strong>Vielen Dank für deine Bestellung!</strong>
        <p style="margin:var(--a2) 0 0"><?= Util::e(Theme::e('danke_text')) ?></p>
      </div>
    <?php endif; ?>

    <h1>Bestellung <?= (int) $bestellung['nummer'] ?></h1>
    <p class="nebentext">Aufgegeben am <?= Util::e(Util::dt((string) $bestellung['erstellt'])) ?></p>

    <div class="marken">
      <span class="pille <?= $pillenklasse ?>">
        <?= Util::e(Bestellungen::ZAHLSTATUS[(string) $bestellung['zahlstatus']] ?? (string) $bestellung['zahlstatus']) ?>
      </span>
      <span class="pille">
        <?= Util::e(Bestellungen::VERSANDSTATUS[(string) $bestellung['versandstatus']] ?? '') ?>
      </span>
      <?php if ((string) $bestellung['status'] === 'storniert'): ?>
        <span class="pille pille-storniert">Storniert</span>
      <?php endif; ?>
    </div>

    <?php if ((string) $bestellung['zahlstatus'] === 'offen' && (string) $bestellung['status'] !== 'storniert'): ?>
      <div class="meldung meldung-info">
        <strong><?= Util::e(Zahlung::name((string) $bestellung['zahlart'])) ?></strong>
        <p style="margin:var(--a2) 0 0"><?= Util::e(Zahlung::hinweis((string) $bestellung['zahlart'])) ?></p>
        <?php if ((string) $bestellung['zahlart'] === 'vorkasse' && Theme::e('bankverbindung') !== ''): ?>
          <p style="margin:var(--a3) 0 0">
            Bitte überweise <strong><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></strong>
            unter Angabe der Bestellnummer <strong><?= (int) $bestellung['nummer'] ?></strong>:
          </p>
          <p style="margin:var(--a2) 0 0;white-space:pre-line"><?= Util::e(Theme::e('bankverbindung')) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($bestellung['sendungen'] !== []): ?>
      <div class="meldung meldung-info">
        <strong>Sendungsverfolgung</strong>
        <?php foreach ($bestellung['sendungen'] as $sendung): ?>
          <p style="margin:var(--a2) 0 0">
            <?= Util::e(trim((string) $sendung['dienstleister'] . ' ' . (string) $sendung['sendungsnummer'])) ?>
            <?php if ((string) $sendung['sendung_url'] !== ''): ?>
              – <a href="<?= Util::e((string) $sendung['sendung_url']) ?>" rel="noopener">Sendung verfolgen</a>
            <?php endif; ?>
          </p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="datenpaar">
      <div>
        <h3>Lieferadresse</h3>
        <p><?= nl2br(Util::e(Kunden::adresseText($adresse))) ?></p>
      </div>
      <div>
        <h3>Kontakt</h3>
        <p><?= Util::e((string) $bestellung['email']) ?>
          <?php if ((string) $bestellung['telefon'] !== ''): ?><br><?= Util::e((string) $bestellung['telefon']) ?><?php endif; ?>
        </p>
        <h3 style="margin-top:var(--a4)">Versandart</h3>
        <p><?= Util::e((string) $bestellung['versandart'] ?: '—') ?></p>
      </div>
    </div>

    <h3 style="margin-top:var(--a6)">Positionen</h3>
    <?php foreach ($bestellung['zeilen'] as $zeile): ?>
      <div class="korbzeile">
        <div class="bild">
          <?php if ((string) $zeile['bild_url'] !== ''): ?>
            <img src="<?= Util::e(Theme::url((string) $zeile['bild_url'])) ?>"
                 alt="<?= Util::e((string) $zeile['titel']) ?>">
          <?php endif; ?>
        </div>
        <div>
          <strong><?= Util::e((string) $zeile['titel']) ?></strong>
          <?php if ((string) $zeile['variante'] !== '' && (string) $zeile['variante'] !== 'Standard'): ?>
            <div class="variante"><?= Util::e((string) $zeile['variante']) ?></div>
          <?php endif; ?>
          <div class="variante">Menge: <?= (int) $zeile['menge'] ?><?php
            if ((string) $zeile['artikelnummer'] !== '') {
                echo ' · Art.-Nr. ' . Util::e((string) $zeile['artikelnummer']);
            }
          ?></div>
        </div>
        <div class="summe"><?= Util::e(Util::geld((int) $zeile['gesamt'])) ?></div>
      </div>
    <?php endforeach; ?>

    <div class="zusammenfassung" style="margin-top:var(--a5)">
      <div class="summenzeile"><span>Zwischensumme</span>
        <span><?= Util::e(Util::geld((int) $bestellung['zwischensumme'])) ?></span></div>
      <?php if ((int) $bestellung['rabatt'] > 0): ?>
        <div class="summenzeile"><span>Rabatt <?= Util::e((string) $bestellung['rabattcode']) ?></span>
          <span>−<?= Util::e(Util::geld((int) $bestellung['rabatt'])) ?></span></div>
      <?php endif; ?>
      <div class="summenzeile"><span>Versand</span>
        <span><?= (int) $bestellung['versandkosten'] === 0 ? 'kostenlos' : Util::e(Util::geld((int) $bestellung['versandkosten'])) ?></span></div>
      <div class="summenzeile gesamt"><span>Gesamt</span>
        <span><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></span></div>
      <div class="summenzeile"><span class="nebentext">enthaltene MwSt.</span>
        <span class="nebentext"><?= Util::e(Util::geld((int) $bestellung['steuer'])) ?></span></div>
      <?php if ((int) $bestellung['erstattet'] > 0): ?>
        <div class="summenzeile"><span>Erstattet</span>
          <span>−<?= Util::e(Util::geld((int) $bestellung['erstattet'])) ?></span></div>
      <?php endif; ?>
    </div>

    <p style="margin-top:var(--a6)">
      <a class="knopf knopf-leer" href="<?= Util::e(Config::url()) ?>">Weiter einkaufen</a>
    </p>
  </div>
</div>
<?php Theme::fuss(); ?>
