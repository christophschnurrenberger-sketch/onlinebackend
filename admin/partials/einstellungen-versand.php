<?php /** Reiter „Versand“. */ ?>

<div class="ad-hinweis ad-hinweis-info">
  <strong>Zonen und Versandarten</strong>
  Eine Zone bündelt Länder, ihre Versandarten gelten für alle davon.
  Eine Zone <em>ohne</em> Länderliste ist die Auffangzone für alle übrigen Länder –
  ohne sie kann aus nicht aufgeführten Ländern niemand bestellen.
</div>

<?php foreach (Versand::zonen() as $zone): ?>
  <form method="post" class="ad-block">
    <?= Auth::csrfFeld() ?>
    <input type="hidden" name="bereich" value="zone-speichern">
    <input type="hidden" name="reiter" value="versand">
    <input type="hidden" name="zone_id" value="<?= (int) $zone['id'] ?>">

    <div class="ad-feldzeile">
      <div class="ad-feld"><label>Name der Zone</label>
        <input type="text" name="zone_name" value="<?= Util::e((string) $zone['name']) ?>" required></div>
      <div class="ad-feld"><label>Länder (Kürzel, kommagetrennt)</label>
        <input type="text" name="zone_laender" value="<?= Util::e(implode(', ', $zone['laenderliste'])) ?>"
               placeholder="leer = alle übrigen Länder"></div>
    </div>

    <h4 style="font-size:13px;margin:12px 0 8px">Versandarten</h4>
    <?php
    $arten = $zone['arten'];
    // Immer eine leere Zeile anbieten, damit sich ohne Umweg eine Art ergänzen lässt.
    $arten[] = ['name' => '', 'preis' => 0, 'lieferzeit' => '', 'frei_ab' => null];
    foreach ($arten as $art): ?>
      <div class="ad-feldzeile" style="grid-template-columns:1.4fr .8fr 1fr 1fr;margin-bottom:8px">
        <input type="text" name="art_name[]" value="<?= Util::e((string) $art['name']) ?>"
               placeholder="Standardversand"
               style="padding:8px 11px;border:1px solid var(--rahmen-kraeftig);border-radius:6px;font:inherit">
        <input type="text" name="art_preis[]" value="<?= Util::e(Util::geldFeld((int) $art['preis'])) ?>"
               placeholder="4,90" inputmode="decimal"
               style="padding:8px 11px;border:1px solid var(--rahmen-kraeftig);border-radius:6px;font:inherit">
        <input type="text" name="art_lieferzeit[]" value="<?= Util::e((string) $art['lieferzeit']) ?>"
               placeholder="2–3 Werktage"
               style="padding:8px 11px;border:1px solid var(--rahmen-kraeftig);border-radius:6px;font:inherit">
        <input type="text" name="art_frei_ab[]"
               value="<?= $art['frei_ab'] !== null ? Util::e(Util::geldFeld((int) $art['frei_ab'])) : '' ?>"
               placeholder="frei ab …" inputmode="decimal"
               style="padding:8px 11px;border:1px solid var(--rahmen-kraeftig);border-radius:6px;font:inherit">
      </div>
    <?php endforeach; ?>

    <div class="ad-knopfgruppe" style="margin-top:10px">
      <button class="ad-knopf ad-knopf-voll ad-knopf-klein" type="submit">Zone speichern</button>
      <button class="ad-knopf ad-knopf-klein ad-knopf-rot" type="submit" name="bereich" value="zone-loeschen"
              formnovalidate onclick="return confirm('Zone „<?= Util::e((string) $zone['name']) ?>“ mit allen Versandarten löschen?')">
        Zone löschen
      </button>
    </div>
  </form>
<?php endforeach; ?>

<form method="post" class="ad-block" style="background:var(--flaeche)">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="zone-neu">
  <input type="hidden" name="reiter" value="versand">
  <h4 style="font-size:13px;margin:0 0 10px">Neue Zone anlegen</h4>
  <div class="ad-feldzeile">
    <div class="ad-feld"><label>Name</label>
      <input type="text" name="zone_name" required placeholder="z. B. Deutschland"></div>
    <div class="ad-feld"><label>Länder (Kürzel)</label>
      <input type="text" name="zone_laender" placeholder="DE, AT"></div>
  </div>
  <div class="ad-feldzeile" style="grid-template-columns:1.4fr .8fr 1fr 1fr">
    <div class="ad-feld"><label>Erste Versandart</label>
      <input type="text" name="art_name" placeholder="Standardversand"></div>
    <div class="ad-feld"><label>Preis</label>
      <input type="text" name="art_preis" placeholder="4,90" inputmode="decimal"></div>
    <div class="ad-feld"><label>Lieferzeit</label>
      <input type="text" name="art_lieferzeit" placeholder="2–3 Werktage"></div>
    <div class="ad-feld"><label>Kostenlos ab</label>
      <input type="text" name="art_frei_ab" placeholder="75,00" inputmode="decimal"></div>
  </div>
  <button class="ad-knopf ad-knopf-voll ad-knopf-klein" type="submit">Zone anlegen</button>
</form>
