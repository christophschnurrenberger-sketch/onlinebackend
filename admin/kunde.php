<?php
/** Kundendetail mit Bestellhistorie. */

$seitentitel = 'Kunde';
require __DIR__ . '/partials/header.php';

$id = Util::getInt('id');

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    try {
        if (Util::post('aktion') === 'loeschen') {
            Kunden::loeschen($id);
            Util::redirect('kunden.php?meldung=' . rawurlencode('Kunde gelöscht.'));
        }
        Kunden::speichern($id, [
            'email'      => Util::post('email'),
            'vorname'    => Util::post('vorname'),
            'nachname'   => Util::post('nachname'),
            'firma'      => Util::post('firma'),
            'telefon'    => Util::post('telefon'),
            'notiz'      => Util::postRaw('notiz'),
            'newsletter' => Util::postBool('newsletter'),
        ]);
        Util::redirect('kunde.php?id=' . $id . '&meldung=' . rawurlencode('Kunde gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$kunde = Kunden::holen($id);
if ($kunde === null) {
    Util::redirect('kunden.php?meldung=' . rawurlencode('Kunde nicht gefunden.') . '&art=fehler');
}
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <a class="bk-zurueck" href="kunden.php">← Alle Kunden</a>
    <h1><?= Util::e(trim((string) $kunde['vorname'] . ' ' . (string) $kunde['nachname']) ?: (string) $kunde['email']) ?></h1>
    <div class="bk-untertitel">Kunde seit <?= Util::e(Util::dt((string) $kunde['erstellt'], 'd.m.Y')) ?></div>
  </div>
  <div class="bk-aktionen">
    <button class="bk-knopf bk-knopf-voll" type="submit" form="kundeform">Speichern</button>
  </div>
</div>

<div class="bk-zwei">
  <div>
    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Bestellungen</h2></div>
      <div class="bk-karte-inhalt eng">
        <?php if ($kunde['bestellliste'] === []): ?>
          <div class="bk-leer"><h3>Noch keine Bestellungen</h3>
            <p>Dieser Kunde hat bisher nichts bestellt.</p></div>
        <?php else: ?>
          <div class="bk-tabelle-rahmen"><table>
            <thead><tr><th>Nr.</th><th>Datum</th><th>Zahlung</th><th>Versand</th><th class="bk-zahl-rechts">Summe</th></tr></thead>
            <tbody>
              <?php foreach ($kunde['bestellliste'] as $bestellung): ?>
                <tr class="bk-klick" style="cursor:pointer" onclick="location='bestellung.php?id=<?= (int) $bestellung['id'] ?>'">
                  <td><strong>#<?= (int) $bestellung['nummer'] ?></strong></td>
                  <td class="bk-neben"><?= Util::e(Util::dt((string) $bestellung['erstellt'], 'd.m.Y')) ?></td>
                  <td><?php require __DIR__ . '/partials/zahlmarke.php'; ?></td>
                  <td class="bk-neben"><?= Util::e(Bestellungen::VERSANDSTATUS[(string) $bestellung['versandstatus']] ?? '') ?></td>
                  <td class="bk-zahl-rechts"><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </section>

    <form id="kundeform" method="post" class="bk-karte">
      <?= Auth::csrfFeld() ?>
      <div class="bk-karte-kopf"><h2>Stammdaten</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld">
          <label for="email">E-Mail</label>
          <input type="email" id="email" name="email" required value="<?= Util::e((string) $kunde['email']) ?>">
        </div>
        <div class="bk-feldzeile">
          <div class="bk-feld"><label for="vorname">Vorname</label>
            <input type="text" id="vorname" name="vorname" value="<?= Util::e((string) $kunde['vorname']) ?>"></div>
          <div class="bk-feld"><label for="nachname">Nachname</label>
            <input type="text" id="nachname" name="nachname" value="<?= Util::e((string) $kunde['nachname']) ?>"></div>
        </div>
        <div class="bk-feldzeile">
          <div class="bk-feld"><label for="firma">Firma</label>
            <input type="text" id="firma" name="firma" value="<?= Util::e((string) $kunde['firma']) ?>"></div>
          <div class="bk-feld"><label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon" value="<?= Util::e((string) $kunde['telefon']) ?>"></div>
        </div>
        <div class="bk-feld">
          <label for="notiz">Interne Notiz</label>
          <textarea id="notiz" name="notiz" rows="3"><?= Util::e((string) $kunde['notiz']) ?></textarea>
        </div>
        <label class="bk-haken" style="margin:0">
          <input type="checkbox" name="newsletter" value="1" <?= (int) $kunde['newsletter'] === 1 ? 'checked' : '' ?>>
          <span>Newsletter erlaubt</span>
        </label>
      </div>
    </form>
  </div>

  <div>
    <div class="bk-drei" style="grid-template-columns:1fr 1fr;margin-bottom:16px">
      <div class="bk-zahl">
        <div class="bk-zahl-titel">Bestellungen</div>
        <div class="bk-zahl-wert"><?= (int) $kunde['bestellungen'] ?></div>
      </div>
      <div class="bk-zahl">
        <div class="bk-zahl-titel">Umsatz</div>
        <div class="bk-zahl-wert" style="font-size:19px"><?= Util::e(Util::geld((int) $kunde['umsatz'])) ?></div>
      </div>
    </div>

    <?php if ($kunde['adressen'] !== []): ?>
      <section class="bk-karte">
        <div class="bk-karte-kopf"><h2>Adressen</h2></div>
        <div class="bk-karte-inhalt">
          <?php foreach ($kunde['adressen'] as $adresse): ?>
            <div style="padding:8px 0;border-bottom:1px solid var(--rahmen)">
              <?php if ((int) $adresse['standard'] === 1): ?>
                <span class="bk-marke bk-marke-gruen"><i></i>Standard</span><br>
              <?php endif; ?>
              <span class="bk-neben"><?= nl2br(Util::e(Kunden::adresseText($adresse))) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (Auth::darf('pflegen')): ?>
      <section class="bk-karte"><div class="bk-karte-inhalt">
        <form method="post" data-frage="Kundenkonto löschen? Bestellungen bleiben erhalten, verlieren aber die Verknüpfung.">
          <?= Auth::csrfFeld() ?>
          <input type="hidden" name="aktion" value="loeschen">
          <button class="bk-knopf bk-knopf-rot" type="submit" style="width:100%">Kunde löschen</button>
        </form>
      </div></section>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
