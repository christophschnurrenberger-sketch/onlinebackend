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

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Bestellungen</h1>
    <div class="ad-untertitel">
      <?= (int) $ergebnis['gesamt'] ?> Bestellungen · <?= Util::e(Util::geld((int) $ergebnis['summe'])) ?> Umsatz
    </div>
  </div>
  <div class="ad-aktionen">
    <a class="ad-knopf" href="export.php?<?= http_build_query(['was' => 'bestellungen', 'suche' => $suche]) ?>">CSV exportieren</a>
  </div>
</div>

<section class="ad-karte">
  <div class="ad-reiter">
    <?php foreach ($reiter as $wert => [$label, $unused]): ?>
      <a href="bestellungen.php?filter=<?= Util::e($wert) ?>"
         class="<?= $aktiverReiter === $wert ? 'aktiv' : '' ?>"><?= Util::e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="ad-werkzeug" method="get">
    <input type="hidden" name="filter" value="<?= Util::e($aktiverReiter) ?>">
    <input type="search" name="suche" placeholder="Bestellnummer, E-Mail oder Name …"
           value="<?= Util::e($suche) ?>" data-auto-suche>
    <noscript><button class="ad-knopf ad-knopf-klein" type="submit">Suchen</button></noscript>
  </form>

  <div class="ad-karte-inhalt eng">
    <?php if ($ergebnis['zeilen'] === []): ?>
      <div class="ad-leer">
        <h3>Keine Bestellungen</h3>
        <p><?= $suche !== '' || $aktiverReiter !== ''
            ? 'Für diesen Filter gibt es keine Bestellungen.'
            : 'Sobald jemand im Shop bestellt, erscheint die Bestellung hier.' ?></p>
      </div>
    <?php else: ?>
      <div class="ad-tabelle-rahmen"><table>
        <thead><tr>
          <th>Nr.</th><th>Datum</th><th>Kunde</th><th>Zahlung</th><th>Versand</th>
          <th class="ad-zahl-rechts">Artikel</th><th class="ad-zahl-rechts">Summe</th>
        </tr></thead>
        <tbody>
          <?php foreach ($ergebnis['zeilen'] as $bestellung): ?>
            <?php $a = $bestellung['lieferadresse_daten']; ?>
            <tr class="ad-klick" style="cursor:pointer"
                onclick="location='bestellung.php?id=<?= (int) $bestellung['id'] ?>'">
              <td>
                <strong>#<?= (int) $bestellung['nummer'] ?></strong>
                <?php if ((string) $bestellung['status'] === 'storniert'): ?>
                  <div><span class="ad-marke ad-marke-rot"><i></i>Storniert</span></div>
                <?php endif; ?>
              </td>
              <td class="ad-neben" title="<?= Util::e(Util::dt((string) $bestellung['erstellt'])) ?>">
                <?= Util::e(Util::seit((string) $bestellung['erstellt'])) ?>
              </td>
              <td>
                <div class="ad-haupt"><?= Util::e(trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? '')) ?: '—') ?></div>
                <div class="ad-neben"><?= Util::e((string) $bestellung['email']) ?></div>
              </td>
              <td><?php require __DIR__ . '/partials/zahlmarke.php'; ?></td>
              <td>
                <?php
                $vs = (string) $bestellung['versandstatus'];
                $vk = $vs === 'versendet' ? 'ad-marke-gruen' : ($vs === 'teilweise' ? 'ad-marke-gelb' : '');
                ?>
                <span class="ad-marke <?= $vk ?>"><i></i><?= Util::e(Bestellungen::VERSANDSTATUS[$vs] ?? $vs) ?></span>
              </td>
              <td class="ad-zahl-rechts"><?= (int) $bestellung['artikelzahl'] ?></td>
              <td class="ad-zahl-rechts"><strong><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php
  $seiten = max(1, (int) ceil($ergebnis['gesamt'] / $proSeite));
  if ($seiten > 1): ?>
    <div class="ad-blaettern">
      <span><?= ($seite - 1) * $proSeite + 1 ?>–<?= min($seite * $proSeite, (int) $ergebnis['gesamt']) ?>
        von <?= (int) $ergebnis['gesamt'] ?></span>
      <span class="ad-knopfgruppe">
        <?php $link = static fn(int $n): string => 'bestellungen.php?'
            . http_build_query(['filter' => $aktiverReiter, 'suche' => $suche, 's' => $n]); ?>
        <?php if ($seite > 1): ?>
          <a class="ad-knopf ad-knopf-klein" href="<?= Util::e($link($seite - 1)) ?>">Zurück</a>
        <?php endif; ?>
        <?php if ($seite < $seiten): ?>
          <a class="ad-knopf ad-knopf-klein" href="<?= Util::e($link($seite + 1)) ?>">Weiter</a>
        <?php endif; ?>
      </span>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
