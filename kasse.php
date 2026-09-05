<?php
/** Kasse – Adresse, Versandart, Zahlart, Bestellabschluss. */

require __DIR__ . '/lib/bootstrap.php';

Theme::fassung();

$korb = Warenkorb::inhalt();
if ($korb['positionen'] === []) {
    Util::redirect(Config::url('warenkorb.php'));
}

$fehler = '';
$werte  = [
    'email' => (string) $korb['email'],
    'telefon' => '', 'vorname' => '', 'nachname' => '', 'firma' => '',
    'strasse' => '', 'zusatz' => '', 'plz' => '', 'ort' => '',
    'land' => (string) $korb['land'], 'notiz' => '',
];

/* Land oder Versandart wechseln – ohne JavaScript als eigener Absendeschritt. */
if (Util::isPost() && Util::post('aktion') === 'aktualisieren') {
    Warenkorb::aendern([
        'land'          => Util::post('land', 'DE'),
        'versandart_id' => Util::postInt('versandart_id'),
    ]);
    $korb = Warenkorb::inhalt();
    foreach (array_keys($werte) as $feld) {
        $werte[$feld] = Util::post($feld, $werte[$feld]);
    }
} elseif (Util::isPost() && Util::post('aktion') === 'bestellen') {
    foreach (array_keys($werte) as $feld) {
        $werte[$feld] = Util::post($feld, '');
    }
    try {
        $ergebnis = Kasse::abschliessen([
            'email'         => $werte['email'],
            'telefon'       => $werte['telefon'],
            'zahlart'       => Util::post('zahlart'),
            'versandart_id' => Util::postInt('versandart_id'),
            'agb'           => Util::postBool('agb'),
            'newsletter'    => Util::postBool('newsletter'),
            'notiz'         => $werte['notiz'],
            'lieferadresse' => [
                'vorname'  => $werte['vorname'],
                'nachname' => $werte['nachname'],
                'firma'    => $werte['firma'],
                'strasse'  => $werte['strasse'],
                'zusatz'   => $werte['zusatz'],
                'plz'      => $werte['plz'],
                'ort'      => $werte['ort'],
                'land'     => $werte['land'],
                'telefon'  => $werte['telefon'],
            ],
        ]);

        if ($ergebnis['aktion'] === 'weiterleiten' && $ergebnis['url'] !== '') {
            Util::redirect($ergebnis['url']);
        }
        Util::redirect(Config::url('bestellung.php?t=' . rawurlencode((string) $ergebnis['bestellung']['token']) . '&neu=1'));
    } catch (Throwable $e) {
        $fehler = $e->getMessage();
        // Warenkorb neu laden – Land oder Versandart können sich geändert haben.
        $korb = Warenkorb::inhalt();
    }
}

$zahlarten = Zahlung::verfuegbare();
$laender   = Versand::laender();
$auswahlLaender = $laender === [] ? array_keys(Versand::LAENDERNAMEN) : $laender;

