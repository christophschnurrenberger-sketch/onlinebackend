<?php
/**
 * Design.
 *
 * Alles hier landet als CSS-Variable im Kopf jeder Shopseite. Das ist die
 * Stelle, an der das generische Aussehen zum eigenen wird – und der Grund,
 * warum ein späterer Theme-Umbau die Templates nicht anfassen muss.
 */

$seitentitel = 'Design';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

/*
 * Die fertigen Stile stehen in lib/Theme.php – dort werden sie auch beim
 * Ausliefern der Shopseiten gebraucht (für die Stildatei). Eine Liste, zwei
 * Verwender: so kann nichts auseinanderlaufen.
 */
$stile = Theme::STILE;

if (Util::isPost()) {
    Auth::csrfPruefen();

    $werte = [];
    $vorlage = Util::post('design_vorlage');

    if (Util::post('aktion') === 'vorlage' && isset($stile[$vorlage])) {
        $werte = $stile[$vorlage]['werte'];
        $werte['design_vorlage'] = $vorlage;
        Settings::setMany($werte);
        Util::redirect('design.php?meldung=' . rawurlencode(
            'Stil „' . $stile[$vorlage]['name'] . '“ übernommen. Zum Sichtbarwerden bitte veröffentlichen.'));
    }

    foreach ([
        'farbe_hintergrund', 'farbe_flaeche', 'farbe_text', 'farbe_nebentext', 'farbe_rahmen',
        'farbe_knopf', 'farbe_knopf_text', 'farbe_akzent', 'farbe_sale',
        'schrift_titel', 'schrift_text', 'ecken', 'inhaltsbreite', 'artikel_pro_reihe',
        'hinweisleiste', 'start_titel', 'start_text', 'start_bild', 'start_knopf',
        'start_knopf_url', 'fusszeile_text', 'servicezeile', 'stempel_text', 'stempel_ort',
        'vorteil_1', 'vorteil_2', 'vorteil_3', 'vorteil_4',
    ] as $feld) {
        $werte[$feld] = Util::post($feld);
    }
    $werte['eigenes_css']         = Util::postRaw('eigenes_css');
    $werte['hinweisleiste_an']    = Util::postBool('hinweisleiste_an') ? '1' : '0';
    $werte['servicezeile_an']     = Util::postBool('servicezeile_an') ? '1' : '0';
    $werte['vorteile_an']         = Util::postBool('vorteile_an') ? '1' : '0';
    $werte['hersteller_zeigen']   = Util::postBool('hersteller_zeigen') ? '1' : '0';
    $werte['streichpreis_zeigen'] = Util::postBool('streichpreis_zeigen') ? '1' : '0';
    $werte['design_vorlage']      = $vorlage;

    Settings::setMany($werte);
    Util::redirect('design.php?meldung=' . rawurlencode('Design gespeichert. Zum Sichtbarwerden bitte veröffentlichen.'));
}

$e = static fn(string $k): string => Settings::get($k);

$schriften = [
    Theme::SCHRIFT_SYSTEM    => 'System (serifenlos)',
    Theme::SCHRIFT_HELVETICA => 'Helvetica',
    Theme::SCHRIFT_SCHMAL    => 'Arial Narrow (schmal)',
    Theme::SCHRIFT_QUELLE    => 'Source Sans 3 (mitgeliefert)',
    Theme::SCHRIFT_PETRONA   => 'Petrona (Serif, mitgeliefert)',
    Theme::SCHRIFT_CABIN     => 'Cabin (Grotesk, mitgeliefert)',
    Theme::SCHRIFT_HUMANIST  => 'Avenir / Segoe (humanistisch)',
    Theme::SCHRIFT_GEORGIA   => 'Georgia (Serif)',
    Theme::SCHRIFT_PALATINO  => 'Palatino (Serif)',
    Theme::SCHRIFT_MONO      => 'Monospace',
];

