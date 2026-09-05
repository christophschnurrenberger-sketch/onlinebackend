<?php
/** Protokoll – was das System zuletzt getan hat. */

$seitentitel = 'Protokoll';
require __DIR__ . '/partials/header.php';

if (Util::isPost() && Auth::darf('einstellen')) {
    Auth::csrfPruefen();
    $weg = Log::aufraeumen(30);
    Util::redirect('protokoll.php?meldung=' . rawurlencode($weg . ' alte Einträge entfernt.'));
}

$ebene = Util::einesVon(Util::get('ebene'), ['', 'info', 'warnung', 'fehler'], '');
$zeilen = Log::letzte(300, $ebene);
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Protokoll</h1>
    <div class="ad-untertitel">Die letzten Ereignisse im System</div>
  </div>
  <?php if (Auth::darf('einstellen')): ?>
    <div class="ad-aktionen">
      <form method="post" data-frage="Einträge älter als 30 Tage löschen?">
        <?= Auth::csrfFeld() ?>
        <button class="ad-knopf" type="submit">Alte Einträge löschen</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<section class="ad-karte">
  <div class="ad-reiter">
    <?php foreach (['' => 'Alle', 'info' => 'Information', 'warnung' => 'Warnungen', 'fehler' => 'Fehler'] as $wert => $label): ?>
      <a href="protokoll.php?ebene=<?= Util::e($wert) ?>" class="<?= $ebene === $wert ? 'aktiv' : '' ?>"><?= Util::e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="ad-karte-inhalt eng">
    <?php if ($zeilen === []): ?>
      <div class="ad-leer"><h3>Keine Einträge</h3><p>Hier ist noch nichts protokolliert.</p></div>
    <?php else: ?>
      <div class="ad-tabelle-rahmen"><table>
        <thead><tr><th>Zeit</th><th>Ebene</th><th>Bereich</th><th>Meldung</th></tr></thead>
        <tbody>
          <?php foreach ($zeilen as $zeile): ?>
            <?php $klasse = match ((string) $zeile['ebene']) {
                'fehler'  => 'ad-marke-rot',
                'warnung' => 'ad-marke-gelb',
                default   => '',
            }; ?>
            <tr>
              <td class="ad-neben" style="white-space:nowrap"><?= Util::e(Util::dt((string) $zeile['erstellt'])) ?></td>
              <td><span class="ad-marke <?= $klasse ?>"><i></i><?= Util::e((string) $zeile['ebene']) ?></span></td>
              <td class="ad-neben"><?= Util::e((string) $zeile['bereich']) ?></td>
              <td><?= Util::e((string) $zeile['text']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
