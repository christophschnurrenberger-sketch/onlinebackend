<?php
/** Kategorienliste. */

$seitentitel = 'Kategorien';
require __DIR__ . '/partials/header.php';

$kategorien = Kategorien::liste(null, Util::get('suche'));
?>
<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Kategorien</h1>
    <div class="bk-untertitel"><?= count($kategorien) ?> Kategorien</div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf bk-knopf-voll" href="kategorie.php">Kategorie anlegen</a>
  </div>
</div>

<section class="bk-karte">
  <form class="bk-werkzeug" method="get">
    <input type="search" name="suche" placeholder="Kategorie suchen …" value="<?= Util::e(Util::get('suche')) ?>" data-auto-suche>
    <noscript><button class="bk-knopf bk-knopf-klein" type="submit">Suchen</button></noscript>
  </form>
  <div class="bk-karte-inhalt eng">
    <?php if ($kategorien === []): ?>
      <div class="bk-leer">
        <h3>Noch keine Kategorien</h3>
        <p>Kategorien gliedern deinen Shop und tauchen in der Navigation auf.</p>
        <a class="bk-knopf bk-knopf-voll" href="kategorie.php">Kategorie anlegen</a>
      </div>
    <?php else: ?>
      <div class="bk-tabelle-rahmen"><table>
        <thead><tr><th>Kategorie</th><th>Art</th><th class="bk-zahl-rechts">Artikel</th><th>Sichtbar</th></tr></thead>
        <tbody>
          <?php foreach ($kategorien as $kategorie): ?>
            <tr class="bk-klick" style="cursor:pointer" onclick="location='kategorie.php?id=<?= (int) $kategorie['id'] ?>'">
              <td>
                <div class="bk-haupt"><?= Util::e((string) $kategorie['titel']) ?></div>
                <div class="bk-neben">kategorie.php?h=<?= Util::e((string) $kategorie['handle']) ?></div>
              </td>
              <td>
                <?php if ((string) $kategorie['art'] === 'automatisch'): ?>
                  <span class="bk-marke bk-marke-blau"><i></i>Automatisch
                    (<?= count($kategorie['regelliste']) ?> Regel<?= count($kategorie['regelliste']) === 1 ? '' : 'n' ?>)</span>
                <?php else: ?>
                  <span class="bk-marke"><i></i>Manuell</span>
                <?php endif; ?>
              </td>
              <td class="bk-zahl-rechts"><?= (int) $kategorie['anzahl'] ?></td>
              <td>
                <?php if ((int) $kategorie['sichtbar'] === 1): ?>
                  <span class="bk-marke bk-marke-gruen"><i></i>Im Shop</span>
                <?php else: ?>
                  <span class="bk-marke"><i></i>Versteckt</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
