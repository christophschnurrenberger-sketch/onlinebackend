<?php
/** Warenkorb – Anzeige und alle Änderungen daran. */

require __DIR__ . '/lib/bootstrap.php';

Theme::fassung();
$meldung = '';
$art     = 'info';

if (Util::isPost()) {
    $aktion = Util::post('aktion');
    try {
        switch ($aktion) {
            case 'hinzufuegen':
                $variantenId = Util::postInt('varianten_id');

                // Ohne JavaScript kommt die Auswahl als Optionswerte statt als
                // Varianten-ID – dann suchen wir die passende Variante selbst.
                if (isset($_POST['option1'])) {
                    $gesucht = Artikel::variante($variantenId);
                    if ($gesucht !== null) {
                        $treffer = DB::row(
                            'SELECT id FROM varianten WHERE artikel_id = ?
                               AND option1 = ? AND option2 = ? AND option3 = ?',
                            [
                                (int) $gesucht['artikel_id'],
                                Util::post('option1'),
                                Util::post('option2'),
                                Util::post('option3'),
                            ]
                        );
                        if ($treffer !== null) {
                            $variantenId = (int) $treffer['id'];
                        }
                    }
                }

                Warenkorb::hinzufuegen($variantenId, Util::postInt('menge', 1));
                Util::redirect(Config::url('warenkorb.php?ok=1'));
                // kein break nötig – redirect beendet

            case 'menge':
                Warenkorb::mengeSetzen(Util::postInt('varianten_id'), Util::postInt('menge'));
                Util::redirect(Config::url('warenkorb.php'));

            case 'entfernen':
                Warenkorb::entfernen(Util::postInt('varianten_id'));
                Util::redirect(Config::url('warenkorb.php'));

            case 'gutschein':
                Warenkorb::aendern(['rabattcode' => Util::post('code')]);
                Util::redirect(Config::url('warenkorb.php'));

            default:
                Util::redirect(Config::url('warenkorb.php'));
        }
    } catch (Throwable $e) {
        // Beim Hinzufügen zurück zur Artikelseite, damit der Kunde den Hinweis
        // im Zusammenhang sieht.
        $zurueck = Util::post('zurueck');
        if ($aktion === 'hinzufuegen' && $zurueck !== '') {
            Util::redirect(Config::url('artikel.php?h=' . rawurlencode($zurueck)
                . '&meldung=' . rawurlencode($e->getMessage())));
        }
        $meldung = $e->getMessage();
        $art     = 'fehler';
    }
}

$korb = Warenkorb::inhalt();
if ($meldung === '' && Util::get('ok') !== '') {
    $meldung = 'Der Artikel liegt jetzt im Warenkorb.';
    $art     = 'erfolg';
}

Theme::kopf(['titel' => 'Warenkorb', 'noindex' => true]);
?>
<div class="behaelter">
  <div class="seitenkopf"><h1>Warenkorb</h1></div>
  <?php Theme::meldung($meldung, $art); ?>
  <?php Theme::meldung((string) $korb['rabattfehler'], 'fehler'); ?>

  <?php if ($korb['positionen'] === []): ?>
    <div class="leer" style="padding-bottom:var(--a8)">
      <p>Dein Warenkorb ist leer.</p>
      <a class="knopf" href="<?= Util::e(Config::url('kategorie.php?h=alle')) ?>">Weiter einkaufen</a>
    </div>
  <?php else: ?>
    <div class="zweispaltig">
      <div>
        <?php foreach ($korb['positionen'] as $position): ?>
          <div class="korbzeile">
            <div class="bild">
              <?php if ((string) $position['bild_url'] !== ''): ?>
                <img src="<?= Util::e(Theme::url((string) $position['bild_url'])) ?>"
                     alt="<?= Util::e((string) $position['titel']) ?>">
              <?php endif; ?>
            </div>
            <div>
              <a href="<?= Util::e(Config::url('artikel.php?h=' . rawurlencode((string) $position['handle']))) ?>">
                <strong><?= Util::e((string) $position['titel']) ?></strong>
              </a>
              <?php if ((string) $position['variante'] !== '' && (string) $position['variante'] !== 'Standard'): ?>
                <div class="variante"><?= Util::e((string) $position['variante']) ?></div>
              <?php endif; ?>
              <?php if (!empty($position['zu_viel'])): ?>
                <div class="variante" style="color:var(--sale)">
                  Nur noch <?= (int) $position['verfuegbar'] ?> verfügbar
                </div>
              <?php endif; ?>

              <form method="post" style="margin-top:var(--a2);display:flex;gap:var(--a3);align-items:center">
                <input type="hidden" name="aktion" value="menge">
                <input type="hidden" name="varianten_id" value="<?= (int) $position['varianten_id'] ?>">
                <div class="menge">
                  <button type="submit" name="menge" value="<?= (int) $position['menge'] - 1 ?>"
                          aria-label="Menge verringern">−</button>
                  <input type="number" name="menge" value="<?= (int) $position['menge'] ?>"
                         min="0" max="99" aria-label="Menge">
                  <button type="submit" name="menge" value="<?= (int) $position['menge'] + 1 ?>"
                          aria-label="Menge erhöhen">+</button>
                </div>
                <noscript><button class="knopf knopf-klein knopf-leer" type="submit">Ändern</button></noscript>
              </form>

              <form method="post" style="margin-top:var(--a2)">
                <input type="hidden" name="aktion" value="entfernen">
                <input type="hidden" name="varianten_id" value="<?= (int) $position['varianten_id'] ?>">
                <button class="entfernen" type="submit">Entfernen</button>
              </form>
            </div>
            <div class="summe">
              <?= Util::e(Util::geld((int) $position['gesamt'])) ?>
              <?php if ((int) $position['rabatt'] > 0): ?>
                <div class="variante" style="text-align:right">
                  −<?= Util::e(Util::geld((int) $position['rabatt'])) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <aside class="zusammenfassung">
        <h2 style="font-size:1.125rem">Zusammenfassung</h2>
        <?php require __DIR__ . '/lib/summen.php'; ?>

        <form method="post" class="gutscheinform">
          <input type="hidden" name="aktion" value="gutschein">
          <label class="nur-vorlesen" for="code">Rabattcode</label>
          <input type="text" id="code" name="code" value="<?= Util::e((string) $korb['rabattcode']) ?>"
                 placeholder="Rabattcode">
          <button class="knopf knopf-klein knopf-leer" type="submit">Einlösen</button>
        </form>

        <a class="knopf knopf-breit" href="<?= Util::e(Config::url('kasse.php')) ?>">Zur Kasse</a>
        <p class="klein nebentext" style="margin-top:var(--a3)">
          Die Versandkosten richten sich nach der Lieferadresse und werden im nächsten Schritt berechnet.
        </p>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php Theme::fuss(); ?>
