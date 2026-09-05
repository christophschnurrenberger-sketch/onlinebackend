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
        echo '<div class="ad-hinweis ad-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$kunde = Kunden::holen($id);
if ($kunde === null) {
    Util::redirect('kunden.php?meldung=' . rawurlencode('Kunde nicht gefunden.') . '&art=fehler');
}
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <a class="ad-zurueck" href="kunden.php">← Alle Kunden</a>
    <h1><?= Util::e(trim((string) $kunde['vorname'] . ' ' . (string) $kunde['nachname']) ?: (string) $kunde['email']) ?></h1>
    <div class="ad-untertitel">Kunde seit <?= Util::e(Util::dt((string) $kunde['erstellt'], 'd.m.Y')) ?></div>
  </div>
  <div class="ad-aktionen">
    <button class="ad-knopf ad-knopf-voll" type="submit" form="kundeform">Speichern</button>
  </div>
</div>

<div class="ad-zwei">
  <div>
    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Bestellungen</h2></div>
      <div class="ad-karte-inhalt eng">
        <?php if ($kunde['bestellliste'] === []): ?>
          <div class="ad-leer"><h3>Noch keine Bestellungen</h3>
            <p>Dieser Kunde hat bisher nichts bestellt.</p></div>
        <?php else: ?>
          <div class="ad-tabelle-rahmen"><table>
            <thead><tr><th>Nr.</th><th>Datum</th><th>Zahlung</th><th>Versand</th><th class="ad-zahl-rechts">Summe</th></tr></thead>
            <tbody>
              <?php foreach ($kunde['bestellliste'] as $bestellung): ?>
                <tr class="ad-klick" style="cursor:pointer" onclick="location='bestellung.php?id=<?= (int) $bestellung['id'] ?>'">
                  <td><strong>#<?= (int) $bestellung['nummer'] ?></strong></td>
                  <td class="ad-neben"><?= Util::e(Util::dt((string) $bestellung['erstellt'], 'd.m.Y')) ?></td>
                  <td><?php require __DIR__ . '/partials/zahlmarke.php'; ?></td>
                  <td class="ad-neben"><?= Util::e(Bestellungen::VERSANDSTATUS[(string) $bestellung['versandstatus']] ?? '') ?></td>
                  <td class="ad-zahl-rechts"><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </section>

    <form id="kundeform" method="post" class="ad-karte">
      <?= Auth::csrfFeld() ?>
      <div class="ad-karte-kopf"><h2>Stammdaten</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feld">
          <label for="email">E-Mail</label>
          <input type="email" id="email" name="email" required value="<?= Util::e((string) $kunde['email']) ?>">
        </div>
        <div class="ad-feldzeile">
          <div class="ad-feld"><label for="vorname">Vorname</label>
            <input type="text" id="vorname" name="vorname" value="<?= Util::e((string) $kunde['vorname']) ?>"></div>
          <div class="ad-feld"><label for="nachname">Nachname</label>
            <input type="text" id="nachname" name="nachname" value="<?= Util::e((string) $kunde['nachname']) ?>"></div>
        </div>
        <div class="ad-feldzeile">
          <div class="ad-feld"><label for="firma">Firma</label>
            <input type="text" id="firma" name="firma" value="<?= Util::e((string) $kunde['firma']) ?>"></div>
          <div class="ad-feld"><label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon" value="<?= Util::e((string) $kunde['telefon']) ?>"></div>
        </div>
        <div class="ad-feld">
          <label for="notiz">Interne Notiz</label>
          <textarea id="notiz" name="notiz" rows="3"><?= Util::e((string) $kunde['notiz']) ?></textarea>
        </div>
        <label class="ad-haken" style="margin:0">
          <input type="checkbox" name="newsletter" value="1" <?= (int) $kunde['newsletter'] === 1 ? 'checked' : '' ?>>
          <span>Newsletter erlaubt</span>
        </label>
      </div>
    </form>
  </div>

  <div>
    <div class="ad-drei" style="grid-template-columns:1fr 1fr;margin-bottom:16px">
      <div class="ad-zahl">
        <div class="ad-zahl-titel">Bestellungen</div>
        <div class="ad-zahl-wert"><?= (int) $kunde['bestellungen'] ?></div>
      </div>
      <div class="ad-zahl">
        <div class="ad-zahl-titel">Umsatz</div>
        <div class="ad-zahl-wert" style="font-size:19px"><?= Util::e(Util::geld((int) $kunde['umsatz'])) ?></div>
      </div>
    </div>

    <?php if ($kunde['adressen'] !== []): ?>
      <section class="ad-karte">
        <div class="ad-karte-kopf"><h2>Adressen</h2></div>
        <div class="ad-karte-inhalt">
          <?php foreach ($kunde['adressen'] as $adresse): ?>
            <div style="padding:8px 0;border-bottom:1px solid var(--rahmen)">
              <?php if ((int) $adresse['standard'] === 1): ?>
                <span class="ad-marke ad-marke-gruen"><i></i>Standard</span><br>
              <?php endif; ?>
              <span class="ad-neben"><?= nl2br(Util::e(Kunden::adresseText($adresse))) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (Auth::darf('pflegen')): ?>
      <section class="ad-karte"><div class="ad-karte-inhalt">
        <form method="post" data-frage="Kundenkonto löschen? Bestellungen bleiben erhalten, verlieren aber die Verknüpfung.">
          <?= Auth::csrfFeld() ?>
          <input type="hidden" name="aktion" value="loeschen">
          <button class="ad-knopf ad-knopf-rot" type="submit" style="width:100%">Kunde löschen</button>
        </form>
      </div></section>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
