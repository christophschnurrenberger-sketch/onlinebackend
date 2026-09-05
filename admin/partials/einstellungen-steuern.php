<?php /** Reiter „Steuern“. */ ?>

<p class="ad-tipp">Der Standardsatz gilt für alle Artikel, an denen kein eigener Satz hängt.
  Die Preise im Shop sind Bruttopreise; die ausgewiesene Steuer ist der darin enthaltene Anteil.</p>

<table style="margin:14px 0">
  <thead><tr><th>Name</th><th>Satz</th><th>Land</th><th>Standard</th><th></th></tr></thead>
  <tbody>
    <?php foreach (Steuern::liste() as $satz): ?>
      <tr>
        <td><?= Util::e((string) $satz['name']) ?></td>
        <td><?= Util::e(Steuern::prozentText((int) $satz['satz_bp'])) ?></td>
        <td><?= Util::e((string) $satz['land']) ?></td>
        <td><?php if ((int) $satz['standard'] === 1): ?>
          <span class="ad-marke ad-marke-gruen"><i></i>Standard</span>
        <?php endif; ?></td>
        <td class="ad-zahl-rechts">
          <form method="post" style="display:inline" data-frage="Steuersatz löschen? Artikel damit fallen auf den Standardsatz zurück.">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="bereich" value="steuer-loeschen">
            <input type="hidden" name="reiter" value="steuern">
            <input type="hidden" name="steuer_id" value="<?= (int) $satz['id'] ?>">
            <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="submit">Löschen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="ad-block">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="steuer-neu">
  <input type="hidden" name="reiter" value="steuern">
  <h4 style="font-size:13px;margin:0 0 10px">Steuersatz hinzufügen</h4>
  <div class="ad-feldzeile-drei">
    <div class="ad-feld"><label>Name</label>
      <input type="text" name="steuer_name" required placeholder="Standard (19 %)"></div>
    <div class="ad-feld"><label>Satz in Prozent</label>
      <input type="text" name="steuer_satz" required placeholder="19" inputmode="decimal"></div>
    <div class="ad-feld"><label>Land</label>
      <input type="text" name="steuer_land" value="DE" maxlength="2"></div>
  </div>
  <label class="ad-haken"><input type="checkbox" name="steuer_standard" value="1">
    <span>Als Standardsatz verwenden</span></label>
  <button class="ad-knopf ad-knopf-voll ad-knopf-klein" type="submit">Hinzufügen</button>
</form>
