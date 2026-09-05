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

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Artikel</h1>
    <div class="ad-untertitel"><?= (int) $ergebnis['gesamt'] ?> Artikel im Katalog</div>
  </div>
  <div class="ad-aktionen">
    <a class="ad-knopf" href="export.php?<?= http_build_query(['was' => 'artikel', 'status' => $status, 'suche' => $suche]) ?>">CSV exportieren</a>
    <a class="ad-knopf ad-knopf-voll" href="artikel-bearbeiten.php">Artikel anlegen</a>
  </div>
</div>

<form method="post" class="ad-karte">
  <?= Auth::csrfFeld() ?>

  <div class="ad-reiter">
    <?php foreach ($reiter as $wert => $label): ?>
      <a href="<?= Util::e($grundlink(['status' => $wert, 's' => 1])) ?>"
         class="<?= $status === $wert ? 'aktiv' : '' ?>"><?= Util::e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="ad-werkzeug">
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
    <noscript><button class="ad-knopf ad-knopf-klein" type="submit" form="filter">Anwenden</button></noscript>
  </div>

  <div class="ad-karte-inhalt eng">
    <?php if ($ergebnis['zeilen'] === []): ?>
      <div class="ad-leer">
        <h3><?= $suche !== '' ? 'Keine Treffer' : 'Noch keine Artikel' ?></h3>
        <p><?= $suche !== ''
            ? 'Versuch es mit einem anderen Suchbegriff.'
            : 'Lege deinen ersten Artikel an – Titel und Preis genügen zum Start.' ?></p>
        <a class="ad-knopf ad-knopf-voll" href="artikel-bearbeiten.php">Artikel anlegen</a>
      </div>
    <?php else: ?>
      <div class="ad-tabelle-rahmen"><table>
        <thead><tr>
          <th style="width:34px"><input type="checkbox" onclick="this.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(b=>b.checked=this.checked)"></th>
          <th class="ad-bild-zelle"></th><th>Artikel</th><th>Status</th>
          <th class="ad-zahl-rechts">Bestand</th><th class="ad-zahl-rechts">Preis</th><th>Typ</th>
        </tr></thead>
        <tbody>
          <?php foreach ($ergebnis['zeilen'] as $artikel): ?>
            <?php
            $statusKlasse = match ((string) $artikel['status']) {
                'aktiv'  => 'ad-marke-gruen',
                'archiv' => 'ad-marke-rot',
                default  => '',
            };
            ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $artikel['id'] ?>"></td>
              <td class="ad-bild-zelle">
                <?php if ((string) $artikel['bild_url'] !== ''): ?>
                  <img class="ad-mini" src="<?= Util::e(Theme::url((string) $artikel['bild_url'])) ?>" alt="">
                <?php else: ?>
                  <div class="ad-mini ad-mini-leer">▦</div>
                <?php endif; ?>
              </td>
              <td class="ad-klick" onclick="location='artikel-bearbeiten.php?id=<?= (int) $artikel['id'] ?>'" style="cursor:pointer">
                <div class="ad-haupt"><?= Util::e((string) $artikel['titel']) ?></div>
                <div class="ad-neben"><?php
                  echo (int) $artikel['varianten_anzahl'] > 1
                      ? (int) $artikel['varianten_anzahl'] . ' Varianten'
                      : Util::e((string) ($artikel['hersteller'] ?: '—'));
                ?></div>
              </td>
              <td><span class="ad-marke <?= $statusKlasse ?>"><i></i><?= Util::e(Artikel::STATUS[(string) $artikel['status']] ?? '') ?></span></td>
              <td class="ad-zahl-rechts"><?= (int) $artikel['bestand_gesamt'] ?></td>
              <td class="ad-zahl-rechts"><?php
                echo (int) $artikel['preis_min'] === (int) $artikel['preis_max']
                    ? Util::e(Util::geld((int) $artikel['preis_min']))
                    : Util::e(Util::geld((int) $artikel['preis_min']) . '–' . Util::geld((int) $artikel['preis_max']));
              ?></td>
              <td class="ad-neben"><?= Util::e((string) ($artikel['typ'] ?: '—')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php if ($ergebnis['zeilen'] !== [] && Auth::darf('pflegen')): ?>
    <div class="ad-karte-fuss" style="justify-content:flex-start">
      <span class="ad-neben">Markierte Artikel:</span>
      <button class="ad-knopf ad-knopf-klein" type="submit" name="massenaktion" value="aktiv">Aktivieren</button>
      <button class="ad-knopf ad-knopf-klein" type="submit" name="massenaktion" value="entwurf">Auf Entwurf</button>
      <button class="ad-knopf ad-knopf-klein" type="submit" name="massenaktion" value="archiv">Archivieren</button>
      <button class="ad-knopf ad-knopf-klein ad-knopf-rot" type="submit" name="massenaktion" value="loeschen"
              data-frage="Markierte Artikel wirklich löschen? Bestehende Bestellungen bleiben unverändert."
              formnovalidate>Löschen</button>
    </div>
  <?php endif; ?>

  <?php
  $seiten = max(1, (int) ceil($ergebnis['gesamt'] / $proSeite));
  if ($seiten > 1): ?>
    <div class="ad-blaettern">
      <span><?= ($seite - 1) * $proSeite + 1 ?>–<?= min($seite * $proSeite, (int) $ergebnis['gesamt']) ?>
        von <?= (int) $ergebnis['gesamt'] ?></span>
      <span class="ad-knopfgruppe">
        <?php if ($seite > 1): ?>
          <a class="ad-knopf ad-knopf-klein" href="<?= Util::e($grundlink(['s' => $seite - 1])) ?>">Zurück</a>
        <?php endif; ?>
        <?php if ($seite < $seiten): ?>
          <a class="ad-knopf ad-knopf-klein" href="<?= Util::e($grundlink(['s' => $seite + 1])) ?>">Weiter</a>
        <?php endif; ?>
      </span>
    </div>
  <?php endif; ?>
</form>

<form id="filter" method="get">
  <input type="hidden" name="status" value="<?= Util::e($status) ?>">
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
