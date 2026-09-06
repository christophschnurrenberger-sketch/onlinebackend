<?php /** Reiter „Steuern“. */ ?>

<p class="bk-tipp">Der Standardsatz gilt für alle Artikel, an denen kein eigener Satz hängt.
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
          <span class="bk-marke bk-marke-gruen"><i></i>Standard</span>
        <?php endif; ?></td>
        <td class="bk-zahl-rechts">
          <form method="post" style="display:inline" data-frage="Steuersatz löschen? Artikel damit fallen auf den Standardsatz zurück.">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="bereich" value="steuer-loeschen">
            <input type="hidden" name="reiter" value="steuern">
            <input type="hidden" name="steuer_id" value="<?= (int) $satz['id'] ?>">
            <button class="bk-knopf bk-knopf-klein bk-knopf-leer bk-knopf-rot" type="submit">Löschen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="bk-block">
  <?= Auth::csrfFeld() ?>
  <input type="hidden" name="bereich" value="steuer-neu">
  <input type="hidden" name="reiter" value="steuern">
  <h4 style="font-size:13px;margin:0 0 10px">Steuersatz hinzufügen</h4>
  <div class="bk-feldzeile-drei">
    <div class="bk-feld"><label>Name</label>
      <input type="text" name="steuer_name" required placeholder="Standard (19 %)"></div>
    <div class="bk-feld"><label>Satz in Prozent</label>
      <input type="text" name="steuer_satz" required placeholder="19" inputmode="decimal"></div>
    <div class="bk-feld"><label>Land</label>
      <input type="text" name="steuer_land" value="DE" maxlength="2"></div>
  </div>
  <label class="bk-haken"><input type="checkbox" name="steuer_standard" value="1">
    <span>Als Standardsatz verwenden</span></label>
  <button class="bk-knopf bk-knopf-voll bk-knopf-klein" type="submit">Hinzufügen</button>
</form>
