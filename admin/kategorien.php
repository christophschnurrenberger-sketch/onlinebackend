<?php
/** Kategorienliste. */

$seitentitel = 'Kategorien';
require __DIR__ . '/partials/header.php';

$kategorien = Kategorien::liste(null, Util::get('suche'));
?>
<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Kategorien</h1>
    <div class="ad-untertitel"><?= count($kategorien) ?> Kategorien</div>
  </div>
  <div class="ad-aktionen">
    <a class="ad-knopf ad-knopf-voll" href="kategorie.php">Kategorie anlegen</a>
  </div>
</div>

<section class="ad-karte">
  <form class="ad-werkzeug" method="get">
    <input type="search" name="suche" placeholder="Kategorie suchen …" value="<?= Util::e(Util::get('suche')) ?>" data-auto-suche>
    <noscript><button class="ad-knopf ad-knopf-klein" type="submit">Suchen</button></noscript>
  </form>
  <div class="ad-karte-inhalt eng">
    <?php if ($kategorien === []): ?>
      <div class="ad-leer">
        <h3>Noch keine Kategorien</h3>
        <p>Kategorien gliedern deinen Shop und tauchen in der Navigation auf.</p>
        <a class="ad-knopf ad-knopf-voll" href="kategorie.php">Kategorie anlegen</a>
      </div>
    <?php else: ?>
      <div class="ad-tabelle-rahmen"><table>
        <thead><tr><th>Kategorie</th><th>Art</th><th class="ad-zahl-rechts">Artikel</th><th>Sichtbar</th></tr></thead>
        <tbody>
          <?php foreach ($kategorien as $kategorie): ?>
            <tr class="ad-klick" style="cursor:pointer" onclick="location='kategorie.php?id=<?= (int) $kategorie['id'] ?>'">
              <td>
                <div class="ad-haupt"><?= Util::e((string) $kategorie['titel']) ?></div>
                <div class="ad-neben">kategorie.php?h=<?= Util::e((string) $kategorie['handle']) ?></div>
              </td>
              <td>
                <?php if ((string) $kategorie['art'] === 'automatisch'): ?>
                  <span class="ad-marke ad-marke-blau"><i></i>Automatisch
                    (<?= count($kategorie['regelliste']) ?> Regel<?= count($kategorie['regelliste']) === 1 ? '' : 'n' ?>)</span>
                <?php else: ?>
                  <span class="ad-marke"><i></i>Manuell</span>
                <?php endif; ?>
              </td>
              <td class="ad-zahl-rechts"><?= (int) $kategorie['anzahl'] ?></td>
              <td>
                <?php if ((int) $kategorie['sichtbar'] === 1): ?>
                  <span class="ad-marke ad-marke-gruen"><i></i>Im Shop</span>
                <?php else: ?>
                  <span class="ad-marke"><i></i>Versteckt</span>
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
