<?php
/** Artikelliste mit Filtern und Massenaktionen. */

$seitentitel = 'Artikel';
require __DIR__ . '/partials/header.php';

/* --- Massenaktionen ------------------------------------------------------- */

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    $ids = array_values(array_filter(array_map('intval', Util::postArray('ids'))));
    $was = Util::post('massenaktion');

    if ($ids !== [] && $was !== '') {
        DB::transaction(static function () use ($ids, $was): void {
            foreach ($ids as $id) {
                if ($was === 'loeschen') {
                    Artikel::loeschen($id);
                } elseif (in_array($was, ['aktiv', 'entwurf', 'archiv'], true)) {
                    Artikel::statusSetzen($id, $was);
                }
            }
        });
        Util::redirect('artikel.php?meldung=' . rawurlencode(count($ids) . ' Artikel aktualisiert.'));
    }
    Util::redirect('artikel.php');
}

$status     = Util::einesVon(Util::get('status'), ['', 'aktiv', 'entwurf', 'archiv'], '');
$suche      = Util::get('suche');
$sortierung = Util::einesVon(Util::get('sortierung', 'geaendert'),
    ['geaendert', 'neueste', 'titel', 'preis-auf', 'preis-ab'], 'geaendert');
$seite      = max(1, Util::getInt('s', 1));
$proSeite   = 50;

$ergebnis = Artikel::liste([
    'status'     => $status,
    'suche'      => $suche,
    'sortierung' => $sortierung,
    'limit'      => $proSeite,
    'offset'     => ($seite - 1) * $proSeite,
]);

$reiter = ['' => 'Alle', 'aktiv' => 'Aktiv', 'entwurf' => 'Entwürfe', 'archiv' => 'Archiv'];
$grundlink = static fn(array $zusatz = []): string =>
    'artikel.php?' . http_build_query(array_merge(
        ['status' => $status, 'suche' => $suche, 'sortierung' => $sortierung], $zusatz
    ));
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Artikel</h1>
    <div class="bk-untertitel"><?= (int) $ergebnis['gesamt'] ?> Artikel im Katalog</div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf" href="export.php?<?= http_build_query(['was' => 'artikel', 'status' => $status, 'suche' => $suche]) ?>">CSV exportieren</a>
    <a class="bk-knopf bk-knopf-voll" href="artikel-bearbeiten.php">Artikel anlegen</a>
  </div>
</div>

