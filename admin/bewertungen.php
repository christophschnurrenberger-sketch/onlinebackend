<?php
/** Bewertungen – lesen, freigeben, beantworten. */

$seitentitel = 'Bewertungen';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    $id = Util::postInt('id');
    try {
        switch (Util::post('aktion')) {
            case 'frei':
            case 'versteckt':
            case 'neu':
                Bewertungen::statusSetzen($id, Util::post('aktion'));
                Util::redirect('bewertungen.php?filter=' . rawurlencode(Util::post('filter'))
                    . '&meldung=' . rawurlencode('Bewertung aktualisiert.'));

            case 'antwort':
                Bewertungen::antworten($id, Util::postRaw('antwort'));
                Util::redirect('bewertungen.php?filter=' . rawurlencode(Util::post('filter'))
                    . '&meldung=' . rawurlencode('Antwort gespeichert.'));

            case 'loeschen':
                Bewertungen::loeschen($id);
                Util::redirect('bewertungen.php?filter=' . rawurlencode(Util::post('filter'))
                    . '&meldung=' . rawurlencode('Bewertung gelöscht.'));

            case 'einstellungen':
                Settings::setMany([
                    'bewertungen_an'       => Util::postBool('bewertungen_an') ? '1' : '0',
                    'bewertungen_freigabe' => Util::einesVon(Util::post('bewertungen_freigabe'), ['manuell', 'sofort'], 'manuell'),
                ]);
                Util::redirect('bewertungen.php?filter=' . rawurlencode(Util::post('filter'))
                    . '&meldung=' . rawurlencode('Einstellung gespeichert – sie wirkt sofort.'));
        }
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$filter    = Util::einesVon(Util::get('filter', 'neu'), ['', 'neu', 'frei', 'versteckt'], 'neu');
$zeilen    = Bewertungen::liste($filter);
$offen     = Bewertungen::offene();
$angezeigt = Theme::bewertungenAn();
$freigabe  = Settings::get('bewertungen_freigabe', 'manuell');

$reiter = [
    'neu'       => 'Wartet auf Freigabe',
    'frei'      => 'Veröffentlicht',
    'versteckt' => 'Nicht veröffentlicht',
    ''          => 'Alle',
];
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Bewertungen</h1>
    <div class="bk-untertitel">
      <?= $offen ?> warte<?= $offen === 1 ? 't' : 'n' ?> auf Freigabe
    </div>
  </div>
</div>

<?php if (!$angezeigt): ?>
  <div class="bk-hinweis bk-hinweis-warnung">
    <strong>Bewertungen sind im Shop abgeschaltet.</strong>
    Unten lassen sie sich einschalten – bis dahin sieht sie niemand außer dir.
  </div>
<?php endif; ?>

<?php
/*
 * Der wichtigste Satz auf dieser Seite.
 *
 * Eine schlechte Bewertung wegzuklicken ist verlockend und verboten: Der
 * Anhang zu § 3 Abs. 3 UWG (Nr. 23b und 23c) verbietet gefälschte Bewertungen
 * ebenso wie das gezielte Aussortieren negativer. Wer nur die guten
 * veröffentlicht, riskiert eine Abmahnung – und verliert den Nutzen: Ein
 * Shop, in dem alles fünf Sterne hat, wird nicht geglaubt.
 */
?>
<div class="bk-hinweis bk-hinweis-info">
  <strong>Freigeben heißt lesen, nicht auswählen.</strong>
  Aussortiert werden darf nur, was beleidigend, rechtswidrig oder offensichtlich
  Spam ist. Eine Bewertung zu verstecken, <em>weil</em> sie schlecht ist, ist nach
  dem Anhang zu § 3 Abs. 3 UWG (Nr. 23b, 23c) unzulässig – und schadet doppelt:
  Eine Notenverteilung ohne einzige kritische Stimme glaubt kein Kunde.
  Besser als Verstecken ist eine sachliche Antwort darunter.
</div>

<section class="bk-karte">
  <div class="bk-reiter">
    <?php foreach ($reiter as $wert => $label): ?>
      <a href="bewertungen.php?filter=<?= Util::e($wert) ?>" class="<?= $filter === $wert ? 'aktiv' : '' ?>">
        <?= Util::e($label) ?>
        <?php if ($wert === 'neu' && $offen > 0): ?><span class="bk-zaehler"><?= $offen ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="bk-karte-inhalt">
    <?php if ($zeilen === []): ?>
      <div class="bk-leer">
        <h3>Nichts hier</h3>
        <p><?= $filter === 'neu'
            ? 'Es wartet keine Bewertung auf Freigabe.'
            : 'In dieser Ansicht liegt nichts.' ?></p>
      </div>
    <?php endif; ?>

    <?php foreach ($zeilen as $zeile): ?>
      <?php
      $sterne = (int) $zeile['sterne'];
      $marke  = match ((string) $zeile['status']) {
          'frei'      => ['bk-marke-gruen', 'Veröffentlicht'],
          'versteckt' => ['bk-marke-rot', 'Nicht veröffentlicht'],
          default     => ['bk-marke-gelb', 'Wartet auf Freigabe'],
      };
      ?>
      <article class="bk-bewertung">
        <div class="bk-bewertung-kopf">
          <span class="bk-sterne" title="<?= $sterne ?> von 5 Sternen">
            <?= str_repeat('★', $sterne) ?><span class="bk-sterne-rest"><?= str_repeat('★', 5 - $sterne) ?></span>
          </span>
          <?php if ((string) $zeile['titel'] !== ''): ?>
            <strong><?= Util::e((string) $zeile['titel']) ?></strong>
          <?php endif; ?>
          <span class="bk-marke <?= $marke[0] ?>"><i></i><?= $marke[1] ?></span>
          <?php if ($zeile['bestellung_id'] !== null): ?>
            <a class="bk-marke bk-marke-gruen" href="bestellung.php?id=<?= (int) $zeile['bestellung_id'] ?>"
               title="Bezahlte Bestellung mit diesem Artikel unter derselben E-Mail-Adresse"><i></i>Verifizierter Kauf</a>
          <?php else: ?>
            <span class="bk-marke"><i></i>Kein Kaufnachweis</span>
          <?php endif; ?>
        </div>

        <div class="bk-bewertung-wer">
          <?= Util::e((string) $zeile['name']) ?>
          &lt;<?= Util::e((string) $zeile['email']) ?>&gt;
          · <?= Util::e(Util::dt((string) $zeile['erstellt'])) ?>
          · <?php if ((string) $zeile['artikel_handle'] !== ''): ?>
              <a href="<?= Util::e(Config::url('artikel.php?h=' . rawurlencode((string) $zeile['artikel_handle']))) ?>"
                 target="_blank" rel="noopener"><?= Util::e((string) $zeile['artikel_titel']) ?> ↗</a>
            <?php else: ?>
              <em>Artikel gelöscht</em>
            <?php endif; ?>
        </div>

        <p class="bk-bewertung-text"><?= nl2br(Util::e((string) $zeile['text'])) ?></p>

        <form method="post" class="bk-bewertung-aktionen">
          <?= Auth::csrfFeld() ?>
          <input type="hidden" name="id" value="<?= (int) $zeile['id'] ?>">
          <input type="hidden" name="filter" value="<?= Util::e($filter) ?>">

          <?php if ((string) $zeile['status'] !== 'frei'): ?>
            <button class="bk-knopf bk-knopf-voll bk-knopf-klein" type="submit" name="aktion" value="frei">Freigeben</button>
          <?php endif; ?>
          <?php if ((string) $zeile['status'] !== 'versteckt'): ?>
            <button class="bk-knopf bk-knopf-klein" type="submit" name="aktion" value="versteckt"
                    data-frage="Nur bei Beleidigung, Rechtswidrigkeit oder Spam ausblenden. Wirklich verstecken?">Verstecken</button>
          <?php endif; ?>
          <button class="bk-knopf bk-knopf-klein bk-knopf-leer bk-knopf-rot" type="submit" name="aktion" value="loeschen"
                  data-frage="Endgültig löschen? Das lässt sich nicht rückgängig machen.">Löschen</button>
        </form>

        <details class="bk-bewertung-antwort" <?= trim((string) $zeile['antwort']) !== '' ? 'open' : '' ?>>
          <summary><?= trim((string) $zeile['antwort']) !== '' ? 'Antwort bearbeiten' : 'Öffentlich antworten' ?></summary>
          <form method="post">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="id" value="<?= (int) $zeile['id'] ?>">
            <input type="hidden" name="filter" value="<?= Util::e($filter) ?>">
            <div class="bk-feld">
              <textarea name="antwort" rows="3"
                        placeholder="Sachlich, kurz, ohne Rechtfertigung. Eine gute Antwort auf eine schlechte Bewertung wirkt oft besser als zehn gute."><?= Util::e((string) $zeile['antwort']) ?></textarea>
              <div class="bk-tipp">Die Antwort steht öffentlich unter der Bewertung. Leer speichern entfernt sie wieder.</div>
            </div>
            <button class="bk-knopf bk-knopf-klein" type="submit" name="aktion" value="antwort">Antwort speichern</button>
          </form>
        </details>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="bk-karte">
  <div class="bk-karte-kopf"><h2>Einstellungen</h2></div>
  <div class="bk-karte-inhalt">
    <form method="post">
      <?= Auth::csrfFeld() ?>
      <input type="hidden" name="filter" value="<?= Util::e($filter) ?>">
      <label class="bk-haken">
        <input type="checkbox" name="bewertungen_an" value="1" <?= $angezeigt ? 'checked' : '' ?>>
        <span>Bewertungen im Shop zeigen und annehmen</span>
      </label>
      <div class="bk-tipp" style="margin-left:25px">
        Wirkt sofort – anders als Artikel und Seiten muss dafür nichts veröffentlicht werden.
      </div>

      <div class="bk-feld" style="margin-top:16px">
        <label for="bewertungen_freigabe">Neue Bewertungen</label>
        <select id="bewertungen_freigabe" name="bewertungen_freigabe">
          <option value="manuell" <?= $freigabe === 'manuell' ? 'selected' : '' ?>>
            erst nach meiner Freigabe zeigen (empfohlen)
          </option>
          <option value="sofort" <?= $freigabe === 'sofort' ? 'selected' : '' ?>>
            sofort zeigen
          </option>
        </select>
        <div class="bk-tipp">
          „Sofort“ spart Arbeit, lässt aber auch Spam durch. Bei „nach Freigabe“ steht die
          Zahl offener Bewertungen links in der Navigation.
        </div>
      </div>

      <button class="bk-knopf bk-knopf-voll" type="submit" name="aktion" value="einstellungen">Speichern</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