$farben = [
    'farbe_hintergrund' => 'Hintergrund', 'farbe_flaeche' => 'Flächen',
    'farbe_text' => 'Text', 'farbe_nebentext' => 'Nebentext',
    'farbe_rahmen' => 'Rahmen', 'farbe_akzent' => 'Akzent',
    'farbe_knopf' => 'Buttons', 'farbe_knopf_text' => 'Button-Schrift',
    'farbe_sale' => 'Sale-Preis',
];
?>

<div class="bk-seitenkopf">
  <div class="bk-titel">
    <h1>Design</h1>
    <div class="bk-untertitel">Farben, Schriften und Startseite des Shops</div>
  </div>
  <div class="bk-aktionen">
    <a class="bk-knopf" href="<?= Util::e(Config::baseUrl()) ?>/" target="_blank" rel="noopener">Shop ansehen ↗</a>
    <button class="bk-knopf bk-knopf-voll" type="submit" form="designform">Speichern</button>
  </div>
</div>

<div class="bk-hinweis bk-hinweis-info">
  <strong>So funktioniert das Design</strong>
  Diese Werte werden als CSS-Variablen in jede Shopseite geschrieben. Die Branchenstile laden
  zusätzlich eine Datei aus <code>assets/stile/</code>, die Abstände, Rahmen und Versalien mitbringt –
  die Seitenstruktur bleibt dabei unverändert. Änderungen werden im Shop erst nach dem
  Veröffentlichen sichtbar.
</div>

<?php
/** Zeichnet eine Auswahlkachel mit Farbprobe. */
$stilkachel = static function (string $schluessel, array $stil, string $aktuell): void {
    $w = $stil['werte'];
    $an = $schluessel === $aktuell;
    ?>
    <label class="bk-stil<?= $an ? ' ist-aktiv' : '' ?>">
      <input type="radio" name="design_vorlage" value="<?= Util::e($schluessel) ?>" <?= $an ? 'checked' : '' ?>>
      <span class="bk-stil-probe" style="background:<?= Util::e($w['farbe_hintergrund']) ?>;
            border-color:<?= Util::e($w['farbe_rahmen']) ?>">
        <span class="bk-stil-kopf" style="background:<?= Util::e($w['farbe_knopf']) ?>"></span>
        <span class="bk-stil-titel" style="font-family:<?= Util::e($w['schrift_titel']) ?>;
              color:<?= Util::e($w['farbe_text']) ?>">Aa</span>
        <span class="bk-stil-knopf" style="background:<?= Util::e($w['farbe_knopf']) ?>;
              color:<?= Util::e($w['farbe_knopf_text']) ?>;border-radius:<?= Util::e($w['ecken']) ?>"></span>
        <span class="bk-stil-punkt" style="background:<?= Util::e($w['farbe_akzent']) ?>"></span>
        <span class="bk-stil-flaeche" style="background:<?= Util::e($w['farbe_flaeche']) ?>"></span>
      </span>
      <span class="bk-stil-name"><?= Util::e($stil['name']) ?>
        <?php if ($stil['datei'] !== ''): ?><em>eigene Stildatei</em><?php endif; ?>
      </span>
      <span class="bk-stil-text"><?= Util::e($stil['text']) ?></span>
    </label>
    <?php
};
$aktuellerStil = $e('design_vorlage') !== '' ? $e('design_vorlage') : 'basis';
?>

<form method="post" style="margin-bottom:16px">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="aktion" value="vorlage">
  <section class="bk-karte">
    <div class="bk-karte-kopf">
      <h2>Shop-Stil</h2>
      <button class="bk-knopf bk-knopf-voll" type="submit">Ausgewählten Stil übernehmen</button>
    </div>
    <div class="bk-karte-inhalt">
      <p class="bk-tipp" style="margin-top:0">
        Ein Stil setzt Farben, Schriften, Ecken und Rasterbreite auf einen Schlag – die Branchenstile
        bringen zusätzlich eine eigene Stildatei mit, die Abstände, Rahmen und Versalien mitbringt.
        <strong>Achtung:</strong> Beim Übernehmen werden die Farben und Schriften unten überschrieben.
        Danach kannst du alles einzeln nachjustieren.
      </p>
      <h3 class="bk-stil-gruppe">Für eine Branche gebaut</h3>
      <div class="bk-stil-raster">
        <?php foreach ($stile as $schluessel => $stil): ?>
          <?php if ($stil['datei'] !== '') { $stilkachel($schluessel, $stil, $aktuellerStil); } ?>
        <?php endforeach; ?>
      </div>
      <h3 class="bk-stil-gruppe">Neutrale Farbschemata</h3>
      <div class="bk-stil-raster">
        <?php foreach ($stile as $schluessel => $stil): ?>
          <?php if ($stil['datei'] === '') { $stilkachel($schluessel, $stil, $aktuellerStil); } ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</form>

