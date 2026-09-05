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

/** Fertige Farbschemata zum Starten. */
$vorlagen = [
    'basis' => ['Basis (hell, neutral)', [
        'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f7f7f8', 'farbe_text' => '#16181d',
        'farbe_nebentext' => '#6b7280', 'farbe_rahmen' => '#e5e7eb', 'farbe_knopf' => '#16181d',
        'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#2f6f4f', 'farbe_sale' => '#c0392b',
        'ecken' => '10px',
    ]],
    'kontrast' => ['Kontrast (schwarz-weiß, kantig)', [
        'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f2f2f2', 'farbe_text' => '#000000',
        'farbe_nebentext' => '#666666', 'farbe_rahmen' => '#000000', 'farbe_knopf' => '#000000',
        'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#000000', 'farbe_sale' => '#d40000',
        'ecken' => '0px',
    ]],
    'warm' => ['Warm (Sand und Terrakotta)', [
        'farbe_hintergrund' => '#fdfaf5', 'farbe_flaeche' => '#f4ede3', 'farbe_text' => '#2c231b',
        'farbe_nebentext' => '#7d6c5b', 'farbe_rahmen' => '#e2d6c6', 'farbe_knopf' => '#8c4a2f',
        'farbe_knopf_text' => '#ffffff', 'farbe_akzent' => '#6b7f4f', 'farbe_sale' => '#b4432a',
        'ecken' => '14px',
    ]],
    'dunkel' => ['Dunkel', [
        'farbe_hintergrund' => '#12141a', 'farbe_flaeche' => '#1b1f28', 'farbe_text' => '#f0f2f5',
        'farbe_nebentext' => '#9aa3b2', 'farbe_rahmen' => '#2a303c', 'farbe_knopf' => '#f0f2f5',
        'farbe_knopf_text' => '#12141a', 'farbe_akzent' => '#6bd6a4', 'farbe_sale' => '#ff7a6b',
        'ecken' => '10px',
    ]],
];

if (Util::isPost()) {
    Auth::csrfPruefen();

    $werte = [];
    $vorlage = Util::post('design_vorlage');

    if (Util::post('aktion') === 'vorlage' && isset($vorlagen[$vorlage])) {
        $werte = $vorlagen[$vorlage][1];
        $werte['design_vorlage'] = $vorlage;
        Settings::setMany($werte);
        Util::redirect('design.php?meldung=' . rawurlencode('Farbschema „' . $vorlagen[$vorlage][0] . '“ übernommen.'));
    }

    foreach ([
        'farbe_hintergrund', 'farbe_flaeche', 'farbe_text', 'farbe_nebentext', 'farbe_rahmen',
        'farbe_knopf', 'farbe_knopf_text', 'farbe_akzent', 'farbe_sale',
        'schrift_titel', 'schrift_text', 'ecken', 'inhaltsbreite', 'artikel_pro_reihe',
        'hinweisleiste', 'start_titel', 'start_text', 'start_bild', 'start_knopf',
        'start_knopf_url', 'fusszeile_text',
    ] as $feld) {
        $werte[$feld] = Util::post($feld);
    }
    $werte['eigenes_css']         = Util::postRaw('eigenes_css');
    $werte['hinweisleiste_an']    = Util::postBool('hinweisleiste_an') ? '1' : '0';
    $werte['hersteller_zeigen']   = Util::postBool('hersteller_zeigen') ? '1' : '0';
    $werte['streichpreis_zeigen'] = Util::postBool('streichpreis_zeigen') ? '1' : '0';
    $werte['design_vorlage']      = $vorlage;

    Settings::setMany($werte);
    Util::redirect('design.php?meldung=' . rawurlencode('Design gespeichert. Zum Sichtbarwerden bitte veröffentlichen.'));
}

$e = static fn(string $k): string => Settings::get($k);

$schriften = [
    "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif" => 'System (serifenlos)',
    "'Helvetica Neue', Helvetica, Arial, sans-serif" => 'Helvetica',
    'Georgia, "Times New Roman", serif' => 'Georgia (Serif)',
    '"Iowan Old Style", "Palatino Linotype", Palatino, serif' => 'Palatino (Serif)',
    "ui-monospace, 'SF Mono', Menlo, Consolas, monospace" => 'Monospace',
];