Theme::kopf(['titel' => 'Kasse', 'noindex' => true]);
?>
<div class="behaelter">
  <div class="seitenkopf"><h1>Kasse</h1></div>
  <?php Theme::meldung($fehler, 'fehler'); ?>

  <form method="post" class="zweispaltig">
    <input type="hidden" name="aktion" value="bestellen">
    <div>
      <fieldset class="feldblock">
        <legend>Kontakt</legend>
        <div class="feld">
          <label for="email">E-Mail-Adresse</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?= Util::e($werte['email']) ?>">
          <div class="tipp">Hierhin schicken wir die Bestellbestätigung.</div>
        </div>
        <div class="feld">
          <label for="telefon">Telefon <?= Settings::bool('telefon_pflicht') ? '' : '(optional)' ?></label>
          <input type="tel" id="telefon" name="telefon" autocomplete="tel"
                 <?= Settings::bool('telefon_pflicht') ? 'required' : '' ?>
                 value="<?= Util::e($werte['telefon']) ?>">
        </div>
      </fieldset>

      <fieldset class="feldblock">
        <legend>Lieferadresse</legend>
        <div class="feldzeile">
          <div class="feld">
            <label for="vorname">Vorname</label>
            <input type="text" id="vorname" name="vorname" required autocomplete="given-name"
                   value="<?= Util::e($werte['vorname']) ?>">
          </div>
          <div class="feld">
            <label for="nachname">Nachname</label>
            <input type="text" id="nachname" name="nachname" required autocomplete="family-name"
                   value="<?= Util::e($werte['nachname']) ?>">
          </div>
        </div>
        <div class="feld">
          <label for="firma">Firma (optional)</label>
          <input type="text" id="firma" name="firma" autocomplete="organization"
                 value="<?= Util::e($werte['firma']) ?>">
        </div>
        <div class="feld">
          <label for="strasse">Straße und Hausnummer</label>
          <input type="text" id="strasse" name="strasse" required autocomplete="address-line1"
                 value="<?= Util::e($werte['strasse']) ?>">
        </div>
        <div class="feld">
          <label for="zusatz">Adresszusatz (optional)</label>
          <input type="text" id="zusatz" name="zusatz" autocomplete="address-line2"
                 value="<?= Util::e($werte['zusatz']) ?>">
        </div>
        <div class="feldzeile">
          <div class="feld">
            <label for="plz">PLZ</label>
            <input type="text" id="plz" name="plz" required autocomplete="postal-code"
                   value="<?= Util::e($werte['plz']) ?>">
          </div>
          <div class="feld">
            <label for="ort">Ort</label>
            <input type="text" id="ort" name="ort" required autocomplete="address-level2"
                   value="<?= Util::e($werte['ort']) ?>">
          </div>
        </div>
        <div class="feld">
          <label for="land">Land</label>
          <select id="land" name="land" onchange="this.form.aktion.value='aktualisieren';this.form.submit()">
            <?php foreach ($auswahlLaender as $code): ?>
              <option value="<?= Util::e($code) ?>" <?= $code === $werte['land'] ? 'selected' : '' ?>>
                <?= Util::e(Versand::landName($code)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <noscript>
            <button class="knopf knopf-klein knopf-leer" type="submit" name="aktion" value="aktualisieren"
                    style="margin-top:var(--a2)">Versandkosten neu berechnen</button>
          </noscript>
        </div>
      </fieldset>

      <?php if ($korb['versandnoetig']): ?>
      <fieldset class="feldblock">
        <legend>Versand</legend>
        <?php if ($korb['versandarten'] === []): ?>
          <div class="meldung meldung-fehler">In dieses Land liefern wir derzeit nicht.</div>
        <?php else: ?>
          <?php foreach ($korb['versandarten'] as $versandart): ?>
            <label class="auswahl">
              <input type="radio" name="versandart_id" value="<?= (int) $versandart['id'] ?>"
                     <?= (int) $versandart['id'] === (int) ($korb['versandart']['id'] ?? 0) ? 'checked' : '' ?>
                     onchange="this.form.aktion.value='aktualisieren';this.form.submit()">
              <span>
                <span class="name"><?= Util::e((string) $versandart['name']) ?></span>
                <?php if ((string) $versandart['lieferzeit'] !== ''): ?>
                  <span class="tipp"><?= Util::e((string) $versandart['lieferzeit']) ?></span>
                <?php endif; ?>
                <?php if (!empty($versandart['frei'])): ?>
                  <span class="tipp">Versandkostenfrei erreicht</span>
                <?php endif; ?>
              </span>
              <span class="preis-rechts">
                <?= (int) $versandart['endpreis'] === 0 ? 'kostenlos' : Util::e(Util::geld((int) $versandart['endpreis'])) ?>
              </span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </fieldset>
      <?php endif; ?>

      <fieldset class="feldblock">
        <legend>Zahlung</legend>
        <?php if ($zahlarten === []): ?>
          <div class="meldung meldung-fehler">
            Es ist keine Zahlart eingerichtet. Bitte im Backend unter
            Einstellungen → Zahlungen mindestens eine aktivieren.
          </div>
        <?php else: ?>
          <?php foreach ($zahlarten as $nr => $zahlart): ?>
            <label class="auswahl">
              <input type="radio" name="zahlart" value="<?= Util::e($zahlart['id']) ?>" required
                     <?= $nr === 0 ? 'checked' : '' ?>>
              <span>
                <span class="name"><?= Util::e($zahlart['name']) ?></span>
                <?php if ($zahlart['hinweis'] !== ''): ?>
                  <span class="tipp"><?= Util::e($zahlart['hinweis']) ?></span>
                <?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </fieldset>

      <div class="feld">
        <label for="notiz">Anmerkung zur Bestellung (optional)</label>
        <textarea id="notiz" name="notiz" rows="3"><?= Util::e($werte['notiz']) ?></textarea>
      </div>

      <?php if (Settings::bool('agb_pflicht')): ?>
        <label class="hakenzeile">
          <input type="checkbox" name="agb" value="1" required>
          <span>Ich habe die
            <a href="<?= Util::e(Config::url('seite.php?h=' . rawurlencode(Theme::e('seite_agb')))) ?>" target="_blank">AGB</a>
            und die
            <a href="<?= Util::e(Config::url('seite.php?h=' . rawurlencode(Theme::e('seite_widerruf')))) ?>" target="_blank">Widerrufsbelehrung</a>
            gelesen und akzeptiere sie.</span>
        </label>
      <?php endif; ?>
      <label class="hakenzeile">
        <input type="checkbox" name="newsletter" value="1">
        <span>Ich möchte den Newsletter erhalten (jederzeit abbestellbar).</span>
      </label>

      <button class="knopf knopf-breit" type="submit" <?= $zahlarten === [] ? 'disabled' : '' ?>>
        Zahlungspflichtig bestellen
      </button>
    </div>

    <aside class="zusammenfassung">
      <h2 style="font-size:1.125rem">Deine Bestellung</h2>
      <?php foreach ($korb['positionen'] as $position): ?>
        <div class="summenzeile">
          <span><?= (int) $position['menge'] ?>× <?= Util::e((string) $position['titel']) ?>
            <?php if ((string) $position['variante'] !== '' && (string) $position['variante'] !== 'Standard'): ?>
              <span class="nebentext">(<?= Util::e((string) $position['variante']) ?>)</span>
            <?php endif; ?>
          </span>
          <span><?= Util::e(Util::geld((int) $position['gesamt'])) ?></span>
        </div>
      <?php endforeach; ?>
      <hr style="border:0;border-top:1px solid var(--rahmen);margin:var(--a3) 0">
      <?php require __DIR__ . '/lib/summen.php'; ?>
      <p class="klein" style="margin-top:var(--a3)">
        <a href="<?= Util::e(Config::url('warenkorb.php')) ?>">Warenkorb bearbeiten</a>
      </p>
    </aside>
  </form>
</div>
<?php Theme::fuss(); ?>
