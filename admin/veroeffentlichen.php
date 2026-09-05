<?php
/**
 * Veröffentlichen.
 *
 * Zeigt vor dem Klick, was sich seit der letzten Veröffentlichung geändert hat.
 * Ohne diese Liste wäre „Veröffentlichen“ ein Blindflug – gerade wenn mehrere
 * Personen im Backend arbeiten.
 */

$seitentitel = 'Veröffentlichen';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

if (Util::isPost()) {
    Auth::csrfPruefen();
    try {
        if (Util::post('aktion') === 'zurueck') {
            if (!Auth::darf('einstellen')) {
                throw new RuntimeException('Für das Zurücksetzen fehlt die Berechtigung.');
            }
            $version = Util::postInt('version');
            Veroeffentlichung::zurueck($version);
            Util::redirect('veroeffentlichen.php?meldung=' . rawurlencode('Fassung ' . $version . ' ist wieder live.'));
        }

        $ergebnis = Veroeffentlichung::veroeffentlichen(Util::post('notiz'), (int) $benutzer['id']);
        Util::redirect('veroeffentlichen.php?meldung='
            . rawurlencode('Fassung ' . $ergebnis['version'] . ' ist live: '
                . $ergebnis['kennzahlen']['artikel'] . ' Artikel, '
                . $ergebnis['kennzahlen']['kategorien'] . ' Kategorien.'));
    } catch (Throwable $e) {
        echo '<div class="ad-hinweis ad-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$offen     = Veroeffentlichung::offeneAenderungen();
$fassungen = Veroeffentlichung::fassungen(20);
$liveVersion = Veroeffentlichung::liveVersion();
$kannVeroeffentlichen = $offen['nie'] || $offen['anzahl'] > 0;

$artNamen = ['neu' => 'neu', 'geaendert' => 'geändert', 'entfernt' => 'entfernt'];
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Veröffentlichen</h1>
    <div class="ad-untertitel">
      <?php if ($liveVersion > 0): ?>
        Der Shop zeigt Fassung <?= $liveVersion ?>
        <?php $zuletzt = Settings::get('zuletzt_veroeffentlicht');
        if ($zuletzt !== '') { echo ' · zuletzt ' . Util::e(Util::seit($zuletzt)); } ?>
      <?php else: ?>
        Noch nie veröffentlicht
      <?php endif; ?>
    </div>
  </div>
  <div class="ad-aktionen">
    <a class="ad-knopf" href="<?= Util::e(Config::baseUrl()) ?>/" target="_blank" rel="noopener">Shop ansehen ↗</a>
  </div>
</div>

<?php if ($offen['nie']): ?>
  <div class="ad-hinweis ad-hinweis-warnung">
    <strong>Der Shop ist noch nicht online</strong>
    Besucher sehen bisher nur einen Hinweis. Mit dem ersten Veröffentlichen geht der Katalog live.
  </div>
<?php elseif ($offen['anzahl'] === 0): ?>
  <div class="ad-hinweis ad-hinweis-erfolg">
    <strong>Alles veröffentlicht</strong>
    Der Shop zeigt genau den Stand, den du im Backend siehst.
  </div>
<?php endif; ?>

<div class="ad-zwei">
  <form method="post" class="ad-karte">
    <?= Auth::csrfFeld() ?>
    <div class="ad-karte-kopf">
      <h2>Offene Änderungen<?= $offen['anzahl'] > 0 ? ' (' . $offen['anzahl'] . ')' : '' ?></h2>
    </div>
    <div class="ad-karte-inhalt">
      <?php if ($offen['nie']): ?>
        <p class="ad-tipp">Der gesamte aktuelle Stand geht mit dem ersten Veröffentlichen live.</p>
      <?php elseif ($offen['liste'] === []): ?>
        <p class="ad-tipp">Seit der letzten Veröffentlichung wurde nichts geändert.</p>
      <?php else: ?>
        <ul class="ad-aenderungen">
          <?php foreach ($offen['liste'] as $aenderung): ?>
            <li>
              <span class="ad-art ad-art-<?= Util::e((string) $aenderung['art']) ?>">
                <?= Util::e($artNamen[(string) $aenderung['art']] ?? (string) $aenderung['art']) ?>
              </span>
              <span class="ad-neben"><?= Util::e((string) $aenderung['typ']) ?></span>
              <strong><?= Util::e((string) $aenderung['titel']) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="ad-feld" style="margin-top:16px">
        <label for="notiz">Notiz zur Fassung (optional)</label>
        <input type="text" id="notiz" name="notiz" placeholder="z. B. Herbstkollektion online">
        <div class="ad-tipp">Hilft später im Verlauf, die richtige Fassung wiederzufinden.</div>
      </div>
    </div>
    <div class="ad-karte-fuss">
      <button class="ad-knopf ad-knopf-gruen" type="submit" <?= $kannVeroeffentlichen ? '' : 'disabled' ?>>
        Jetzt veröffentlichen
      </button>
    </div>
  </form>

  <section class="ad-karte">
    <div class="ad-karte-kopf"><h2>Verlauf</h2></div>
    <div class="ad-karte-inhalt eng">
      <?php if ($fassungen === []): ?>
        <div class="ad-leer" style="padding:24px"><p>Noch keine Veröffentlichung.</p></div>
      <?php else: ?>
        <div class="ad-tabelle-rahmen"><table>
          <thead><tr><th>Fassung</th><th>Inhalt</th><th>Wann</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($fassungen as $fassung): ?>
              <tr>
                <td>
                  <strong>v<?= (int) $fassung['version'] ?></strong>
                  <?php if ((int) $fassung['live'] === 1): ?>
                    <div><span class="ad-marke ad-marke-gruen"><i></i>live</span></div>
                  <?php endif; ?>
                </td>
                <td class="ad-neben">
                  <?= (int) ($fassung['zahlen']['artikel'] ?? 0) ?> Artikel ·
                  <?= (int) ($fassung['zahlen']['kategorien'] ?? 0) ?> Kategorien
                  <?php if ((string) $fassung['notiz'] !== ''): ?>
                    <div><?= Util::e((string) $fassung['notiz']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="ad-neben" title="<?= Util::e(Util::dt((string) $fassung['erstellt'])) ?>">
                  <?= Util::e(Util::seit((string) $fassung['erstellt'])) ?>
                  <?php if ((string) ($fassung['benutzer_name'] ?? '') !== ''): ?>
                    <div><?= Util::e((string) $fassung['benutzer_name']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="ad-zahl-rechts">
                  <?php if ((int) $fassung['live'] !== 1 && Auth::darf('einstellen')): ?>
                    <form method="post" style="display:inline"
                          data-frage="Auf Fassung <?= (int) $fassung['version'] ?> zurücksetzen? Der Shop zeigt sofort wieder diesen älteren Stand.">
                      <?= Auth::csrfFeld() ?>
                      <input type="hidden" name="aktion" value="zurueck">
                      <input type="hidden" name="version" value="<?= (int) $fassung['version'] ?>">
                      <button class="ad-knopf ad-knopf-klein" type="submit">Zurücksetzen</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
