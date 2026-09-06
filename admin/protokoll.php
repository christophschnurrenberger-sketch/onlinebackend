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

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Protokoll</h1>
    <div class="bk-untertitel">Die letzten Ereignisse im System</div>
  </div>
  <?php if (Auth::darf('einstellen')): ?>
    <div class="bk-aktionen">
      <form method="post" data-frage="Einträge älter als 30 Tage löschen?">
        <?= Auth::csrfFeld() ?>
        <button class="bk-knopf" type="submit">Alte Einträge löschen</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<section class="bk-karte">
  <div class="bk-reiter">
    <?php foreach (['' => 'Alle', 'info' => 'Information', 'warnung' => 'Warnungen', 'fehler' => 'Fehler'] as $wert => $label): ?>
      <a href="protokoll.php?ebene=<?= Util::e($wert) ?>" class="<?= $ebene === $wert ? 'aktiv' : '' ?>"><?= Util::e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="bk-karte-inhalt eng">
    <?php if ($zeilen === []): ?>
      <div class="bk-leer"><h3>Keine Einträge</h3><p>Hier ist noch nichts protokolliert.</p></div>
    <?php else: ?>
      <div class="bk-tabelle-rahmen"><table>
        <thead><tr><th>Zeit</th><th>Ebene</th><th>Bereich</th><th>Meldung</th></tr></thead>
        <tbody>
          <?php foreach ($zeilen as $zeile): ?>
            <?php $klasse = match ((string) $zeile['ebene']) {
                'fehler'  => 'bk-marke-rot',
                'warnung' => 'bk-marke-gelb',
                default   => '',
            }; ?>
            <tr>
              <td class="bk-neben" style="white-space:nowrap"><?= Util::e(Util::dt((string) $zeile['erstellt'])) ?></td>
              <td><span class="bk-marke <?= $klasse ?>"><i></i><?= Util::e((string) $zeile['ebene']) ?></span></td>
              <td class="bk-neben"><?= Util::e((string) $zeile['bereich']) ?></td>
              <td><?= Util::e((string) $zeile['text']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
