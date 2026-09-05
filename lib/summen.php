<?php
/**
 * Summenblock für Warenkorb, Kasse und Bestellung.
 * Erwartet $korb (Ergebnis von Warenkorb::inhalt() oder Preise::rechnen()).
 */
?>
<div class="summenzeile">
  <span>Zwischensumme</span>
  <span><?= Util::e(Util::geld((int) $korb['zwischensumme'])) ?></span>
</div>

<?php if ((int) $korb['rabatt'] > 0): ?>
  <div class="summenzeile">
    <span>Rabatt
      <?php if ((string) $korb['rabattcode'] !== ''): ?>
        <span class="nebentext">(<?= Util::e((string) $korb['rabattcode']) ?>)</span>
      <?php endif; ?>
    </span>
    <span>−<?= Util::e(Util::geld((int) $korb['rabatt'])) ?></span>
  </div>
<?php endif; ?>

<div class="summenzeile">
  <span>Versand</span>
  <span><?php
    if (!$korb['versandnoetig']) {
        echo '—';
    } elseif ((int) $korb['versandkosten'] === 0) {
        echo 'kostenlos';
    } else {
        echo Util::e(Util::geld((int) $korb['versandkosten']));
    }
  ?></span>
</div>

<div class="summenzeile gesamt">
  <span>Gesamt</span>
  <span><?= Util::e(Util::geld((int) $korb['gesamt'])) ?></span>
</div>

<?php foreach ($korb['steuerzeilen'] as $steuer): ?>
  <div class="summenzeile">
    <span class="nebentext">enthaltene MwSt. <?= Util::e(Steuern::prozentText((int) $steuer['satz_bp'])) ?></span>
    <span class="nebentext"><?= Util::e(Util::geld((int) $steuer['betrag'])) ?></span>
  </div>
<?php endforeach; ?>
