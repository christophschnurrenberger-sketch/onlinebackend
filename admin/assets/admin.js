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
})();
