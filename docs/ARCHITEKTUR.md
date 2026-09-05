# Architektur

Warum das System so gebaut ist, wie es gebaut ist.

## Die drei Regeln

**Geld ist immer eine Ganzzahl in Cent.** `19,90 €` ist `1990`. Fließkomma auf
Preisen erzeugt genau die Rundungsfehler, die Kunden auf der Rechnung sehen und
die die Buchhaltung nicht ausgleichen kann. Gerundet wird ausschließlich in
`lib/Util.php`; `Util::verteilen()` teilt einen Betrag proportional auf
Positionen auf, ohne dass dabei Cent verloren gehen — der Rest wandert an die
Positionen mit dem größten Rundungsverlust.

**Preise berechnet genau eine Stelle.** `lib/Preise.php` bedient
Warenkorb-Anzeige, Kassenseite und die gespeicherte Bestellung. Gäbe es zwei
Rechenwege, würden sie irgendwann um einen Cent auseinanderlaufen — und das
sieht der Kunde als Erster.

**Bestellungen sind Dokumente, keine Sicht auf den Katalog.** Titel,
Artikelnummer, Preis und Steuersatz werden in die Bestellzeile kopiert. Ändert
der Betreiber später den Preis oder benennt den Artikel um, bleibt die
Bestellung so, wie der Kunde sie abgeschlossen hat. Das ist handelsrechtlich
nötig und macht Belege reproduzierbar.

## Schichten

```
*.php (Wurzel)  Shopseiten: Anfrage lesen, Klassen aufrufen, HTML ausgeben
admin/*.php     Backend-Seiten, gleiches Muster
lib/            Alles andere:
                  Config, DB, Schema, Util, Settings, Log, Auth   Grundlage
                  Artikel, Kategorien, Inhalte, Kunden,
                  Bestellungen, Rabatte, Versand, Steuern,
                  Bestand, Medien                                 Fachdaten
                  Preise, Warenkorb, Kasse, Zahlung, Mail,
                  Veroeffentlichung                               Abläufe
                  Theme                                           Darstellung
```

Die Richtung ist einbahnig: Seiten rufen Klassen, Klassen rufen `DB`. Eine
Seite enthält nie eine Preisberechnung, eine Fachklasse nie HTML.

Klassen werden ohne Composer geladen — `lib/bootstrap.php` bindet sie der Reihe
nach ein. Das ist bei rund 25 Klassen völlig ausreichend und erspart eine
Abhängigkeit, die auf günstigem Hosting oft Ärger macht.

## Datenmodell

Die kaufbare Einheit ist die **Variante**, nicht der Artikel. Preis und Bestand
hängen an ihr. Auch ein Artikel ohne Optionen hat genau eine Standardvariante —
das erspart im Warenkorb, an der Kasse und in der Bestellung jede
Sonderbehandlung von „Artikel ohne Varianten“.

```
artikel ──┬── artikel_bilder
          ├── artikel_optionen      (die Achsen: Größe, Farbe …)
          └── varianten ────────────┬── bestandsbewegungen   (Journal)
                                    └── bestellzeilen

kategorien ── kategorie_artikel     (nur bei manuellen Kategorien)
              Regeln automatischer Kategorien liegen als JSON in der
              Kategorie und werden beim Lesen ausgewertet

warenkoerbe ── warenkorb_zeilen     speichern nur Variante und Menge, nie Preise
bestellungen ─┬── bestellzeilen
              ├── bestellereignisse  der Verlauf, den das Backend zeigt
              ├── sendungen
              ├── erstattungen
              └── zahlungen

veroeffentlichungen                 die eingefrorenen Fassungen
```

Bestandteile, die auffallen könnten:

- **`warenkoerbe` speichert keine Preise.** Preise kommen bei jedem Aufruf frisch
  aus der Berechnung — sonst könnte ein alter Warenkorb eine Preisänderung
  unterlaufen.
- **`bestandsbewegungen` ist ein Journal.** Jede Bestandsänderung hinterlässt
  eine Zeile mit Grund, Bestellung und Benutzer. Ohne Journal ist Lagerhaltung
  nicht nachvollziehbar; die Frage „warum stehen hier drei?“ bliebe
  unbeantwortbar.
- **`webhooks` speichert vor der Verarbeitung.** Schlägt sie fehl, ist die
  Meldung trotzdem dokumentiert. Die Eindeutigkeit von `(anbieter, ereignis_id)`
  macht die Verarbeitung idempotent.

Das Schema ist einmal generisch formuliert und wird je nach Datenbank übersetzt
(`%PK%`, `%INT%`, `%STR(n)%`, `%TEXT%`, `%DT%`). Deshalb läuft dasselbe System
auf SQLite und auf MySQL. `Schema::migrate()` ist idempotent und ergänzt beim
nächsten Seitenaufruf, was nach einem Update fehlt.

## Die veröffentlichte Fassung

`lib/Veroeffentlichung.php` serialisiert bei jedem Veröffentlichen den kompletten
veröffentlichungsfähigen Zustand nach JSON und legt ihn mit einer Versionsnummer
ab. Der Shop liest ausschließlich aus der Fassung, die gerade `live = 1` trägt.

**Was hineingeht:** aktive Artikel mit Varianten und Bildern, sichtbare
Kategorien (Regeln werden dabei zu festen Artikellisten aufgelöst),
veröffentlichte Seiten und Beiträge, Menüs und alle Einstellungen.

**Was bewusst draußen bleibt:** Bestände, Warenkörbe, Bestellungen, Kunden und
Rabattzähler. Die müssen sofort wirken. Ein ausverkaufter Artikel darf nicht bis
zur nächsten Veröffentlichung weiterverkauft werden — deshalb liest der Shop
Verfügbarkeiten direkt aus der Datenbank, auch wenn der Rest der Seite aus der
Fassung kommt.