<form method="post" class="bk-karte">
  <?= Auth::csrfFeld() ?>

  <div class="bk-reiter">
    <?php foreach ($reiter as $wert => $label): ?>
      <a href="<?= Util::e($grundlink(['status' => $wert, 's' => 1])) ?>"
         class="<?= $status === $wert ? 'aktiv' : '' ?>"><?= Util::e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="bk-werkzeug">
    <input type="search" name="suche" form="filter" placeholder="Nach Titel oder Artikelnummer suchen …"
           value="<?= Util::e($suche) ?>" data-auto-suche>
    <select name="sortierung" form="filter" onchange="this.form.submit()">
      <?php foreach ([
        'geaendert' => 'Zuletzt bearbeitet', 'neueste' => 'Neueste zuerst', 'titel' => 'Titel A–Z',
        'preis-auf' => 'Preis aufsteigend', 'preis-ab' => 'Preis absteigend',
      ] as $wert => $label): ?>
        <option value="<?= $wert ?>" <?= $sortierung === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="bk-knopf bk-knopf-klein" type="submit" form="filter">Anwenden</button></noscript>
  </div>

  <div class="bk-karte-inhalt eng">
    <?php if ($ergebnis['zeilen'] === []): ?>
      <div class="bk-leer">
        <h3><?= $suche !== '' ? 'Keine Treffer' : 'Noch keine Artikel' ?></h3>
        <p><?= $suche !== ''
            ? 'Versuch es mit einem anderen Suchbegriff.'
            : 'Lege deinen ersten Artikel an – Titel und Preis genügen zum Start.' ?></p>
        <a class="bk-knopf bk-knopf-voll" href="artikel-bearbeiten.php">Artikel anlegen</a>
      </div>
    <?php else: ?>
      <div class="bk-tabelle-rahmen"><table>
        <thead><tr>
          <th style="width:34px"><input type="checkbox" onclick="this.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(b=>b.checked=this.checked)"></th>
          <th class="bk-bild-zelle"></th><th>Artikel</th><th>Status</th>
          <th class="bk-zahl-rechts">Bestand</th><th class="bk-zahl-rechts">Preis</th><th>Typ</th>
        </tr></thead>
        <tbody>
          <?php foreach ($ergebnis['zeilen'] as $artikel): ?>
            <?php
            $statusKlasse = match ((string) $artikel['status']) {
                'aktiv'  => 'bk-marke-gruen',
                'archiv' => 'bk-marke-rot',
                default  => '',
            };
            ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $artikel['id'] ?>"></td>
              <td class="bk-bild-zelle">
                <?php if ((string) $artikel['bild_url'] !== ''): ?>
                  <img class="bk-mini" src="<?= Util::e(Theme::url((string) $artikel['bild_url'])) ?>" alt="">
                <?php else: ?>
                  <div class="bk-mini bk-mini-leer">▦</div>
                <?php endif; ?>
              </td>
              <td class="bk-klick" onclick="location='artikel-bearbeiten.php?id=<?= (int) $artikel['id'] ?>'" style="cursor:pointer">
                <div class="bk-haupt"><?= Util::e((string) $artikel['titel']) ?></div>
                <div class="bk-neben"><?php
                  echo (int) $artikel['varianten_anzahl'] > 1
                      ? (int) $artikel['varianten_anzahl'] . ' Varianten'
                      : Util::e((string) ($artikel['hersteller'] ?: '—'));
                ?></div>
              </td>
              <td><span class="bk-marke <?= $statusKlasse ?>"><i></i><?= Util::e(Artikel::STATUS[(string) $artikel['status']] ?? '') ?></span></td>
              <td class="bk-zahl-rechts"><?= (int) $artikel['bestand_gesamt'] ?></td>
              <td class="bk-zahl-rechts"><?php
                echo (int) $artikel['preis_min'] === (int) $artikel['preis_max']
                    ? Util::e(Util::geld((int) $artikel['preis_min']))
                    : Util::e(Util::geld((int) $artikel['preis_min']) . '–' . Util::geld((int) $artikel['preis_max']));
              ?></td>
              <td class="bk-neben"><?= Util::e((string) ($artikel['typ'] ?: '—')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php if ($ergebnis['zeilen'] !== [] && Auth::darf('pflegen')): ?>
    <div class="bk-karte-fuss" style="justify-content:flex-start">
      <span class="bk-neben">Markierte Artikel:</span>
      <button class="bk-knopf bk-knopf-klein" type="submit" name="massenaktion" value="aktiv">Aktivieren</button>
      <button class="bk-knopf bk-knopf-klein" type="submit" name="massenaktion" value="entwurf">Auf Entwurf</button>
      <button class="bk-knopf bk-knopf-klein" type="submit" name="massenaktion" value="archiv">Archivieren</button>
      <button class="bk-knopf bk-knopf-klein bk-knopf-rot" type="submit" name="massenaktion" value="loeschen"
              data-frage="Markierte Artikel wirklich löschen? Bestehende Bestellungen bleiben unverändert."
              formnovalidate>Löschen</button>
    </div>
  <?php endif; ?>

  <?php
  $seiten = max(1, (int) ceil($ergebnis['gesamt'] / $proSeite));
  if ($seiten > 1): ?>
    <div class="bk-blaettern">
      <span><?= ($seite - 1) * $proSeite + 1 ?>–<?= min($seite * $proSeite, (int) $ergebnis['gesamt']) ?>
        von <?= (int) $ergebnis['gesamt'] ?></span>
      <span class="bk-knopfgruppe">
        <?php if ($seite > 1): ?>
          <a class="bk-knopf bk-knopf-klein" href="<?= Util::e($grundlink(['s' => $seite - 1])) ?>">Zurück</a>
        <?php endif; ?>
        <?php if ($seite < $seiten): ?>
          <a class="bk-knopf bk-knopf-klein" href="<?= Util::e($grundlink(['s' => $seite + 1])) ?>">Weiter</a>
        <?php endif; ?>
      </span>
    </div>
  <?php endif; ?>
</form>

<form id="filter" method="get">
  <input type="hidden" name="status" value="<?= Util::e($status) ?>">
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
