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

    var neu = document.querySelector('[data-baustein-hinzu]');
    var wahl = document.getElementById('bausteinwahl');
    if (neu && wahl) {
      neu.addEventListener('click', function () {
        var vorlage = document.getElementById('bs-vorlage-' + wahl.value);
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
      });
    }
  }

})();