<form id="designform" method="post" class="bk-zwei">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="design_vorlage" value="<?= Util::e($e('design_vorlage')) ?>">

  <div>
    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Farben</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feldzeile">
          <?php foreach ($farben as $feld => $label): ?>
            <div class="bk-feld">
              <label for="<?= $feld ?>"><?= Util::e($label) ?></label>
              <div class="bk-farbe">
                <input type="color" value="<?= Util::e(preg_match('/^#[0-9a-f]{6}$/i', $e($feld)) ? $e($feld) : '#000000') ?>">
                <input type="text" id="<?= $feld ?>" name="<?= $feld ?>" value="<?= Util::e($e($feld)) ?>">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Startseite</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld">
          <label for="start_titel">Überschrift</label>
          <input type="text" id="start_titel" name="start_titel" value="<?= Util::e($e('start_titel')) ?>">
        </div>
        <div class="bk-feld">
          <label for="start_text">Unterzeile</label>
          <input type="text" id="start_text" name="start_text" value="<?= Util::e($e('start_text')) ?>">
        </div>
        <div class="bk-feldzeile">
          <div class="bk-feld"><label for="stempel_text">Stempel: Text</label>
            <input type="text" id="stempel_text" name="stempel_text" value="<?= Util::e($e('stempel_text')) ?>"
                   placeholder="seit 1998">
            <div class="bk-tipp">Runder Stempel auf der Bühne. Leer lassen blendet ihn aus.</div></div>
          <div class="bk-feld"><label for="stempel_ort">Stempel: Ort</label>
            <input type="text" id="stempel_ort" name="stempel_ort" value="<?= Util::e($e('stempel_ort')) ?>"
                   placeholder="Brandenburg"></div>
        </div>
        <div class="bk-feld">
          <label for="start_bild">Hintergrundbild (Adresse)</label>
          <input type="text" id="start_bild" name="start_bild" value="<?= Util::e($e('start_bild')) ?>"
                 placeholder="uploads/buehne.jpg">
          <div class="bk-tipp">Leer lassen für eine einfarbige Fläche.</div>
        </div>
        <div class="bk-feldzeile">
          <div class="bk-feld"><label for="start_knopf">Buttontext</label>
            <input type="text" id="start_knopf" name="start_knopf" value="<?= Util::e($e('start_knopf')) ?>"></div>
          <div class="bk-feld"><label for="start_knopf_url">Buttonziel</label>
            <input type="text" id="start_knopf_url" name="start_knopf_url" value="<?= Util::e($e('start_knopf_url')) ?>"></div>
        </div>
      </div>
    </section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Ankündigungsleiste</h2></div>
      <div class="bk-karte-inhalt">
        <label class="bk-haken">
          <input type="checkbox" name="hinweisleiste_an" value="1" <?= Settings::bool('hinweisleiste_an') ? 'checked' : '' ?>>
          <span>Leiste über dem Kopf anzeigen</span>
        </label>
        <div class="bk-feld" style="margin:0">
          <label for="hinweisleiste">Text</label>
          <input type="text" id="hinweisleiste" name="hinweisleiste" value="<?= Util::e($e('hinweisleiste')) ?>"
                 placeholder="Versandkostenfrei ab 75 €">
        </div>
      </div>
    </section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Servicezeile &amp; Vorteile</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-hinweis bk-hinweis-warnung" style="margin:0 0 14px">
          Diese Texte stehen öffentlich im Shop. In Deutschland sind Werbeaussagen
          verbindlich – bitte nur hineinschreiben, was auch eingehalten wird.
        </div>
        <label class="bk-haken">
          <input type="checkbox" name="servicezeile_an" value="1" <?= Settings::bool('servicezeile_an') ? 'checked' : '' ?>>
          <span>Schmale Servicezeile ganz oben anzeigen</span>
        </label>
        <div class="bk-feld">
          <label for="servicezeile">Text links</label>
          <input type="text" id="servicezeile" name="servicezeile" value="<?= Util::e($e('servicezeile')) ?>"
                 placeholder="Kundenservice Mo–Fr 9–17 Uhr">
          <div class="bk-tipp">Rechts stehen automatisch Telefonnummer und Kontakt aus den Einstellungen.</div>
        </div>
        <label class="bk-haken">
          <input type="checkbox" name="vorteile_an" value="1" <?= Settings::bool('vorteile_an') ? 'checked' : '' ?>>
          <span>Vorteilsleiste unter dem Kopf anzeigen</span>
        </label>
        <div class="bk-feldzeile">
          <div class="bk-feld"><label for="vorteil_1">Vorteil 1</label>
            <input type="text" id="vorteil_1" name="vorteil_1" value="<?= Util::e($e('vorteil_1')) ?>"
                   placeholder="Versandkostenfrei ab 50 €"></div>
          <div class="bk-feld"><label for="vorteil_2">Vorteil 2</label>
            <input type="text" id="vorteil_2" name="vorteil_2" value="<?= Util::e($e('vorteil_2')) ?>"></div>
        </div>
        <div class="bk-feldzeile">
          <div class="bk-feld"><label for="vorteil_3">Vorteil 3</label>
            <input type="text" id="vorteil_3" name="vorteil_3" value="<?= Util::e($e('vorteil_3')) ?>"></div>
          <div class="bk-feld" style="margin:0"><label for="vorteil_4">Vorteil 4</label>
            <input type="text" id="vorteil_4" name="vorteil_4" value="<?= Util::e($e('vorteil_4')) ?>"></div>
        </div>
      </div>
    </section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Eigenes CSS</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld" style="margin:0">
          <label for="eigenes_css">Zusätzliche Regeln</label>
          <textarea id="eigenes_css" name="eigenes_css" rows="8" class="bk-code"><?= Util::e($e('eigenes_css')) ?></textarea>
          <div class="bk-tipp">Wird nach dem Basis-Stylesheet eingebunden und überschreibt es damit.</div>
        </div>
      </div>
    </section>
  </div>

  <div>
    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Typografie &amp; Raster</h2></div>
      <div class="bk-karte-inhalt">
        <div class="bk-feld">
          <label for="schrift_titel">Überschriften</label>
          <select id="schrift_titel" name="schrift_titel">
            <?php foreach ($schriften as $wert => $label): ?>
              <option value="<?= Util::e($wert) ?>" <?= $e('schrift_titel') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bk-feld">
          <label for="schrift_text">Fließtext</label>
          <select id="schrift_text" name="schrift_text">
            <?php foreach ($schriften as $wert => $label): ?>
              <option value="<?= Util::e($wert) ?>" <?= $e('schrift_text') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bk-feld">
          <label for="ecken">Ecken</label>
          <select id="ecken" name="ecken">
            <?php foreach (['0px' => 'Kantig', '6px' => 'Leicht gerundet', '10px' => 'Gerundet', '18px' => 'Stark gerundet'] as $wert => $label): ?>
              <option value="<?= $wert ?>" <?= $e('ecken') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bk-feld">
          <label for="inhaltsbreite">Inhaltsbreite</label>
          <select id="inhaltsbreite" name="inhaltsbreite">
            <?php foreach (['1000px' => 'Schmal', '1200px' => 'Standard', '1400px' => 'Breit', '100%' => 'Volle Breite'] as $wert => $label): ?>
              <option value="<?= $wert ?>" <?= $e('inhaltsbreite') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?> (<?= $wert ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bk-feld" style="margin:0">
          <label for="artikel_pro_reihe">Artikel pro Reihe</label>
          <select id="artikel_pro_reihe" name="artikel_pro_reihe">
            <?php foreach (['2', '3', '4', '5'] as $wert): ?>
              <option value="<?= $wert ?>" <?= $e('artikel_pro_reihe') === $wert ? 'selected' : '' ?>><?= $wert ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Anzeigeoptionen</h2></div>
      <div class="bk-karte-inhalt">
        <label class="bk-haken">
          <input type="checkbox" name="hersteller_zeigen" value="1" <?= Settings::bool('hersteller_zeigen') ? 'checked' : '' ?>>
          <span>Hersteller in der Artikelliste zeigen</span>
        </label>
        <label class="bk-haken">
          <input type="checkbox" name="streichpreis_zeigen" value="1" <?= Settings::bool('streichpreis_zeigen') ? 'checked' : '' ?>>
          <span>Streichpreise und Sale-Kennzeichnung zeigen</span>
        </label>
        <div class="bk-feld" style="margin:0">
          <label for="fusszeile_text">Fußzeilentext</label>
          <input type="text" id="fusszeile_text" name="fusszeile_text" value="<?= Util::e($e('fusszeile_text')) ?>">
        </div>
      </div>
    </section>

    <section class="bk-karte">
      <div class="bk-karte-kopf"><h2>Vorschau</h2></div>
      <div class="bk-karte-inhalt">
        <?php
        $farbe = static fn(string $k): string => Util::e(preg_replace('/[;{}<>"]/', '', Settings::get($k)));
        ?>
        <div style="border:1px solid var(--rahmen);border-radius:8px;overflow:hidden;
                    background:<?= $farbe('farbe_hintergrund') ?>;color:<?= $farbe('farbe_text') ?>;
                    font-family:<?= $farbe('schrift_text') ?>">
          <?php if (Settings::bool('hinweisleiste_an')): ?>
            <div style="background:<?= $farbe('farbe_knopf') ?>;color:<?= $farbe('farbe_knopf_text') ?>;
                        padding:6px;text-align:center;font-size:11px">
              <?= Util::e($e('hinweisleiste') ?: 'Ankündigung') ?>
            </div>
          <?php endif; ?>
          <div style="padding:12px;border-bottom:1px solid <?= $farbe('farbe_rahmen') ?>;
                      font-family:<?= $farbe('schrift_titel') ?>;font-weight:700">
            <?= Util::e(Settings::get('shop_name')) ?>
          </div>
          <div style="background:<?= $farbe('farbe_flaeche') ?>;padding:18px 12px">
            <div style="font-family:<?= $farbe('schrift_titel') ?>;font-size:16px;font-weight:600;margin-bottom:4px">
              <?= Util::e($e('start_titel') ?: 'Überschrift') ?></div>
            <div style="font-size:12px;color:<?= $farbe('farbe_nebentext') ?>;margin-bottom:10px">
              <?= Util::e($e('start_text') ?: 'Unterzeile') ?></div>
            <span style="display:inline-block;background:<?= $farbe('farbe_knopf') ?>;
                         color:<?= $farbe('farbe_knopf_text') ?>;padding:6px 14px;
                         border-radius:<?= $farbe('ecken') ?>;font-size:12px">
              <?= Util::e($e('start_knopf') ?: 'Button') ?></span>
          </div>
          <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <?php for ($i = 0; $i < 2; $i++): ?>
              <div>
                <div style="aspect-ratio:1;background:<?= $farbe('farbe_flaeche') ?>;
                            border-radius:<?= $farbe('ecken') ?>"></div>
                <div style="font-size:11px;margin-top:4px">Artikelname</div>
                <div style="font-size:11px">
                  <span style="color:<?= $farbe('farbe_sale') ?>;font-weight:600">39,00 €</span>
                  <span style="color:<?= $farbe('farbe_nebentext') ?>;text-decoration:line-through">49,00 €</span>
                </div>
              </div>
            <?php endfor; ?>
          </div>
        </div>
        <p class="bk-tipp" style="margin-top:8px">Grobe Vorschau. Die vollständige Ansicht öffnet
          der Knopf oben rechts.</p>
      </div>
    </section>
  </div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