$farben = [
    'farbe_hintergrund' => 'Hintergrund', 'farbe_flaeche' => 'Flächen',
    'farbe_text' => 'Text', 'farbe_nebentext' => 'Nebentext',
    'farbe_rahmen' => 'Rahmen', 'farbe_akzent' => 'Akzent',
    'farbe_knopf' => 'Buttons', 'farbe_knopf_text' => 'Button-Schrift',
    'farbe_sale' => 'Sale-Preis',
];
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Design</h1>
    <div class="ad-untertitel">Farben, Schriften und Startseite des Shops</div>
  </div>
  <div class="ad-aktionen">
    <a class="ad-knopf" href="<?= Util::e(Config::baseUrl()) ?>/" target="_blank" rel="noopener">Shop ansehen ↗</a>
    <button class="ad-knopf ad-knopf-voll" type="submit" form="designform">Speichern</button>
  </div>
</div>

<div class="ad-hinweis ad-hinweis-info">
  <strong>So funktioniert das Design</strong>
  Diese Werte werden als CSS-Variablen in jede Shopseite geschrieben. Ein späteres eigenes Theme
  überschreibt entweder diese Variablen oder ersetzt <code>assets/shop.css</code> – die Seitenstruktur
  bleibt dabei unverändert. Änderungen werden im Shop erst nach dem Veröffentlichen sichtbar.
</div>

<form method="post" style="margin-bottom:16px">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="aktion" value="vorlage">
  <section class="ad-karte">
    <div class="ad-karte-kopf">
      <h2>Farbschema übernehmen</h2>
      <select name="design_vorlage">
        <?php foreach ($vorlagen as $wert => [$label, $unused]): ?>
          <option value="<?= Util::e($wert) ?>" <?= $e('design_vorlage') === $wert ? 'selected' : '' ?>>
            <?= Util::e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="ad-knopf" type="submit">Übernehmen</button>
    </div>
  </section>
</form>

