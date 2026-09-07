<?php
/**
 * Die Live-Vorschau-Karte des Baukastens.
 *
 * Eingebunden von der Seitenmaske und von der Startseite. Der Rahmen zeigt
 * admin/vorschau.php – dieselbe Ausgabe wie im Shop, gefüttert mit dem
 * ungespeicherten Formularstand aus #inhaltform. Ohne JavaScript bleibt die
 * Karte leer und stört nicht; Speichern und Veröffentlichen laufen davon
 * unabhängig.
 */
?>
        <section class="bk-karte bk-vorschau" data-vorschau
                 data-vorschau-ziel="<?= Util::e(Config::url('admin/vorschau.php')) ?>">
          <div class="bk-karte-kopf">
            <h2>Vorschau</h2>
            <div class="bk-vorschau-schalter">
              <button class="bk-knopf bk-knopf-klein bk-knopf-aktiv" type="button"
                      data-vorschau-breite="1280">Bildschirm</button>
              <button class="bk-knopf bk-knopf-klein" type="button"
                      data-vorschau-breite="390">Telefon</button>
              <?php /* Auf einem 13-Zoll-Laptop ist die Spalte zu schmal, um
                       Text zu lesen. Der Knopf legt die Vorschau über die
                       ganze Fläche – die Felder bleiben darunter erhalten. */ ?>
              <button class="bk-knopf bk-knopf-klein bk-knopf-leer" type="button"
                      data-vorschau-gross title="Vorschau groß zeigen (Esc schließt)">⤢</button>
            </div>
          </div>
          <div class="bk-vorschau-buehne" data-vorschau-buehne>
            <iframe data-vorschau-rahmen title="Vorschau der Seite"
                    referrerpolicy="same-origin" loading="lazy"></iframe>
            <div class="bk-vorschau-schleier" data-vorschau-schleier hidden></div>
          </div>
          <div class="bk-vorschau-fuss">
            <span data-vorschau-stand>Vorschau wird geladen …</span>
            <button class="bk-knopf bk-knopf-klein bk-knopf-leer" type="button"
                    data-vorschau-neu>Neu aufbauen</button>
          </div>
        </section>
