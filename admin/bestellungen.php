<?php
/** Bestellliste mit Filtern. */

$seitentitel = 'Bestellungen';
require __DIR__ . '/partials/header.php';

$reiter = [
    ''            => ['Alle', []],
    'offen'       => ['Offen', ['status' => 'offen']],
    'unbezahlt'   => ['Unbezahlt', ['zahlstatus' => 'offen', 'status' => 'offen']],
    'zuversenden' => ['Zu versenden', ['versandstatus' => 'offen', 'status' => 'offen']],
    'archiviert'  => ['Archiviert', ['status' => 'archiviert']],
];
$aktiverReiter = Util::einesVon(Util::get('filter'), array_keys($reiter), '');
$suche    = Util::get('suche');
$seite    = max(1, Util::getInt('s', 1));
$proSeite = 50;

$ergebnis = Bestellungen::liste(array_merge(
    $reiter[$aktiverReiter][1],
    ['suche' => $suche, 'limit' => $proSeite, 'offset' => ($seite - 1) * $proSeite]
));
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Bestellungen</h1>
    <div class="bk-untertitel">
      <?= (int) $ergebnis['gesamt'] ?> Bestellungen · <?= Util::e(Util::geld((int) $ergebnis['summe'])) ?> Umsatz
    </div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf" href="export.php?<?= http_build_query(['was' => 'bestellungen', 'suche' => $suche]) ?>">CSV exportieren</a>
  </div>
</div>

<section class="bk-karte">
  <div class="bk-reiter">
    <?php foreach ($reiter as $wert => [$label, $unused]): ?>
      <a href="bestellungen.php?filter=<?= Util::e($wert) ?>"
         class="<?= $aktiverReiter === $wert ? 'aktiv' : '' ?>"><?= Util::e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="bk-werkzeug" method="get">
    <input type="hidden" name="filter" value="<?= Util::e($aktiverReiter) ?>">
    <input type="search" name="suche" placeholder="Bestellnummer, E-Mail oder Name …"
           value="<?= Util::e($suche) ?>" data-auto-suche>
    <noscript><button class="bk-knopf bk-knopf-klein" type="submit">Suchen</button></noscript>
  </form>

  <div class="bk-karte-inhalt eng">
    <?php if ($ergebnis['zeilen'] === []): ?>
      <div class="bk-leer">
        <h3>Keine Bestellungen</h3>
        <p><?= $suche !== '' || $aktiverReiter !== ''
            ? 'Für diesen Filter gibt es keine Bestellungen.'
            : 'Sobald jemand im Shop bestellt, erscheint die Bestellung hier.' ?></p>
      </div>
    <?php else: ?>
      <div class="bk-tabelle-rahmen"><table>
        <thead><tr>
          <th>Nr.</th><th>Datum</th><th>Kunde</th><th>Zahlung</th><th>Versand</th>
          <th class="bk-zahl-rechts">Artikel</th><th class="bk-zahl-rechts">Summe</th>
        </tr></thead>
        <tbody>
          <?php foreach ($ergebnis['zeilen'] as $bestellung): ?>
            <?php $a = $bestellung['lieferadresse_daten']; ?>
            <tr class="bk-klick" style="cursor:pointer"
                onclick="location='bestellung.php?id=<?= (int) $bestellung['id'] ?>'">
              <td>
                <strong>#<?= (int) $bestellung['nummer'] ?></strong>
                <?php if ((string) $bestellung['status'] === 'storniert'): ?>
                  <div><span class="bk-marke bk-marke-rot"><i></i>Storniert</span></div>
                <?php endif; ?>
              </td>
              <td class="bk-neben" title="<?= Util::e(Util::dt((string) $bestellung['erstellt'])) ?>">
                <?= Util::e(Util::seit((string) $bestellung['erstellt'])) ?>
              </td>
              <td>
                <div class="bk-haupt"><?= Util::e(trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? '')) ?: '—') ?></div>
                <div class="bk-neben"><?= Util::e((string) $bestellung['email']) ?></div>
              </td>
              <td><?php require __DIR__ . '/partials/zahlmarke.php'; ?></td>
              <td>
                <?php
                $vs = (string) $bestellung['versandstatus'];
                $vk = $vs === 'versendet' ? 'bk-marke-gruen' : ($vs === 'teilweise' ? 'bk-marke-gelb' : '');
                ?>
                <span class="bk-marke <?= $vk ?>"><i></i><?= Util::e(Bestellungen::VERSANDSTATUS[$vs] ?? $vs) ?></span>
              </td>
              <td class="bk-zahl-rechts"><?= (int) $bestellung['artikelzahl'] ?></td>
              <td class="bk-zahl-rechts"><strong><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php
  $seiten = max(1, (int) ceil($ergebnis['gesamt'] / $proSeite));
  if ($seiten > 1): ?>
    <div class="bk-blaettern">
      <span><?= ($seite - 1) * $proSeite + 1 ?>–<?= min($seite * $proSeite, (int) $ergebnis['gesamt']) ?>
        von <?= (int) $ergebnis['gesamt'] ?></span>
      <span class="bk-knopfgruppe">
        <?php $link = static fn(int $n): string => 'bestellungen.php?'
            . http_build_query(['filter' => $aktiverReiter, 'suche' => $suche, 's' => $n]); ?>
        <?php if ($seite > 1): ?>
          <a class="bk-knopf bk-knopf-klein" href="<?= Util::e($link($seite - 1)) ?>">Zurück</a>
        <?php endif; ?>
        <?php if ($seite < $seiten): ?>
          <a class="bk-knopf bk-knopf-klein" href="<?= Util::e($link($seite + 1)) ?>">Weiter</a>
        <?php endif; ?>
      </span>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
