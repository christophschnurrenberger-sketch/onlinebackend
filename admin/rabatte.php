<?php
/** Rabattcodes – Liste und Bearbeitung in einer Maske. */

$seitentitel = 'Rabatte';
require __DIR__ . '/partials/header.php';

$id      = Util::getInt('id');
$anlegen = Util::get('neu') !== '';

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    try {
        if (Util::post('aktion') === 'loeschen') {
            Rabatte::loeschen($id);
            Util::redirect('rabatte.php?meldung=' . rawurlencode('Rabattcode gelöscht.'));
        }
        $daten = [
            'code'             => Util::post('code'),
            'name'             => Util::post('name'),
            'art'              => Util::post('art'),
            'wert'             => Util::post('wert'),
            'mindestwert'      => Util::post('mindestwert'),
            'max_nutzungen'    => Util::post('max_nutzungen'),
            'einmal_pro_kunde' => Util::postBool('einmal_pro_kunde'),
            'gilt_ab'          => Util::post('gilt_ab'),
            'gilt_bis'         => Util::post('gilt_bis'),
            'aktiv'            => Util::postBool('aktiv'),
        ];
        if ($id > 0) {
            Rabatte::speichern($id, $daten);
        } else {
            $id = Rabatte::anlegen($daten);
        }
        Util::redirect('rabatte.php?meldung=' . rawurlencode('Rabattcode gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
        $anlegen = true;
    }
}

$rabatte = Rabatte::liste(Util::get('suche'));
$aktuell = $id > 0 ? Rabatte::holen($id) : null;
$maske   = $anlegen || $aktuell !== null;

$vorgabe = [
    'code' => '', 'name' => '', 'art' => 'prozent', 'wert' => 0, 'mindestwert' => 0,
    'max_nutzungen' => null, 'einmal_pro_kunde' => 0, 'gilt_ab' => null, 'gilt_bis' => null, 'aktiv' => 1,
];
$werte = $aktuell ?? $vorgabe;

/** Prozentwerte liegen als Hundertstel vor: 1250 → "12,5". */
$wertFeld = (string) $werte['art'] === 'prozent'
    ? rtrim(rtrim(number_format((int) $werte['wert'] / 100, 2, ',', ''), '0'), ',')
    : Util::geldFeld((int) $werte['wert']);
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Rabatte</h1>
    <div class="bk-untertitel"><?= count($rabatte) ?> Codes</div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf bk-knopf-voll" href="rabatte.php?neu=1">Rabattcode anlegen</a>
  </div>
</div>

<?php if ($maske): ?>
  <form method="post" class="bk-karte">
    <?= Auth::csrfFeld() ?>
    <div class="bk-karte-kopf">
      <h2><?= $aktuell !== null ? 'Code ' . Util::e((string) $werte['code']) : 'Neuer Rabattcode' ?></h2>
      <a class="bk-knopf bk-knopf-klein bk-knopf-leer" href="rabatte.php">Abbrechen</a>
    </div>
    <div class="bk-karte-inhalt">
      <div class="bk-feldzeile">
        <div class="bk-feld">
          <label for="code">Code</label>
          <input type="text" id="code" name="code" required value="<?= Util::e((string) $werte['code']) ?>"
                 placeholder="SOMMER25" style="text-transform:uppercase">
          <div class="bk-tipp">Wird automatisch großgeschrieben.</div>
        </div>
        <div class="bk-feld">
          <label for="name">Interner Name</label>
          <input type="text" id="name" name="name" value="<?= Util::e((string) $werte['name']) ?>"
                 placeholder="Sommeraktion">
        </div>
      </div>
      <div class="bk-feldzeile">
        <div class="bk-feld">
          <label for="art">Art</label>
          <select id="art" name="art">
            <?php foreach (Rabatte::ARTEN as $wert => $label): ?>
              <option value="<?= Util::e($wert) ?>" <?= (string) $werte['art'] === $wert ? 'selected' : '' ?>>
                <?= Util::e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bk-feld">
          <label for="wert">Wert</label>
          <input type="text" id="wert" name="wert" value="<?= Util::e($wertFeld) ?>" inputmode="decimal">
          <div class="bk-tipp">Prozent (z. B. 10) oder Betrag in Euro. Bei Gratisversand egal.</div>
        </div>
      </div>
      <div class="bk-feldzeile">
        <div class="bk-feld">
          <label for="mindestwert">Mindestbestellwert</label>
          <div class="bk-euro"><input type="text" id="mindestwert" name="mindestwert"
                 value="<?= Util::e(Util::geldFeld((int) $werte['mindestwert'])) ?>" inputmode="decimal"></div>
        </div>
        <div class="bk-feld">
          <label for="max_nutzungen">Maximale Einlösungen</label>
          <input type="number" id="max_nutzungen" name="max_nutzungen" min="0"
                 value="<?= $werte['max_nutzungen'] !== null ? (int) $werte['max_nutzungen'] : '' ?>">
          <div class="bk-tipp">Leer = unbegrenzt</div>
        </div>
      </div>
      <div class="bk-feldzeile">
        <div class="bk-feld">
          <label for="gilt_ab">Gültig ab</label>
          <input type="date" id="gilt_ab" name="gilt_ab"
                 value="<?= Util::e(substr((string) ($werte['gilt_ab'] ?? ''), 0, 10)) ?>">
        </div>
        <div class="bk-feld">
          <label for="gilt_bis">Gültig bis</label>
          <input type="date" id="gilt_bis" name="gilt_bis"
                 value="<?= Util::e(substr((string) ($werte['gilt_bis'] ?? ''), 0, 10)) ?>">
        </div>
      </div>
      <label class="bk-haken">
        <input type="checkbox" name="einmal_pro_kunde" value="1" <?= (int) $werte['einmal_pro_kunde'] === 1 ? 'checked' : '' ?>>
        <span>Nur einmal pro Kunde einlösbar</span>
      </label>
      <label class="bk-haken" style="margin:0">
        <input type="checkbox" name="aktiv" value="1" <?= (int) $werte['aktiv'] === 1 ? 'checked' : '' ?>>
        <span>Aktiv</span>
      </label>
    </div>
    <div class="bk-karte-fuss">
      <?php if ($aktuell !== null): ?>
        <button class="bk-knopf bk-knopf-rot" type="submit" name="aktion" value="loeschen" formnovalidate
                style="margin-right:auto"
                onclick="return confirm('Diesen Rabattcode löschen?')">Löschen</button>
      <?php endif; ?>
      <button class="bk-knopf bk-knopf-voll" type="submit">Speichern</button>
    </div>
  </form>
<?php endif; ?>

<section class="bk-karte">
  <form class="bk-werkzeug" method="get">
    <input type="search" name="suche" placeholder="Code suchen …" value="<?= Util::e(Util::get('suche')) ?>" data-auto-suche>
    <noscript><button class="bk-knopf bk-knopf-klein" type="submit">Suchen</button></noscript>
  </form>
  <div class="bk-karte-inhalt eng">
    <?php if ($rabatte === []): ?>
      <div class="bk-leer">
        <h3>Keine Rabattcodes</h3>
        <p>Rabattcodes können Kunden im Warenkorb einlösen.</p>
        <a class="bk-knopf bk-knopf-voll" href="rabatte.php?neu=1">Rabattcode anlegen</a>
      </div>
    <?php else: ?>
      <div class="bk-tabelle-rahmen"><table>
        <thead><tr><th>Code</th><th>Art</th><th>Wert</th><th>Bedingung</th>
          <th class="bk-zahl-rechts">Eingelöst</th><th>Gültig bis</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($rabatte as $rabatt): ?>
            <tr class="bk-klick" style="cursor:pointer" onclick="location='rabatte.php?id=<?= (int) $rabatt['id'] ?>'">
              <td>
                <strong style="font-family:var(--mono)"><?= Util::e((string) $rabatt['code']) ?></strong>
                <?php if ((string) $rabatt['name'] !== ''): ?>
                  <div class="bk-neben"><?= Util::e((string) $rabatt['name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="bk-neben"><?= Util::e(Rabatte::ARTEN[(string) $rabatt['art']] ?? '') ?></td>
              <td><?= Util::e(Rabatte::wertText($rabatt)) ?></td>
              <td class="bk-neben">
                <?= (int) $rabatt['mindestwert'] > 0 ? 'ab ' . Util::e(Util::geld((int) $rabatt['mindestwert'])) : '—' ?>
                <?php if ((int) $rabatt['einmal_pro_kunde'] === 1): ?><br>1× pro Kunde<?php endif; ?>
              </td>
              <td class="bk-zahl-rechts">
                <?= (int) $rabatt['genutzt'] ?><?php
                  if ($rabatt['max_nutzungen'] !== null) {
                      echo ' / ' . (int) $rabatt['max_nutzungen'];
                  }
                ?>
              </td>
              <td class="bk-neben"><?= $rabatt['gilt_bis'] !== null
                  ? Util::e(Util::dt((string) $rabatt['gilt_bis'], 'd.m.Y')) : 'unbefristet' ?></td>
              <td>
                <?php if ((int) $rabatt['aktiv'] === 1): ?>
                  <span class="bk-marke bk-marke-gruen"><i></i>Aktiv</span>
                <?php else: ?>
                  <span class="bk-marke"><i></i>Inaktiv</span>
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
