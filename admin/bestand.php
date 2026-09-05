<?php
/**
 * Bestandsübersicht.
 *
 * Mengen sind direkt in der Tabelle änderbar – Lagerkorrekturen macht niemand
 * gern über zehn Einzelseiten. Jede Änderung erzeugt eine Bestandsbewegung,
 * die im Verlauf nachvollziehbar bleibt.
 */

$seitentitel = 'Bestand';
require __DIR__ . '/partials/header.php';

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    $geaendert = 0;
    foreach (Util::postArray('bestand') as $variantenId => $menge) {
        $variantenId = (int) $variantenId;
        $alt = DB::value('SELECT bestand FROM varianten WHERE id = ?', [$variantenId]);
        if ($alt !== null && (int) $alt !== (int) $menge) {
            Bestand::setzen($variantenId, (int) $menge, (int) $benutzer['id']);
            $geaendert++;
        }
    }
    Util::redirect('bestand.php?suche=' . rawurlencode(Util::post('suche'))
        . '&meldung=' . rawurlencode($geaendert === 0 ? 'Nichts geändert.' : $geaendert . ' Bestände aktualisiert.'));
}

$suche    = Util::get('suche');
$varianten = Bestand::uebersicht($suche);
$verlauf  = Util::getInt('verlauf');
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Bestand</h1>
    <div class="ad-untertitel"><?= count($varianten) ?> Varianten</div>
  </div>
</div>

<?php if ($verlauf > 0): ?>
  <?php $bewegungen = Bestand::bewegungen($verlauf, 50); ?>
  <section class="ad-karte">
    <div class="ad-karte-kopf">
      <h2>Bestandsverlauf</h2>
      <a class="ad-knopf ad-knopf-klein" href="bestand.php?suche=<?= Util::e($suche) ?>">Schließen</a>
    </div>
    <div class="ad-karte-inhalt eng">
      <?php if ($bewegungen === []): ?>
        <div class="ad-leer" style="padding:24px"><p>Noch keine Bewegungen aufgezeichnet.</p></div>
      <?php else: ?>
        <div class="ad-tabelle-rahmen"><table>
          <thead><tr><th>Zeit</th><th class="ad-zahl-rechts">Menge</th><th>Grund</th><th>Bestellung</th><th>Von</th></tr></thead>
          <tbody>
            <?php foreach ($bewegungen as $bewegung): ?>
              <tr>
                <td class="ad-neben"><?= Util::e(Util::dt((string) $bewegung['erstellt'])) ?></td>
                <td class="ad-zahl-rechts">
                  <strong style="color:<?= (int) $bewegung['menge'] < 0 ? 'var(--rot)' : 'var(--gruen)' ?>">
                    <?= (int) $bewegung['menge'] > 0 ? '+' : '' ?><?= (int) $bewegung['menge'] ?>
                  </strong>
                </td>
                <td><?= Util::e(Bestand::GRUENDE[(string) $bewegung['grund']] ?? (string) $bewegung['grund']) ?></td>
                <td><?php if ($bewegung['bestellnummer'] !== null): ?>
                  <a href="bestellung.php?id=<?= (int) $bewegung['bestellung_id'] ?>">#<?= (int) $bewegung['bestellnummer'] ?></a>
                <?php else: ?>—<?php endif; ?></td>
                <td class="ad-neben"><?= Util::e((string) ($bewegung['benutzer_name'] ?: 'System')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<form method="post" class="ad-karte">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="suche" value="<?= Util::e($suche) ?>">

  <div class="ad-werkzeug">
    <input type="search" name="suche" form="bestandsuche" placeholder="Artikel oder Artikelnummer suchen …"
           value="<?= Util::e($suche) ?>" data-auto-suche>
    <noscript><button class="ad-knopf ad-knopf-klein" type="submit" form="bestandsuche">Suchen</button></noscript>
  </div>

  <div class="ad-karte-inhalt eng">
    <?php if ($varianten === []): ?>
      <div class="ad-leer"><h3>Keine Varianten</h3><p>Lege zuerst Artikel an.</p></div>
    <?php else: ?>
      <div class="ad-tabelle-rahmen"><table>
        <thead><tr>
          <th>Artikel</th><th>Artikelnr.</th><th>Status</th>
          <th class="ad-zahl-rechts">Preis</th><th class="ad-zahl-rechts" style="width:140px">Bestand</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($varianten as $variante): ?>
            <tr>
              <td>
                <a href="artikel-bearbeiten.php?id=<?= (int) $variante['artikel_id'] ?>" class="ad-haupt"
                   style="color:inherit"><?= Util::e((string) $variante['artikel']) ?></a>
                <div class="ad-neben"><?= Util::e((string) $variante['titel']) ?></div>
              </td>
              <td class="ad-neben"><?= Util::e((string) ($variante['artikelnummer'] ?: '—')) ?></td>
              <td>
                <span class="ad-marke <?= (string) $variante['status'] === 'aktiv' ? 'ad-marke-gruen' : '' ?>">
                  <i></i><?= Util::e(Artikel::STATUS[(string) $variante['status']] ?? '') ?>
                </span>
              </td>
              <td class="ad-zahl-rechts"><?= Util::e(Util::geld((int) $variante['preis'])) ?></td>
              <td class="ad-zahl-rechts">
                <?php if ((int) $variante['bestand_fuehren'] === 1): ?>
                  <input type="number" name="bestand[<?= (int) $variante['id'] ?>]"
                         value="<?= (int) $variante['bestand'] ?>" data-bestand-feld
                         style="width:82px;padding:5px 8px;border:1px solid var(--rahmen-kraeftig);
                                border-radius:6px;text-align:right;font:inherit">
                <?php else: ?>
                  <span class="ad-neben">nicht geführt</span>
                <?php endif; ?>
              </td>
              <td class="ad-zahl-rechts">
                <a class="ad-knopf ad-knopf-klein ad-knopf-leer"
                   href="bestand.php?verlauf=<?= (int) $variante['id'] ?>&suche=<?= Util::e($suche) ?>"
                   title="Verlauf">≡</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php if ($varianten !== [] && Auth::darf('pflegen')): ?>
    <div class="ad-karte-fuss">
      <span class="ad-neben" style="margin-right:auto">Änderungen werden als Korrektur protokolliert.</span>
      <button class="ad-knopf ad-knopf-voll" type="submit">Bestände speichern</button>
    </div>
  <?php endif; ?>
</form>

<form id="bestandsuche" method="get"></form>

<?php require __DIR__ . '/partials/footer.php'; ?>