<form id="designform" method="post" class="ad-zwei">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="design_vorlage" value="<?= Util::e($e('design_vorlage')) ?>">

  <div>
    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Farben</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feldzeile">
          <?php foreach ($farben as $feld => $label): ?>
            <div class="ad-feld">
              <label for="<?= $feld ?>"><?= Util::e($label) ?></label>
              <div class="ad-farbe">
                <input type="color" value="<?= Util::e(preg_match('/^#[0-9a-f]{6}$/i', $e($feld)) ? $e($feld) : '#000000') ?>">
                <input type="text" id="<?= $feld ?>" name="<?= $feld ?>" value="<?= Util::e($e($feld)) ?>">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Startseite</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feld">
          <label for="start_titel">Überschrift</label>
          <input type="text" id="start_titel" name="start_titel" value="<?= Util::e($e('start_titel')) ?>">
        </div>
        <div class="ad-feld">
          <label for="start_text">Unterzeile</label>
          <input type="text" id="start_text" name="start_text" value="<?= Util::e($e('start_text')) ?>">
        </div>
        <div class="ad-feld">
          <label for="start_bild">Hintergrundbild (Adresse)</label>
          <input type="text" id="start_bild" name="start_bild" value="<?= Util::e($e('start_bild')) ?>"
                 placeholder="uploads/buehne.jpg">
          <div class="ad-tipp">Leer lassen für eine einfarbige Fläche.</div>
        </div>
        <div class="ad-feldzeile">
          <div class="ad-feld"><label for="start_knopf">Buttontext</label>
            <input type="text" id="start_knopf" name="start_knopf" value="<?= Util::e($e('start_knopf')) ?>"></div>
          <div class="ad-feld"><label for="start_knopf_url">Buttonziel</label>
            <input type="text" id="start_knopf_url" name="start_knopf_url" value="<?= Util::e($e('start_knopf_url')) ?>"></div>
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Ankündigungsleiste</h2></div>
      <div class="ad-karte-inhalt">
        <label class="ad-haken">
          <input type="checkbox" name="hinweisleiste_an" value="1" <?= Settings::bool('hinweisleiste_an') ? 'checked' : '' ?>>
          <span>Leiste über dem Kopf anzeigen</span>
        </label>
        <div class="ad-feld" style="margin:0">
          <label for="hinweisleiste">Text</label>
          <input type="text" id="hinweisleiste" name="hinweisleiste" value="<?= Util::e($e('hinweisleiste')) ?>"
                 placeholder="Versandkostenfrei ab 75 €">
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Eigenes CSS</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feld" style="margin:0">
          <label for="eigenes_css">Zusätzliche Regeln</label>
          <textarea id="eigenes_css" name="eigenes_css" rows="8" class="ad-code"><?= Util::e($e('eigenes_css')) ?></textarea>
          <div class="ad-tipp">Wird nach dem Basis-Stylesheet eingebunden und überschreibt es damit.</div>
        </div>
      </div>
    </section>
  </div>

  <div>
    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Typografie &amp; Raster</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feld">
          <label for="schrift_titel">Überschriften</label>
          <select id="schrift_titel" name="schrift_titel">
            <?php foreach ($schriften as $wert => $label): ?>
              <option value="<?= Util::e($wert) ?>" <?= $e('schrift_titel') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ad-feld">
          <label for="schrift_text">Fließtext</label>
          <select id="schrift_text" name="schrift_text">
            <?php foreach ($schriften as $wert => $label): ?>
              <option value="<?= Util::e($wert) ?>" <?= $e('schrift_text') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ad-feld">
          <label for="ecken">Ecken</label>
          <select id="ecken" name="ecken">
            <?php foreach (['0px' => 'Kantig', '6px' => 'Leicht gerundet', '10px' => 'Gerundet', '18px' => 'Stark gerundet'] as $wert => $label): ?>
              <option value="<?= $wert ?>" <?= $e('ecken') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ad-feld">
          <label for="inhaltsbreite">Inhaltsbreite</label>
          <select id="inhaltsbreite" name="inhaltsbreite">
            <?php foreach (['1000px' => 'Schmal', '1200px' => 'Standard', '1400px' => 'Breit', '100%' => 'Volle Breite'] as $wert => $label): ?>
              <option value="<?= $wert ?>" <?= $e('inhaltsbreite') === $wert ? 'selected' : '' ?>><?= Util::e($label) ?> (<?= $wert ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ad-feld" style="margin:0">
          <label for="artikel_pro_reihe">Artikel pro Reihe</label>
          <select id="artikel_pro_reihe" name="artikel_pro_reihe">
            <?php foreach (['2', '3', '4', '5'] as $wert): ?>
              <option value="<?= $wert ?>" <?= $e('artikel_pro_reihe') === $wert ? 'selected' : '' ?>><?= $wert ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Anzeigeoptionen</h2></div>
      <div class="ad-karte-inhalt">
        <label class="ad-haken">
          <input type="checkbox" name="hersteller_zeigen" value="1" <?= Settings::bool('hersteller_zeigen') ? 'checked' : '' ?>>
          <span>Hersteller in der Artikelliste zeigen</span>
        </label>
        <label class="ad-haken">
          <input type="checkbox" name="streichpreis_zeigen" value="1" <?= Settings::bool('streichpreis_zeigen') ? 'checked' : '' ?>>
          <span>Streichpreise und Sale-Kennzeichnung zeigen</span>
        </label>
        <div class="ad-feld" style="margin:0">
          <label for="fusszeile_text">Fußzeilentext</label>
          <input type="text" id="fusszeile_text" name="fusszeile_text" value="<?= Util::e($e('fusszeile_text')) ?>">
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Vorschau</h2></div>
      <div class="ad-karte-inhalt">
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
        <p class="ad-tipp" style="margin-top:8px">Grobe Vorschau. Die vollständige Ansicht öffnet
          der Knopf oben rechts.</p>
      </div>
    </section>
  </div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
