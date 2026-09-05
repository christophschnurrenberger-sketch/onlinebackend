<?php
/**
 * Artikel anlegen und bearbeiten.
 *
 * Die meistgenutzte Maske des Backends. Sie ist so gebaut, dass der häufige
 * Fall schnell geht (Titel, Preis, Bild, speichern) und der seltene möglich
 * bleibt (Optionen, Varianten, SEO, Bestandspolitik).
 */

$seitentitel = 'Artikel bearbeiten';
$benoetigtesRecht = 'pflegen';
require __DIR__ . '/partials/header.php';

$id  = Util::getInt('id');
$neu = $id === 0;

/* --- Speichern ------------------------------------------------------------ */

if (Util::isPost()) {
    Auth::csrfPruefen();
    try {
        /* Optionen aus dem Formular. */
        $optionen = [];
        foreach (Util::postArray('option_name') as $nr => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $optionen[] = [
                'name'       => $name,
                'werteliste' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) (Util::postArray('option_werte')[$nr] ?? ''))
                ), static fn($w) => $w !== '')),
            ];
        }

        /* Varianten aus dem Formular. Die Seitenspalte gilt für alle. */
        $gemeinsam = [
            'versandpflicht' => Util::postBool('versandpflicht') ? 1 : 0,
            'steuer_id'      => Util::postInt('steuer_id') ?: null,
            'gewicht_g'      => Util::postInt('gewicht_g'),
            'bestand_fuehren' => Util::postBool('bestand_fuehren') ? 1 : 0,
            'ueberverkauf'   => Util::postBool('ueberverkauf') ? 1 : 0,
        ];
        $varianten = [];
        foreach (Util::postArray('v_titel') as $nr => $titel) {
            $varianten[] = array_merge($gemeinsam, [
                'id'            => (int) (Util::postArray('v_id')[$nr] ?? 0),
                'titel'         => (string) $titel,
                'preis'         => (string) (Util::postArray('v_preis')[$nr] ?? '0'),
                'streichpreis'  => (string) (Util::postArray('v_streich')[$nr] ?? ''),
                'artikelnummer' => (string) (Util::postArray('v_nummer')[$nr] ?? ''),
                'bestand'       => (int) (Util::postArray('v_bestand')[$nr] ?? 0),
                'option1'       => (string) (Util::postArray('v_o1')[$nr] ?? ''),
                'option2'       => (string) (Util::postArray('v_o2')[$nr] ?? ''),
                'option3'       => (string) (Util::postArray('v_o3')[$nr] ?? ''),
            ]);
        }

        /* Bilder: bestehende plus neu hochgeladene. */
        $bilder = [];
        foreach (Util::postArray('bild_url') as $url) {
            $url = trim((string) $url);
            if ($url !== '') {
                $bilder[] = ['url' => $url, 'alt' => ''];
            }
        }
        if (!empty($_FILES['neue_bilder']['name'][0])) {
            foreach ($_FILES['neue_bilder']['name'] as $nr => $name) {
                if ((int) $_FILES['neue_bilder']['error'][$nr] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $hoch = Medien::hochladen([
                    'name'     => $name,
                    'type'     => $_FILES['neue_bilder']['type'][$nr],
                    'tmp_name' => $_FILES['neue_bilder']['tmp_name'][$nr],
                    'error'    => $_FILES['neue_bilder']['error'][$nr],
                    'size'     => $_FILES['neue_bilder']['size'][$nr],
                ]);
                $bilder[] = ['url' => $hoch['url'], 'alt' => ''];
            }
        }

        $daten = [
            'titel'        => Util::post('titel'),
            'handle'       => Util::post('handle'),
            'untertitel'   => Util::post('untertitel'),
            'beschreibung' => Util::postRaw('beschreibung'),
            'hersteller'   => Util::post('hersteller'),
            'typ'          => Util::post('typ'),
            'schlagworte'  => Util::post('schlagworte'),
            'status'       => Util::post('status'),
            'seo_titel'    => Util::post('seo_titel'),
            'seo_text'     => Util::post('seo_text'),
            'optionen'     => $optionen,
            'varianten'    => $varianten,
            'bilder'       => $bilder,
        ];

        if ($neu) {
            $id = Artikel::anlegen($daten);
            Util::redirect('artikel-bearbeiten.php?id=' . $id . '&meldung=' . rawurlencode('Artikel angelegt.'));
        }
        Artikel::speichern($id, $daten);
        Util::redirect('artikel-bearbeiten.php?id=' . $id . '&meldung=' . rawurlencode('Artikel gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="ad-hinweis ad-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

/* --- Löschen und Duplizieren ---------------------------------------------- */

if (!$neu && Util::get('aktion') === 'loeschen') {
    Auth::csrfPruefen();
    Artikel::loeschen($id);
    Util::redirect('artikel.php?meldung=' . rawurlencode('Artikel gelöscht.'));
}
if (!$neu && Util::get('aktion') === 'duplizieren') {
    $kopie = Artikel::duplizieren($id);
    Util::redirect('artikel-bearbeiten.php?id=' . $kopie . '&meldung=' . rawurlencode('Kopie als Entwurf angelegt.'));
}

/* --- Daten für die Maske --------------------------------------------------- */

$artikel = $neu ? null : Artikel::holen($id);
if (!$neu && $artikel === null) {
    Util::redirect('artikel.php?meldung=' . rawurlencode('Artikel nicht gefunden.') . '&art=fehler');
}

$vorgabe = [
    'titel' => '', 'handle' => '', 'untertitel' => '', 'beschreibung' => '',
    'hersteller' => '', 'typ' => '', 'schlagworte' => '', 'status' => 'entwurf',
    'seo_titel' => '', 'seo_text' => '',
];
$werte     = $artikel ?? $vorgabe;
$varianten = $artikel['varianten'] ?? [[
    'id' => 0, 'titel' => 'Standard', 'preis' => 0, 'streichpreis' => null,
    'artikelnummer' => '', 'bestand' => 0, 'option1' => '', 'option2' => '', 'option3' => '',
]];
$optionen  = $artikel['optionen'] ?? [];
$bilder    = $artikel['bilder'] ?? [];
$erste     = $varianten[0];

$steuersaetze = Steuern::liste();
$hersteller   = Artikel::hersteller();
$typen        = Artikel::typen();
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <a class="ad-zurueck" href="artikel.php">← Alle Artikel</a>
    <h1><?= $neu ? 'Neuer Artikel' : Util::e((string) $werte['titel']) ?></h1>
    <?php if (!$neu): ?>
      <div class="ad-untertitel">Zuletzt bearbeitet <?= Util::e(Util::dt((string) $werte['geaendert'])) ?></div>
    <?php endif; ?>
  </div>
  <div class="ad-aktionen">
    <?php if (!$neu): ?>
      <a class="ad-knopf" href="artikel-bearbeiten.php?id=<?= $id ?>&aktion=duplizieren">Duplizieren</a>
      <a class="ad-knopf" target="_blank" rel="noopener"
         href="<?= Util::e(Config::url('artikel.php?h=' . rawurlencode((string) $werte['handle']))) ?>">Im Shop ansehen ↗</a>
    <?php endif; ?>
    <button class="ad-knopf ad-knopf-voll" type="submit" form="artikelform">Speichern</button>
  </div>
</div>

<form id="artikelform" method="post" enctype="multipart/form-data" class="ad-zwei">
  <?= Auth::csrfFeld() ?>

  <div>
    <section class="ad-karte"><div class="ad-karte-inhalt">
      <div class="ad-feld">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" required data-titel-quelle
               value="<?= Util::e((string) $werte['titel']) ?>" placeholder="z. B. Leinenhemd Sommer">
      </div>
      <div class="ad-feld">
        <label for="untertitel">Kurzbeschreibung</label>
        <input type="text" id="untertitel" name="untertitel" value="<?= Util::e((string) $werte['untertitel']) ?>">
        <div class="ad-tipp">Eine Zeile unter dem Titel im Shop.</div>
      </div>
      <div class="ad-feld">
        <label for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung" rows="10"><?= Util::e((string) $werte['beschreibung']) ?></textarea>
        <div class="ad-tipp">Einfaches HTML ist erlaubt: &lt;p&gt;, &lt;ul&gt;, &lt;strong&gt;, &lt;a&gt;.
          Skripte werden beim Speichern entfernt.</div>
      </div>
    </div></section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Bilder</h2></div>
      <div class="ad-karte-inhalt">
        <?php if ($bilder !== []): ?>
          <div class="ad-bilder" style="margin-bottom:12px">
            <?php foreach ($bilder as $nr => $bild): ?>
              <div class="ad-bild">
                <img src="<?= Util::e(Theme::url((string) $bild['url'])) ?>" alt="">
                <input type="hidden" name="bild_url[]" value="<?= Util::e((string) $bild['url']) ?>">
                <button type="button" title="Entfernen" data-zeile-weg=".ad-bild">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="ad-feld" style="margin:0">
          <label for="neue_bilder">Bilder hinzufügen</label>
          <input type="file" id="neue_bilder" name="neue_bilder[]" accept="image/*" multiple>
          <div class="ad-tipp">JPEG, PNG, WebP, AVIF oder GIF – bis 8 MB je Bild.
            Werden beim Speichern übernommen.</div>
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf">
        <h2>Optionen &amp; Varianten</h2>
        <button class="ad-knopf ad-knopf-klein" type="button"
                data-zeile-hinzu="#optionsliste" data-vorlage="#optionsvorlage">Option hinzufügen</button>
      </div>
      <div class="ad-karte-inhalt">
        <div id="optionsliste">
          <?php foreach ($optionen as $nr => $option): ?>
            <div class="ad-block">
              <div class="ad-feldzeile" style="margin-bottom:8px">
                <div class="ad-feld" style="margin:0">
                  <label>Optionsname</label>
                  <input type="text" name="option_name[]" value="<?= Util::e((string) $option['name']) ?>" placeholder="Größe">
                </div>
                <div class="ad-feld" style="margin:0">
                  <label>Werte (kommagetrennt)</label>
                  <input type="text" name="option_werte[]"
                         value="<?= Util::e(implode(', ', $option['werteliste'])) ?>" placeholder="S, M, L">
                </div>
              </div>
              <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
                      data-zeile-weg=".ad-block">Option entfernen</button>
            </div>
          <?php endforeach; ?>
        </div>

        <p class="ad-tipp">
          Nach dem Ändern von Optionen einmal speichern – danach lassen sich die Varianten
          unten bearbeiten. Ohne Optionen hat der Artikel genau eine Variante.
        </p>

        <div style="margin-top:16px">
          <div class="ad-variante ad-variante-kopf">
            <div>Variante</div><div>Preis</div><div>Streichpreis</div>
            <div>Artikelnr.</div><div>Bestand</div><div></div>
          </div>
          <div id="variantenliste">
            <?php foreach ($varianten as $nr => $variante): ?>
              <div class="ad-variante">
                <input type="hidden" name="v_id[]" value="<?= (int) ($variante['id'] ?? 0) ?>">
                <input type="hidden" name="v_o1[]" value="<?= Util::e((string) ($variante['option1'] ?? '')) ?>">
                <input type="hidden" name="v_o2[]" value="<?= Util::e((string) ($variante['option2'] ?? '')) ?>">
                <input type="hidden" name="v_o3[]" value="<?= Util::e((string) ($variante['option3'] ?? '')) ?>">
                <input type="text" name="v_titel[]" value="<?= Util::e((string) $variante['titel']) ?>" placeholder="Standard">
                <input type="text" name="v_preis[]" value="<?= Util::e(Util::geldFeld((int) $variante['preis'])) ?>" placeholder="0,00" inputmode="decimal">
                <input type="text" name="v_streich[]" value="<?= Util::e(Util::geldFeld($variante['streichpreis'] !== null ? (int) $variante['streichpreis'] : null)) ?>" placeholder="—" inputmode="decimal">
                <input type="text" name="v_nummer[]" value="<?= Util::e((string) $variante['artikelnummer']) ?>" placeholder="SKU">
                <input type="number" name="v_bestand[]" value="<?= (int) $variante['bestand'] ?>">
                <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
                        data-zeile-weg=".ad-variante" title="Variante entfernen">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="ad-knopf ad-knopf-klein" type="button" style="margin-top:10px"
                  data-zeile-hinzu="#variantenliste" data-vorlage="#variantenvorlage">Variante hinzufügen</button>
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Suchmaschinen</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feld">
          <label for="handle">Adresse im Shop</label>
          <input type="text" id="handle" name="handle" data-handle-ziel value="<?= Util::e((string) $werte['handle']) ?>">
          <div class="ad-tipp">Erreichbar unter artikel.php?h=<?= Util::e((string) ($werte['handle'] ?: 'titel')) ?></div>
        </div>
        <div class="ad-feld">
          <label for="seo_titel">SEO-Titel</label>
          <input type="text" id="seo_titel" name="seo_titel" value="<?= Util::e((string) $werte['seo_titel']) ?>"
                 placeholder="<?= Util::e((string) $werte['titel']) ?>">
        </div>
        <div class="ad-feld">
          <label for="seo_text">SEO-Beschreibung</label>
          <textarea id="seo_text" name="seo_text" rows="3"><?= Util::e((string) $werte['seo_text']) ?></textarea>
          <div class="ad-tipp">Rund 150 Zeichen. Leer lassen, um sie aus der Beschreibung zu erzeugen.</div>
        </div>
      </div>
    </section>
  </div>

  <div>
    <section class="ad-karte"><div class="ad-karte-inhalt">
      <div class="ad-feld" style="margin:0">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="entwurf" <?= (string) $werte['status'] === 'entwurf' ? 'selected' : '' ?>>Entwurf – nicht im Shop</option>
          <option value="aktiv"   <?= (string) $werte['status'] === 'aktiv' ? 'selected' : '' ?>>Aktiv – geht beim Veröffentlichen live</option>
          <option value="archiv"  <?= (string) $werte['status'] === 'archiv' ? 'selected' : '' ?>>Archiviert</option>
        </select>
        <div class="ad-tipp">Auch aktive Artikel erscheinen erst nach dem Veröffentlichen im Shop.</div>
      </div>
    </div></section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Einordnung</h2></div>
      <div class="ad-karte-inhalt">
        <div class="ad-feld">
          <label for="typ">Produkttyp</label>
          <input type="text" id="typ" name="typ" list="typen" value="<?= Util::e((string) $werte['typ']) ?>" placeholder="z. B. Hemden">
          <datalist id="typen"><?php foreach ($typen as $t): ?><option value="<?= Util::e($t) ?>"></option><?php endforeach; ?></datalist>
        </div>
        <div class="ad-feld">
          <label for="hersteller">Hersteller</label>
          <input type="text" id="hersteller" name="hersteller" list="hersteller_liste" value="<?= Util::e((string) $werte['hersteller']) ?>">
          <datalist id="hersteller_liste"><?php foreach ($hersteller as $h): ?><option value="<?= Util::e($h) ?>"></option><?php endforeach; ?></datalist>
        </div>
        <div class="ad-feld" style="margin:0">
          <label for="schlagworte">Schlagwörter</label>
          <input type="text" id="schlagworte" name="schlagworte" value="<?= Util::e((string) $werte['schlagworte']) ?>">
          <div class="ad-tipp">Kommagetrennt. Automatische Kategorien greifen darauf zu.</div>
        </div>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Bestand, Versand &amp; Steuer</h2></div>
      <div class="ad-karte-inhalt">
        <label class="ad-haken">
          <input type="checkbox" name="bestand_fuehren" value="1"
                 <?= (int) ($erste['bestand_fuehren'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span>Bestand führen</span>
        </label>
        <label class="ad-haken">
          <input type="checkbox" name="ueberverkauf" value="1"
                 <?= (int) ($erste['ueberverkauf'] ?? 0) === 1 ? 'checked' : '' ?>>
          <span>Verkauf auch bei Bestand 0 zulassen</span>
        </label>
        <label class="ad-haken">
          <input type="checkbox" name="versandpflicht" value="1"
                 <?= (int) ($erste['versandpflicht'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span>Artikel muss versendet werden</span>
        </label>
        <div class="ad-feld">
          <label for="steuer_id">Steuersatz</label>
          <select id="steuer_id" name="steuer_id">
            <option value="">Standardsatz des Shops</option>
            <?php foreach ($steuersaetze as $satz): ?>
              <option value="<?= (int) $satz['id'] ?>"
                      <?= (int) ($erste['steuer_id'] ?? 0) === (int) $satz['id'] ? 'selected' : '' ?>>
                <?= Util::e((string) $satz['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ad-feld" style="margin:0">
          <label for="gewicht_g">Gewicht in Gramm</label>
          <input type="number" id="gewicht_g" name="gewicht_g" value="<?= (int) ($erste['gewicht_g'] ?? 0) ?>" min="0">
        </div>
      </div>
    </section>

    <?php if (!$neu && $artikel['kategorien'] !== []): ?>
      <section class="ad-karte">
        <div class="ad-karte-kopf"><h2>In Kategorien</h2></div>
        <div class="ad-karte-inhalt">
          <?php foreach ($artikel['kategorien'] as $kategorie): ?>
            <a class="ad-marke" style="margin:0 4px 4px 0"
               href="kategorie.php?id=<?= (int) $kategorie['id'] ?>"><?= Util::e((string) $kategorie['titel']) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!$neu): ?>
      <section class="ad-karte"><div class="ad-karte-inhalt">
        <a class="ad-knopf ad-knopf-rot" style="width:100%;justify-content:center"
           href="artikel-bearbeiten.php?id=<?= $id ?>&aktion=loeschen&_token=<?= Util::e(Auth::csrfToken()) ?>"
           data-frage="Diesen Artikel endgültig löschen? Bestehende Bestellungen bleiben unverändert.">
          Artikel löschen
        </a>
      </div></section>
    <?php endif; ?>
  </div>
</form>

<template id="optionsvorlage">
  <div class="ad-block">
    <div class="ad-feldzeile" style="margin-bottom:8px">
      <div class="ad-feld" style="margin:0"><label>Optionsname</label>
        <input type="text" name="option_name[]" placeholder="Größe"></div>
      <div class="ad-feld" style="margin:0"><label>Werte (kommagetrennt)</label>
        <input type="text" name="option_werte[]" placeholder="S, M, L"></div>
    </div>
    <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
            data-zeile-weg=".ad-block">Option entfernen</button>
  </div>
</template>

<template id="variantenvorlage">
  <div class="ad-variante">
    <input type="hidden" name="v_id[]" value="0">
    <input type="hidden" name="v_o1[]" value=""><input type="hidden" name="v_o2[]" value="">
    <input type="hidden" name="v_o3[]" value="">
    <input type="text" name="v_titel[]" placeholder="Neue Variante">
    <input type="text" name="v_preis[]" placeholder="0,00" inputmode="decimal">
    <input type="text" name="v_streich[]" placeholder="—" inputmode="decimal">
    <input type="text" name="v_nummer[]" placeholder="SKU">
    <input type="number" name="v_bestand[]" value="0">
    <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="button"
            data-zeile-weg=".ad-variante" title="Variante entfernen">✕</button>
  </div>
</template>

<?php require __DIR__ . '/partials/footer.php'; ?>
