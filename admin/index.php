<?php
/**
 * Übersicht.
 *
 * Beantwortet die drei Fragen, mit denen ein Betreiber den Tag beginnt:
 * Wie lief es? Was liegt an? Was fehlt gerade im Lager?
 */

$seitentitel = 'Übersicht';
require __DIR__ . '/partials/header.php';

$tage = Util::einesVon(Util::get('tage', '30'), ['7', '30', '90', '365'], '30');
$zahlen = Bestellungen::kennzahlen((int) $tage);
$knapp  = Bestand::knapp(5, 8);
$letzte = Bestellungen::liste(['limit' => 8])['zeilen'];

$katalog = [
    'Aktive Artikel' => (int) DB::value("SELECT COUNT(*) FROM artikel WHERE status = 'aktiv'", [], 0),
    'Entwürfe'       => (int) DB::value("SELECT COUNT(*) FROM artikel WHERE status = 'entwurf'", [], 0),
    'Kategorien'     => (int) DB::value('SELECT COUNT(*) FROM kategorien', [], 0),
    'Kunden'         => (int) DB::value('SELECT COUNT(*) FROM kunden', [], 0),
];

/** Veränderung zum Vorzeitraum als Text. */
function veraenderung(int $jetzt, int $vorher): string
{
    if ($vorher === 0) {
        return '<div class="ad-zahl-diff ad-neben">kein Vorzeitraum</div>';
    }
    $prozent = (int) round((($jetzt - $vorher) / $vorher) * 100);
    $klasse  = $prozent >= 0 ? 'ad-hoch' : 'ad-runter';
    return '<div class="ad-zahl-diff ' . $klasse . '">' . ($prozent >= 0 ? '▲' : '▼') . ' '
         . abs($prozent) . ' % zum Vorzeitraum</div>';
}
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Übersicht</h1>
    <div class="ad-untertitel">Letzte <?= (int) $tage ?> Tage</div>
  </div>
  <div class="ad-aktionen">
    <form method="get">
      <select name="tage" onchange="this.form.submit()">
        <?php foreach ([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '365 Tage'] as $wert => $label): ?>
          <option value="<?= $wert ?>" <?= (int) $tage === $wert ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="ad-knopf ad-knopf-klein" type="submit">Zeitraum</button></noscript>
    </form>
    <a class="ad-knopf ad-knopf-voll" href="artikel-bearbeiten.php">Artikel anlegen</a>
  </div>
</div>

<?php if ($offeneAenderungen['nie']): ?>
  <div class="ad-hinweis ad-hinweis-warnung">
    <strong>Der Shop ist noch nicht veröffentlicht</strong>
    Besucher sehen bisher nur einen Hinweis. Klicke oben rechts auf „Veröffentlichen“, sobald du bereit bist.
  </div>
<?php elseif ($offeneAenderungen['anzahl'] > 0): ?>
  <div class="ad-hinweis ad-hinweis-info">
    <strong><?= $offeneAenderungen['anzahl'] ?> Änderung<?= $offeneAenderungen['anzahl'] === 1 ? '' : 'en' ?> wartet auf Veröffentlichung</strong>
    <a href="veroeffentlichen.php">Änderungen ansehen und veröffentlichen</a>
  </div>
<?php endif; ?>

<div class="ad-vier" style="margin-bottom:16px">
  <div class="ad-zahl">
    <div class="ad-zahl-titel">Umsatz</div>
    <div class="ad-zahl-wert"><?= Util::e(Util::geld($zahlen['umsatz'])) ?></div>
    <?= veraenderung($zahlen['umsatz'], $zahlen['umsatz_vorher']) ?>
    <?php if (count($zahlen['taeglich']) > 1): ?>
      <?php $hoechster = max(1, max(array_map(static fn($t) => (int) $t['umsatz'], $zahlen['taeglich']))); ?>
      <div class="ad-balken">
        <?php foreach ($zahlen['taeglich'] as $tag): ?>
          <i style="height:<?= (int) round(((int) $tag['umsatz'] / $hoechster) * 100) ?>%"
             title="<?= Util::e((string) $tag['tag']) ?>: <?= Util::e(Util::geld((int) $tag['umsatz'])) ?>"></i>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="ad-zahl">
    <div class="ad-zahl-titel">Bestellungen</div>
    <div class="ad-zahl-wert"><?= (int) $zahlen['anzahl'] ?></div>
    <?= veraenderung($zahlen['anzahl'], $zahlen['anzahl_vorher']) ?>
  </div>
  <div class="ad-zahl">
    <div class="ad-zahl-titel">Ø Bestellwert</div>
    <div class="ad-zahl-wert"><?= Util::e(Util::geld($zahlen['schnitt'])) ?></div>
  </div>
  <div class="ad-zahl">
    <div class="ad-zahl-titel">Zu erledigen</div>
    <div class="ad-zahl-wert"><?= (int) $zahlen['unversendet'] ?></div>
    <div class="ad-zahl-diff ad-neben"><?= (int) $zahlen['unbezahlt'] ?> unbezahlt · <?= (int) $zahlen['offen'] ?> offen</div>
  </div>
