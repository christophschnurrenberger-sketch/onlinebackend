/*
 * Storefront-JavaScript.
 *
 * Reine Verbesserung: Variantenauswahl, Mengenknöpfe und mobiles Menü. Jede
 * dieser Aktionen existiert auch als normales Formular, der Shop bleibt also
 * ohne JavaScript vollständig benutzbar.
 */
(function () {
  'use strict';

  /* --- Mobiles Menü ------------------------------------------------------ */

  var schalter = document.querySelector('.menue-schalter');
  var menue = document.getElementById('hauptmenue');
  if (schalter && menue) {
    schalter.addEventListener('click', function () {
      var offen = menue.classList.toggle('offen');
      schalter.setAttribute('aria-expanded', String(offen));
    });
  }

  /* --- Mengenknöpfe ------------------------------------------------------ */

  document.addEventListener('click', function (e) {
    var knopf = e.target.closest('[data-menge]');
    if (!knopf) return;
    var feld = knopf.parentElement.querySelector('input');
    if (!feld) return;
    var neu = Math.max(1, (parseInt(feld.value, 10) || 1) + parseInt(knopf.dataset.menge, 10));
    feld.value = String(neu);
    // In Warenkorbzeilen sofort übernehmen, damit die Summe stimmt.
    if (knopf.dataset.absenden === '1') feld.form.submit();
  });

  /* --- Artikelseite: Varianten ------------------------------------------- */

  var seite = document.querySelector('[data-artikel]');
  if (!seite) return;

  var varianten = [];
  try {
    varianten = JSON.parse(seite.dataset.varianten || '[]');
  } catch (e) {
    return;
  }

  var waehrung = document.documentElement.dataset.waehrung || 'EUR';
  var geld = function (cent) {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: waehrung }).format((cent || 0) / 100);
  };

  var auswahl = [];
  seite.querySelectorAll('[data-optionswert][aria-pressed="true"]').forEach(function (b) {
    auswahl[parseInt(b.dataset.optionsindex, 10) - 1] = b.dataset.optionswert;
  });

  function passendeVariante() {
    return varianten.find(function (v) {
      return v.optionen.every(function (wert, i) {
        return auswahl[i] === undefined || auswahl[i] === wert;
      });
    });
  }

  function aktualisieren() {
    var v = passendeVariante();
    var feld = seite.querySelector('[data-variantenfeld]');
    var preis = seite.querySelector('[data-preis]');
    var hinweis = seite.querySelector('[data-lagerhinweis]');
    var knopf = seite.querySelector('[data-kaufknopf]');
    var nummer = seite.querySelector('[data-artikelnummer]');
    var bild = seite.querySelector('[data-hauptbild]');

    if (!v) {
      if (knopf) { knopf.disabled = true; knopf.textContent = 'Nicht verfügbar'; }
      return;
    }
    if (feld) feld.value = v.id;

    if (preis) {
      var sale = v.streichpreis && v.streichpreis > v.preis;
      preis.innerHTML = '<span class="preis-jetzt' + (sale ? ' preis-sale' : '') + '">' + geld(v.preis) + '</span>'
        + (sale ? '<span class="preis-vorher">' + geld(v.streichpreis) + '</span>' : '');
    }

    var frei = v.verfuegbar;
    if (hinweis) {
      hinweis.className = 'lagerhinweis ' +
        (frei === 0 ? 'lager-aus' : (frei !== null && frei <= 5 ? 'lager-knapp' : 'lager-da'));
      hinweis.textContent =
        frei === null ? 'Sofort lieferbar'
        : frei <= 0 ? 'Ausverkauft'
        : frei <= 5 ? 'Nur noch ' + frei + ' auf Lager'
        : 'Auf Lager – sofort lieferbar';
    }
    if (knopf) {
      knopf.disabled = frei === 0;
      knopf.textContent = frei === 0 ? 'Ausverkauft' : 'In den Warenkorb';
    }
    if (nummer && v.artikelnummer) nummer.textContent = 'Art.-Nr.: ' + v.artikelnummer;
    if (bild && v.bild) bild.src = v.bild;
  }

  seite.querySelectorAll('[data-optionswert]').forEach(function (knopf) {
    knopf.addEventListener('click', function () {
      var index = parseInt(knopf.dataset.optionsindex, 10);
      auswahl[index - 1] = knopf.dataset.optionswert;
      seite.querySelectorAll('[data-optionsindex="' + index + '"]').forEach(function (anderer) {
        anderer.setAttribute('aria-pressed', String(anderer === knopf));
      });
      aktualisieren();
    });
  });

  seite.querySelectorAll('[data-kleinbild]').forEach(function (klein) {
    klein.addEventListener('click', function () {
      var gross = seite.querySelector('[data-hauptbild]');
      if (gross) gross.src = klein.dataset.kleinbild;
      seite.querySelectorAll('[data-kleinbild]').forEach(function (a) {
        a.setAttribute('aria-current', String(a === klein));
      });
    });
  });

  aktualisieren();
})();