Die Alternative wäre gewesen, im Shop einfach auf `status = 'aktiv'` zu filtern.
Das hätte drei Dinge gekostet: das gefahrlose Arbeiten an aktiven Artikeln, den
Rollback und die Änderungsliste vor dem Veröffentlichen.

## Der Bestell-Ablauf

```
1. Prüfen        E-Mail, Adresse, AGB, Zahlart, Warenkorb nicht leer,
                 Bestand reicht, Mindestbestellwert erreicht

2. Neu bepreisen Die Adresse bestimmt das Land und damit den Versandpreis

3. Transaktion   Kunde anlegen oder ergänzen
                 Bestellung schreiben
                 Bestand ausbuchen  ← wirft, wenn jemand schneller war;
                                      die Transaktion nimmt dann alles zurück
                 Rabattzähler erhöhen

4. Zahlung       Außerhalb der Transaktion: ein Aufruf zu Stripe oder PayPal
                 darf keine offene Datenbanktransaktion blockieren

5. Ergebnis      'fertig' → Dankeseite, oder 'weiterleiten' → zum Anbieter
```

Der Bestand wird schon in Schritt 3 gebucht, nicht erst nach der Zahlung: sonst
könnten zwei Kunden im Zahlungsfenster dasselbe letzte Stück kaufen. Bricht die
Zahlung ab, gibt `Kasse::abbrechen()` die Ware wieder frei und storniert die
Bestellung.

## Zahlungsanbieter

Alle Zahlarten liegen in `lib/Zahlung.php` hinter derselben Schnittstelle:

```php
Zahlung::verfuegbare()               // was an der Kasse erscheint
Zahlung::starten($bestellung, $art)  // ['aktion' => 'fertig'|'weiterleiten', …]
Zahlung::bestaetigen($bestellung)    // Status beim Anbieter nachfragen
Zahlung::erstatten($bestellung, $betrag)
```

`starten()` kennt genau zwei Ausgänge — der Kunde geht zum Anbieter, oder die
Bestellung ist sofort abschließbar. Mehr Fälle braucht es nicht, und der
Kassencode bleibt dadurch geradlinig.

Zwei Regeln gelten für jeden Webhook: **Signatur prüfen, bevor irgendetwas
gebucht wird**, und **jede Ereignis-ID nur einmal verarbeiten**.

HTTP-Aufrufe laufen über cURL, mit Streams als Rückfallweg — manche günstigen
Hoster haben cURL nicht aktiviert, und ohne Rückfall wäre dort keine
Onlinezahlung möglich.

## Sicherheit

| Angriff | Gegenmaßnahme |
|---|---|
| Passwort-Diebstahl aus der Datenbank | `password_hash()` mit Salt je Passwort |
| Session-Übernahme | Zufälliges Token, HttpOnly, SameSite=Lax, serverseitig gespeichert — Abmelden wirkt sofort |
| CSRF | Sitzungsgebundenes Token bei jeder schreibenden Aktion |
| XSS über den Rich-Text | `Util::sauberesHtml()` beim Speichern: Whitelist für Tags und Attribute, `javascript:`- und `data:`-Adressen raus |
| XSS über Templates | Jede Ausgabe durch `Util::e()` |
| SQL-Injection | Ausschließlich vorbereitete Anweisungen mit typisierten Parametern |
| Direkter Zugriff auf `config.php`, `lib/`, `data/` | `.htaccess` (Apache) sperrt sie |
| Bösartige Uploads | Typprüfung am Dateiinhalt, Größenlimit, serverseitig vergebener Dateiname, PHP im Upload-Ordner abgeschaltet |
| Gefälschte Zahlungsbestätigung | HMAC-Signaturprüfung (Stripe), Rückfrage beim Anbieter (PayPal) |
| Timing-Angriff auf den Login | Auch ohne Treffer wird ein Hash geprüft; Vergleiche laufen zeitkonstant |
| Preismanipulation vom Kunden | Der Browser schickt nur Varianten-ID und Menge; alle Preise kommen vom Server |
| Überverkauf durch Parallelzugriff | Bestandsbuchung in derselben Transaktion wie die Bestellung |
| Offener Weiterleiter beim Login | Nur eigene Pfade als Ziel zugelassen |

Zugangsdaten für Stripe, PayPal und SMTP liegen mit AES-256 verschlüsselt in der
Datenbank; der Schlüssel steht in `config.php`. Wer die Datenbank kopiert, hat
sie damit noch nicht.

## Grenzen

Die Architektur trägt einen Shop bis in den mittleren fünfstelligen Bereich an
Artikeln und Bestellungen pro Jahr auf gewöhnlichem Webhosting. Wo sie endet:

- **SQLite schreibt seriell.** Bei Dutzenden gleichzeitigen Bestellungen pro
  Sekunde wäre MySQL angebracht — das System kann beides, der Wechsel ist eine
  Änderung in `config.php` plus Datenübernahme.
- **Keine Bildskalierung.** Siehe [THEME.md](THEME.md).
- **Eine Sprache, eine Währung.** Mehrsprachigkeit hieße: übersetzbare Felder je
  Sprache und ein Sprachsegment in den Adressen.
- **Die Suche ist eine lineare Suche** über die Fassung. Bis einige tausend
  Artikel ist das schneller als jeder Index; darüber wäre eine Volltextsuche in
  der Datenbank der nächste Schritt.
- **Kein Kundenkonto.** Kunden bestellen als Gast und rufen ihre Bestellung über
  einen langen Zufallslink auf. Für die meisten kleinen Shops ist das genug und
  spart die Verwaltung von Kundenpasswörtern.
