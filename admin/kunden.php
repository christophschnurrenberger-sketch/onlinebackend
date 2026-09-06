<?php
/** Kundenliste. */

$seitentitel = 'Kunden';
require __DIR__ . '/partials/header.php';

$suche      = Util::get('suche');
$sortierung = Util::einesVon(Util::get('sortierung', 'neueste'), ['neueste', 'umsatz', 'bestellungen', 'name'], 'neueste');
$seite      = max(1, Util::getInt('s', 1));
$proSeite   = 50;

$ergebnis = Kunden::liste($suche, $sortierung, $proSeite, ($seite - 1) * $proSeite);
?>
<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Kunden</h1>
    <div class="bk-untertitel"><?= (int) $ergebnis['gesamt'] ?> Kunden</div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf" href="export.php?<?= http_build_query(['was' => 'kunden', 'suche' => $suche]) ?>">CSV exportieren</a>
  </div>
</div>

<section class="bk-karte">
  <form class="bk-werkzeug" method="get">
    <input type="search" name="suche" placeholder="Name, E-Mail oder Firma …" value="<?= Util::e($suche) ?>" data-auto-suche>
    <select name="sortierung" onchange="this.form.submit()">
      <?php foreach ([
        'neueste' => 'Neueste zuerst', 'umsatz' => 'Höchster Umsatz',
        'bestellungen' => 'Meiste Bestellungen', 'name' => 'Name A–Z',
      ] as $wert => $label): ?>
        <option value="<?= $wert ?>" <?= $sortierung === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="bk-knopf bk-knopf-klein" type="submit">Anwenden</button></noscript>
  </form>

  <div class="bk-karte-inhalt eng">
    <?php if ($ergebnis['zeilen'] === []): ?>
      <div class="bk-leer"><h3>Keine Kunden</h3>
        <p>Kunden entstehen automatisch mit der ersten Bestellung.</p></div>
    <?php else: ?>
      <div class="bk-tabelle-rahmen"><table>
        <thead><tr><th>Kunde</th><th>Firma</th><th class="bk-zahl-rechts">Bestellungen</th>
          <th class="bk-zahl-rechts">Umsatz</th><th>Kunde seit</th></tr></thead>
        <tbody>
          <?php foreach ($ergebnis['zeilen'] as $kunde): ?>
            <tr class="bk-klick" style="cursor:pointer" onclick="location='kunde.php?id=<?= (int) $kunde['id'] ?>'">
              <td>
                <div class="bk-haupt"><?= Util::e(trim((string) $kunde['vorname'] . ' ' . (string) $kunde['nachname']) ?: (string) $kunde['email']) ?></div>
                <div class="bk-neben"><?= Util::e((string) $kunde['email']) ?></div>
              </td>
              <td class="bk-neben"><?= Util::e((string) ($kunde['firma'] ?: '—')) ?></td>
              <td class="bk-zahl-rechts"><?= (int) $kunde['bestellungen'] ?></td>
              <td class="bk-zahl-rechts"><strong><?= Util::e(Util::geld((int) $kunde['umsatz'])) ?></strong></td>
              <td class="bk-neben"><?= Util::e(Util::dt((string) $kunde['erstellt'], 'd.m.Y')) ?></td>
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
        <?php $link = static fn(int $n): string => 'kunden.php?'
            . http_build_query(['suche' => $suche, 'sortierung' => $sortierung, 's' => $n]); ?>
        <?php if ($seite > 1): ?><a class="bk-knopf bk-knopf-klein" href="<?= Util::e($link($seite - 1)) ?>">Zurück</a><?php endif; ?>
        <?php if ($seite < $seiten): ?><a class="bk-knopf bk-knopf-klein" href="<?= Util::e($link($seite + 1)) ?>">Weiter</a><?php endif; ?>
      </span>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
