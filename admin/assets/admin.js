/*
 * Backend-JavaScript.
 *
 * Kleine Hilfen, ohne die das Backend ebenfalls vollständig bedienbar bleibt:
 * Rückfragen vor dem Löschen, Farbwähler, Handle-Vorschlag aus dem Titel und
 * das Hinzufügen von Zeilen in Varianten- und Regellisten.
 */
(function () {
  'use strict';

  /* --- Rückfrage vor heiklen Aktionen ------------------------------------ */

  document.addEventListener('submit', function (e) {
    var frage = e.target.dataset ? e.target.dataset.frage : null;
    if (frage && !window.confirm(frage)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-frage]');
    if (el && el.tagName === 'A' && !window.confirm(el.dataset.frage)) e.preventDefault();
  });

  /* --- Farbwähler mit Textfeld koppeln ----------------------------------- */

  document.querySelectorAll('.bk-farbe').forEach(function (gruppe) {
    var wahl = gruppe.querySelector('input[type=color]');
    var text = gruppe.querySelector('input[type=text]');
    if (!wahl || !text) return;
    wahl.addEventListener('input', function () { text.value = wahl.value; });
    text.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) wahl.value = text.value;
    });
  });

  /* --- Handle aus dem Titel vorschlagen ---------------------------------- */

  var titel = document.querySelector('[data-titel-quelle]');
  var handle = document.querySelector('[data-handle-ziel]');
  if (titel && handle && handle.value === '') {
    titel.addEventListener('input', function () {
      handle.value = titel.value.toLowerCase()
        .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    });
  }

  /* --- Zeilen hinzufügen (Varianten, Regeln, Menüpunkte) ------------------ */

  document.querySelectorAll('[data-zeile-hinzu]').forEach(function (knopf) {
    knopf.addEventListener('click', function () {
      var ziel = document.querySelector(knopf.dataset.zeileHinzu);
      var vorlage = document.querySelector(knopf.dataset.vorlage);
      if (!ziel || !vorlage) return;
      // Die Vorlage trägt __N__ als Platzhalter für den Zeilenindex.
      var nummer = ziel.children.length;
      var html = vorlage.innerHTML.replace(/__N__/g, String(nummer + 1000));
      var huelle = document.createElement('div');
      huelle.innerHTML = html;
      while (huelle.firstElementChild) ziel.appendChild(huelle.firstElementChild);
    });
  });

  document.addEventListener('click', function (e) {
    var weg = e.target.closest('[data-zeile-weg]');
    if (!weg) return;
    var zeile = weg.closest(weg.dataset.zeileWeg);
    if (zeile) zeile.remove();
    document.dispatchEvent(new CustomEvent('vorschau:neu'));
  });

  /* --- Suchfelder mit kurzer Verzögerung absenden ------------------------- */

  document.querySelectorAll('[data-auto-suche]').forEach(function (feld) {
    var timer;
    feld.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { feld.form.submit(); }, 400);
    });
  });

  /* --- Bestandsfeld beim Verlassen speichern ----------------------------- */

  document.querySelectorAll('[data-bestand-feld]').forEach(function (feld) {
    var alt = feld.value;
    feld.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); feld.blur(); } });
    feld.addEventListener('blur', function () { if (feld.value !== alt) feld.form.submit(); });
  });

  /* --- Bausteine: sortieren und hinzufügen -------------------------------- */

  /*
   * Sortiert wird mit der eingebauten Ziehen-und-Ablegen-Schnittstelle des
   * Browsers – keine Bibliothek, kein Berührungs-Sonderfall, den wir selbst
   * bauen müssten. Nach jedem Ablegen werden die versteckten Positionsfelder
   * neu durchnummeriert; die Reihenfolge im Formular ist dem Server egal.
   */
  var behaelter = document.querySelector('[data-sortier]');
  if (behaelter) {
    var gezogen = null;

    behaelter.addEventListener('dragstart', function (e) {
      var el = e.target.closest('[data-sortier-element]');
      if (!el) return;
      /*
       * Aus einem Eingabefeld heraus wird nicht gezogen. Chromium entscheidet
       * schon beim Drücken der Maustaste, ob ein Ziehen beginnt – das Attribut
       * später zu setzen kommt zu spät, hier abzubrechen geht.
       */
      if (e.target.closest('input, textarea, select, button, a')) {
        e.preventDefault();
        return;
      }
      gezogen = el;
      el.classList.add('wird-gezogen');
      e.dataTransfer.effectAllowed = 'move';
      // Firefox überträgt sonst nichts und bricht das Ziehen ab.
      e.dataTransfer.setData('text/plain', '');
    });

    behaelter.addEventListener('dragend', function () {
      if (gezogen) gezogen.classList.remove('wird-gezogen');
      gezogen = null;
      nummerieren();
    });

    behaelter.addEventListener('dragover', function (e) {
      if (!gezogen) return;
      e.preventDefault();
      var ziel = e.target.closest('[data-sortier-element]');
      if (!ziel || ziel === gezogen) return;
      var kasten = ziel.getBoundingClientRect();
      var obereHaelfte = e.clientY < kasten.top + kasten.height / 2;
      behaelter.insertBefore(gezogen, obereHaelfte ? ziel : ziel.nextSibling);
    });

    function nummerieren() {
      var felder = behaelter.querySelectorAll('[data-sortier-pos]');
      for (var i = 0; i < felder.length; i++) felder[i].value = String(i);
      // Die Vorschau hängt an der Reihenfolge, nicht nur an den Texten.
      document.dispatchEvent(new CustomEvent('vorschau:neu'));
    }

    /* Verschieben per Knopf – auf dem Telefon die einzige Möglichkeit. */
    behaelter.addEventListener('click', function (e) {
      var hoch = e.target.closest('[data-sortier-hoch]');
      var runter = e.target.closest('[data-sortier-runter]');
      if (!hoch && !runter) return;
      var karte = e.target.closest('[data-sortier-element]');
      if (!karte) return;
      if (hoch && karte.previousElementSibling) {
        behaelter.insertBefore(karte, karte.previousElementSibling);
      } else if (runter && karte.nextElementSibling) {
        behaelter.insertBefore(karte.nextElementSibling, karte);
      }
      nummerieren();
      karte.scrollIntoView({ block: 'nearest' });
    });

    /*
     * Neue Bausteine kommen aus der Kachelauswahl unter der Liste. Die
     * Vorlage steht als <template> in der Seite, gebaut vom selben PHP-Code
     * wie die vorhandenen Karten – ein zweiter Satz Formularfelder in
     * JavaScript wäre eine Fehlerquelle, die sich nie wieder schließt.
     */
    document.addEventListener('click', function (e) {
      var knopf = e.target.closest('[data-baustein-hinzu]');
      if (!knopf) return;
      var vorlage = document.getElementById('bs-vorlage-' + knopf.dataset.bausteinHinzu);
      if (!vorlage) return;
      // Fortlaufende Nummer, damit die Feldnamen sich nie überschneiden.
      var nummer = Date.now() % 100000;
      var html = vorlage.innerHTML.split('__I__').join(String(nummer));
      var huelle = document.createElement('div');
      huelle.innerHTML = html;
      var karte = huelle.querySelector('[data-sortier-element]');
      if (!karte) return;
      behaelter.appendChild(karte);
      nummerieren();
      var leer = document.getElementById('bausteine-leer');
      if (leer) leer.remove();
      karte.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Ins erste Feld springen: dann zeigt die Vorschau gleich, wo der
      // neue Baustein gelandet ist.
      var erstes = karte.querySelector('input[type=text], textarea');
      if (erstes) erstes.focus({ preventScroll: true });
    });
  }

  /* --- Live-Vorschau ------------------------------------------------------ */

  /*
   * Der Baukasten zeigt rechts, was links entsteht.
   *
   * Statt die Seite im Backend nachzubauen, schickt die Vorschau den
   * ungespeicherten Formularstand an admin/vorschau.php und zeigt dessen
   * Antwort in einem Rahmen. Damit ist die Vorschau nicht "so ähnlich wie der
   * Shop", sondern derselbe Programmcode – eine Abweichung kann gar nicht
   * entstehen.
   *
   * Der Rahmen wird in Originalbreite gebaut (1280 px) und heruntergerechnet.
   * Ein schmal gerenderter Rahmen würde das Telefon-Layout zeigen und damit
   * das Falsche.
   */
  var vk = document.querySelector('[data-vorschau]');
  var vformular = document.getElementById('inhaltform');
  if (vk && vformular && window.fetch && 'srcdoc' in document.createElement('iframe')) {
    var vrahmen   = vk.querySelector('[data-vorschau-rahmen]');
    var vbuehne   = vk.querySelector('[data-vorschau-buehne]');
    var vstand    = vk.querySelector('[data-vorschau-stand]');
    var vschleier = vk.querySelector('[data-vorschau-schleier]');
    var vziel     = vk.dataset.vorschauZiel;

    var vbreite = 1280;      // Breite, in der gerendert wird
    var vrollen = 0;         // zuletzt gemeldete Blätterhöhe im Rahmen
    var vaktiv  = null;      // Baustein, der gerade bearbeitet wird
    var vletzte = null;      // zuletzt gesendeter Formularstand
    var vtimer  = null;
    var vabbruch = null;

    function vpassen() {
      var platz = vbuehne.clientWidth;
      var faktor = Math.min(1, platz / vbreite);
      vrahmen.style.width = vbreite + 'px';
      vrahmen.style.height = Math.ceil(vbuehne.clientHeight / faktor) + 'px';
      vrahmen.style.transform = 'scale(' + faktor + ')';
      vrahmen.style.left = Math.max(0, Math.round((platz - vbreite * faktor) / 2)) + 'px';
    }

    function vdaten() {
      return new URLSearchParams(new FormData(vformular)).toString();
    }

    function vbauen(erzwingen) {
      var koerper = vdaten();

      /*
       * Welcher Baustein gerade bearbeitet wird, steht im Fokus – nicht in
       * einer eigenen Buchführung. Nach dem Umsortieren wäre eine gemerkte
       * Nummer falsch, der Fokus stimmt immer.
       */
      var fokus = document.activeElement;
      var offen = (fokus && fokus.closest) ? fokus.closest('[data-sortier-element]') : null;
      var marke = offen ? offen.querySelector('[data-sortier-pos]') : null;
      vaktiv = marke ? Number(marke.value) : null;

      if (!erzwingen && koerper === vletzte) return;
      vletzte = koerper;

      if (vabbruch) vabbruch.abort();
      vabbruch = ('AbortController' in window) ? new AbortController() : null;

      vschleier.hidden = false;
      vstand.textContent = 'wird aufgebaut …';

      fetch(vziel, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: koerper,
        signal: vabbruch ? vabbruch.signal : undefined
      }).then(function (antwort) {
        return antwort.text();
      }).then(function (html) {
        vrahmen.srcdoc = html;
        vschleier.hidden = true;
        vstand.textContent = 'Stand von ' + new Date().toLocaleTimeString('de-DE');
      }).catch(function (fehler) {
        if (fehler && fehler.name === 'AbortError') return;
        vschleier.hidden = true;
        vstand.textContent = 'Vorschau nicht erreichbar.';
        // Beim nächsten Versuch nicht wegen "unverändert" abbrechen.
        vletzte = null;
      });
    }

    /*
     * Nach dem Aufbau den alten Zustand wiederherstellen: erst die Markierung
     * (ohne zu springen), dann die Blätterhöhe. Andernfalls stünde die
     * Vorschau nach jedem Tastendruck wieder ganz oben.
     */
    vrahmen.addEventListener('load', function () {
      var fenster = vrahmen.contentWindow;
      if (!fenster) return;
      if (vaktiv !== null) fenster.postMessage({ bsHervor: vaktiv, bsSpringen: false }, '*');
      if (vrollen > 0) fenster.postMessage({ bsRollen: vrollen }, '*');
    });

    /* Tippen sammeln: erst wenn eine Sekunde Ruhe ist, wird neu gebaut. */
    function vspaeter() {
      clearTimeout(vtimer);
      vtimer = setTimeout(function () { vbauen(false); }, 700);
    }
    vformular.addEventListener('input', vspaeter);
    vformular.addEventListener('change', vspaeter);
    document.addEventListener('vorschau:neu', vspaeter);

    var vjetzt = vk.querySelector('[data-vorschau-neu]');
    if (vjetzt) {
      vjetzt.addEventListener('click', function () { clearTimeout(vtimer); vbauen(true); });
    }

    /* Bildschirm oder Telefon. */
    vk.querySelectorAll('[data-vorschau-breite]').forEach(function (knopf) {
      knopf.addEventListener('click', function () {
        vk.querySelectorAll('[data-vorschau-breite]').forEach(function (k) {
          k.classList.remove('bk-knopf-aktiv');
        });
        knopf.classList.add('bk-knopf-aktiv');
        vbreite = Number(knopf.dataset.vorschauBreite) || 1280;
        vpassen();
      });
    });

    /*
     * Vom Rahmen zurück ins Formular: Ein Klick in der Vorschau öffnet den
     * Baustein, der dort steht. Das ist der Teil, der aus einer Liste von
     * Feldern einen Baukasten macht.
     */
    window.addEventListener('message', function (e) {
      if (!e.data || e.source !== vrahmen.contentWindow) return;

      if (typeof e.data.bsRollstand === 'number') {
        vrollen = e.data.bsRollstand;
        return;
      }
      if (typeof e.data.bsSprung !== 'number') return;

      /*
       * Über den Wert suchen, nicht über das Attribut: nummerieren() setzt
       * die Eigenschaft, das HTML-Attribut bleibt dabei auf dem alten Stand.
       * Ein Selektor [value="3"] fände nach dem ersten Umsortieren die
       * falsche Karte.
       */
      var karte = null;
      var felder = vformular.querySelectorAll('[data-sortier-pos]');
      for (var i = 0; i < felder.length; i++) {
        if (Number(felder[i].value) === e.data.bsSprung) {
          karte = felder[i].closest('[data-sortier-element]');
          break;
        }
      }
      if (!karte) return;
      vformular.querySelectorAll('.bk-baustein-aktiv').forEach(function (k) {
        k.classList.remove('bk-baustein-aktiv');
      });
      karte.classList.add('bk-baustein-aktiv');
      karte.scrollIntoView({ behavior: 'smooth', block: 'center' });
      var feld = karte.querySelector('input[type=text], textarea');
      if (feld) feld.focus({ preventScroll: true });
    });

    /* Und umgekehrt: Wer ein Feld anfasst, sieht im Rahmen, wo es hingehört. */
    vformular.addEventListener('focusin', function (e) {
      var karte = e.target.closest('[data-sortier-element]');
      if (!karte || !vrahmen.contentWindow) return;
      var pos = karte.querySelector('[data-sortier-pos]');
      if (!pos) return;
      vformular.querySelectorAll('.bk-baustein-aktiv').forEach(function (k) {
        k.classList.remove('bk-baustein-aktiv');
      });
      karte.classList.add('bk-baustein-aktiv');
      vrahmen.contentWindow.postMessage({ bsHervor: Number(pos.value) }, '*');
    });

    /* Große Ansicht ein- und ausschalten. */
    var vgross = vk.querySelector('[data-vorschau-gross]');
    function vumschalten(an) {
      vk.classList.toggle('bk-vorschau-gross', an);
      document.body.classList.toggle('hat-vorschau-gross', an);
      if (vgross) {
        vgross.classList.toggle('bk-knopf-aktiv', an);
        vgross.textContent = an ? '⤡' : '⤢';
      }
      // Erst nach dem Umbruch messen, sonst steht die alte Höhe im Weg.
      requestAnimationFrame(vpassen);
    }
    if (vgross) {
      vgross.addEventListener('click', function () {
        vumschalten(!vk.classList.contains('bk-vorschau-gross'));
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && vk.classList.contains('bk-vorschau-gross')) vumschalten(false);
      });
    }

    window.addEventListener('resize', vpassen);
    vpassen();
    vbauen(true);
  }

})();