</div>

<div class="ad-zwei">
  <section class="ad-karte">
    <div class="ad-karte-kopf">
      <h2>Neueste Bestellungen</h2>
      <a class="ad-knopf ad-knopf-klein" href="bestellungen.php">Alle ansehen</a>
    </div>
    <div class="ad-karte-inhalt eng">
      <?php if ($letzte === []): ?>
        <div class="ad-leer"><h3>Noch keine Bestellungen</h3>
          <p>Sobald jemand bestellt, erscheint die Bestellung hier.</p></div>
      <?php else: ?>
        <div class="ad-tabelle-rahmen"><table>
          <thead><tr><th>Nr.</th><th>Kunde</th><th>Zahlung</th><th class="ad-zahl-rechts">Summe</th><th>Zeit</th></tr></thead>
          <tbody>
            <?php foreach ($letzte as $bestellung): ?>
              <?php $a = $bestellung['lieferadresse_daten']; ?>
              <tr class="ad-klick" onclick="location='bestellung.php?id=<?= (int) $bestellung['id'] ?>'">
                <td><strong>#<?= (int) $bestellung['nummer'] ?></strong></td>
                <td><?= Util::e(trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? '')) ?: (string) $bestellung['email']) ?></td>
                <td><?php require __DIR__ . '/partials/zahlmarke.php'; ?></td>
                <td class="ad-zahl-rechts"><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></td>
                <td class="ad-neben"><?= Util::e(Util::seit((string) $bestellung['erstellt'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </section>

  <div>
    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Katalog</h2></div>
      <div class="ad-karte-inhalt">
        <?php foreach ($katalog as $name => $wert): ?>
          <div style="display:flex;justify-content:space-between;padding:5px 0">
            <span><?= Util::e($name) ?></span><strong><?= (int) $wert ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Bestand wird knapp</h2></div>
      <div class="ad-karte-inhalt eng">
        <?php if ($knapp === []): ?>
          <div class="ad-leer" style="padding:24px"><p>Alle Bestände sind in Ordnung.</p></div>
        <?php else: ?>
          <div class="ad-tabelle-rahmen"><table><tbody>
            <?php foreach ($knapp as $eintrag): ?>
              <tr class="ad-klick" onclick="location='artikel-bearbeiten.php?id=<?= (int) $eintrag['artikel_id'] ?>'">
                <td>
                  <div class="ad-haupt"><?= Util::e((string) $eintrag['artikel']) ?></div>
                  <div class="ad-neben"><?= Util::e((string) $eintrag['variante']) ?><?php
                    if ((string) $eintrag['artikelnummer'] !== '') {
                        echo ' · ' . Util::e((string) $eintrag['artikelnummer']);
                    }
                  ?></div>
                </td>
                <td class="ad-zahl-rechts">
                  <span class="ad-marke <?= (int) $eintrag['bestand'] <= 0 ? 'ad-marke-rot' : 'ad-marke-gelb' ?>">
                    <i></i><?= (int) $eintrag['bestand'] ?> Stück
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($zahlen['top_artikel'] !== []): ?>
      <section class="ad-karte">
        <div class="ad-karte-kopf"><h2>Meistverkauft</h2></div>
        <div class="ad-karte-inhalt eng">
          <div class="ad-tabelle-rahmen"><table><tbody>
            <?php foreach ($zahlen['top_artikel'] as $artikel): ?>
              <tr>
                <td><?= Util::e((string) $artikel['titel']) ?>
                  <div class="ad-neben"><?= (int) $artikel['menge'] ?> verkauft</div></td>
                <td class="ad-zahl-rechts"><?= Util::e(Util::geld((int) $artikel['umsatz'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody></table></div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
