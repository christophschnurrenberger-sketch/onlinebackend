<?php
/**
 * Live-Vorschau des Seitenbaukastens.
 *
 * Nimmt den aktuellen Stand aus dem Bearbeitungsformular entgegen – noch
 * ungespeichert – und gibt die Seite so aus, wie sie im Shop aussähe. Das
 * Backend zeigt das Ergebnis in einem Rahmen daneben.
 *
 * Bewusst dieselbe Ausgabe wie im Shop und keine nachgebaute Vorschau: eine
 * Vorschau, die anders aussieht als das Ergebnis, ist schlimmer als keine.
 * Aus demselben Grund laufen die Formularwerte durch dieselbe Reinigung wie
 * beim Speichern – die Vorschau zeigt, was ankommt, nicht, was getippt wurde.
 */

require __DIR__ . '/../lib/bootstrap.php';

Auth::verlangen('pflegen');
Auth::csrfPruefen();

/* Die Fassung, aus der Kopf, Fuß und Artikel kommen. */
try {
    $fassung = Theme::fassung();
} catch (Throwable $e) {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8">'
       . '<div style="font:15px/1.6 -apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:32px;color:#6d7175">'
       . '<strong style="color:#202223">Noch keine Vorschau möglich.</strong><br>'
       . 'Der Shop muss einmal veröffentlicht sein, damit Kopf, Fuß und Artikel feststehen.'
       . '</div>';
    exit;
}

/* Bausteine aus dem Formular – in derselben Reihenfolge wie beim Speichern. */
$roh = Util::postArray('bs');
usort($roh, static fn($a, $b): int => (int) ($a['pos'] ?? 0) <=> (int) ($b['pos'] ?? 0));

$bausteine = [];
foreach ($roh as $eintrag) {
    $typ = (string) ($eintrag['typ'] ?? '');
    if (isset(Bausteine::TYPEN[$typ])) {
        $bausteine[] = [
            'typ'   => $typ,
            'daten' => Bausteine::saeubern($typ, (array) ($eintrag['daten'] ?? [])),
        ];
    }
}

$titel  = Util::post('titel');
$inhalt = Util::sauberesHtml(Util::postRaw('inhalt'));

Theme::kopf(['titel' => $titel !== '' ? $titel : 'Vorschau', 'noindex' => true]);
?>
<style>
/*
 * Nur in der Vorschau: der Baustein, der links im Formular gerade bearbeitet
 * wird, bekommt einen Rahmen. So sieht man, welches Feld welchen Teil der
 * Seite betrifft. Beim Überfahren zeigt ein zarter Rahmen, dass sich hier
 * klicken lässt.
 */
[data-bs-nr] { scroll-margin-top: 90px; position: relative; }
[data-bs-nr]:hover { outline: 1px dashed rgba(0,0,0,.18); outline-offset: 4px; }
[data-bs-nr].bs-hervor { outline: 2px dashed rgba(0,0,0,.45); outline-offset: 6px; }
.bs-vorschau-leer {
  max-width: 640px; margin: 80px auto; padding: 0 24px; text-align: center;
  font-size: 15px; line-height: 1.7; opacity: .7;
}
</style>
<?php
if ($bausteine === [] && trim($inhalt) === '') {
    echo '<div class="bs-vorschau-leer"><p><strong>' . Util::e($titel !== '' ? $titel : 'Neue Seite') . '</strong></p>'
       . '<p>Noch nichts zu sehen. Links einen Baustein hinzufügen – die Vorschau folgt beim Tippen.</p></div>';
} elseif ($bausteine === []) {
    echo '<div class="behaelter"><article class="schmal">';
    echo '<h1>' . Util::e($titel) . '</h1>';
    echo '<div class="rte">' . $inhalt . '</div>';
    echo '</article></div>';
} else {
    foreach ($bausteine as $nr => $baustein) {
        // Jeder Baustein bekommt seine Nummer mit, damit das Backend ihn
        // ansteuern kann, wenn im Formular ein Feld den Fokus bekommt.
        echo '<div data-bs-nr="' . (int) $nr . '">';
        Bausteine::rendern($baustein, $fassung);
        echo '</div>';
    }
    if (trim($inhalt) !== '') {
        echo '<div class="behaelter"><article class="schmal"><div class="rte">' . $inhalt . '</div></article></div>';
    }
}

Theme::fuss();
?>
<script>
/*
 * Brücke zum Backend.
 *
 * Klick in der Vorschau springt zum passenden Baustein im Formular. Links
 * werden dabei nicht gefolgt: Die Vorschau soll die Seite zeigen, nicht durch
 * den Shop navigieren – ein Klick auf "Zum Shop" wäre sonst das Ende der
 * Vorschau. Formulare (Suche, Warenkorb) ebenso.
 */
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-bs-nr]');
  if (e.target.closest('a, button, [type=submit]')) e.preventDefault();
  if (el && window.parent !== window) {
    window.parent.postMessage({ bsSprung: Number(el.dataset.bsNr) }, '*');
  }
});
document.addEventListener('submit', function (e) { e.preventDefault(); });

window.addEventListener('message', function (e) {
  if (!e.data) return;
  if (typeof e.data.bsHervor === 'number') {
    var alle = document.querySelectorAll('[data-bs-nr]');
    for (var i = 0; i < alle.length; i++) alle[i].classList.remove('bs-hervor');
    var ziel = document.querySelector('[data-bs-nr="' + e.data.bsHervor + '"]');
    if (ziel) {
      ziel.classList.add('bs-hervor');
      // Nach einem Neuaufbau wird die Markierung nur wiederhergestellt; die
      // Blätterhöhe kommt gleich danach als eigene Nachricht und wäre durch
      // ein Springen hier schon wieder verstellt.
      if (e.data.bsSpringen !== false) {
        ziel.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }
  // Nach dem Neuaufbau die alte Blätterhöhe wiederherstellen: sonst springt
  // die Vorschau bei jedem Tastendruck nach oben.
  if (typeof e.data.bsRollen === 'number') {
    window.scrollTo(0, e.data.bsRollen);
  }
});

/* Meldet dem Backend die Blätterhöhe, damit sie den nächsten Aufbau übersteht. */
window.addEventListener('scroll', function () {
  if (window.parent !== window) {
    window.parent.postMessage({ bsRollstand: window.scrollY }, '*');
  }
}, { passive: true });
</script>
