# Shop-System

Ein vollständiges Shop-System für den eigenen Webspace. Im **Backend** pflegst du
Artikel, Kategorien, Inhalte und Einstellungen. Mit einem Klick auf
**Veröffentlichen** geht dieser Stand in den **Shop** — vorher sieht ihn niemand.

Zahlungen, Versandzonen, Steuern, Rabatte, Bestandsführung, Bestellabwicklung
und Rechtstexte sind eingebaut. Für das Aussehen liegen vier fertige Stile bei —
**Golf**, **Fahrrad**, **Pferdesport** und **Kräuter** —, umschaltbar im Backend
unter *Design*.

```
┌──────────────────┐   Veröffentlichen   ┌──────────────────┐
│     Backend      │  ─────────────────▶ │      Shop        │
│  /admin          │   Fassung 1, 2, 3…  │  index.php       │
│  Entwürfe        │                     │  nur Veröffent-  │
│  Arbeitsstand    │  ◀─── Rollback ──── │  lichtes         │
└──────────────────┘                     └──────────────────┘
        │                                          │
        └──────────── eine Datenbank ──────────────┘
           Bestände, Warenkörbe und Bestellungen
           laufen daran vorbei und wirken sofort
```

## Installation

Gebaut für ganz normales Webhosting — kein Composer, kein Node, keine
Kommandozeile.

1. **Hochladen.** Alle Dateien per FTP in den gewünschten Ordner, zum Beispiel
   `/shop` oder direkt ins Hauptverzeichnis. Dabei die **versteckten Dateien**
   nicht vergessen: Die `.htaccess`-Dateien beginnen mit einem Punkt und werden
   von FTP-Programmen oft ausgeblendet (FileZilla: *Server → Versteckte Dateien
   anzeigen*).
2. **Rechte setzen.** Die Ordner `data` und `uploads` sowie der Shop-Ordner selbst
   müssen beschreibbar sein — im FTP-Programm auf **755** (bei manchen Hostern 775).
3. **`systemcheck.php` aufrufen.** Zeigt, ob dein Server alles mitbringt, und
   benennt konkret, was fehlt.
4. **`install.php` aufrufen.** Ein Formular, zwei Minuten: Shopname, dein Zugang,
   Datenbank. Der Rest wird angelegt.
5. **`install.php` löschen.** Das Backend erinnert dich daran.

> **Aktualisierst du eine ältere Fassung?** Überschreiben allein reicht nicht:
> Dein FTP-Programm löscht nichts. Eine übrig gebliebene `index.html` wird von
> Apache bevorzugt ausgeliefert, noch vor der `index.php` — dann siehst du die
> alte Oberfläche und in der Browser-Konsole `404`-Meldungen für Dateien, die es
> nicht mehr gibt. Ruf danach einmal **`aufraeumen.php`** auf: Sie listet die
> Reste auf und entfernt sie auf Klick. `config.php`, `data/` und `uploads/`
> rührt sie nicht an.

> **„Internal Server Error“ nach dem Hochladen?** Das kommt von Apache, nicht
> vom Shop. Benenne die `.htaccess` im Shop-Ordner testweise in `htaccess.txt`
> um und lade neu. Ist der Fehler weg, erlaubt dein Hoster keine eigenen
> Apache-Regeln — lass sie umbenannt und folge Abschnitt 5 in
> [docs/BETRIEB.md](docs/BETRIEB.md). Bleibt der Fehler, liegt es an PHP;
> dann hilft `systemcheck.php`.

> **Nicht jedes Mal per FTP?** `.github/workflows/webspace.yml` lädt den Shop
> nach jedem Push automatisch auf den Webspace — mit Syntaxprüfung vorweg und
> ohne `config.php`, `data/` und `uploads/` anzurühren. Einzurichten sind nur
> drei Zugangsdaten in den GitHub-Einstellungen, siehe Abschnitt 9 in
> [docs/BETRIEB.md](docs/BETRIEB.md).

**Voraussetzungen:** PHP 8.1 oder neuer, dazu PDO mit SQLite *oder* MySQL.
Beides bringt praktisch jeder Hoster mit. Fehlt etwas, sagt der Systemcheck,
wo im Hosting-Menü du es umstellst.

**SQLite oder MySQL?** SQLite ist eine einzelne Datei, braucht keine Einrichtung
und reicht für die allermeisten Shops. MySQL lohnt sich erst bei sehr vielen
gleichzeitigen Besuchern. Der Installer bietet beides an; wechseln kannst du
später über `config.php`.

Nach der Installation:

| Adresse | Was |
|---|---|
| `dein-shop.de/shop/` | Der Shop |
| `dein-shop.de/shop/admin/` | Das Backend |

## Der Veröffentlichungs-Ablauf

Das ist der Kern des Systems und der Grund für einige seiner Entwurfsentscheidungen.

**Beim Veröffentlichen** wird der gesamte veröffentlichungsfähige Zustand —
aktive Artikel, sichtbare Kategorien, Seiten, Beiträge, Menüs, Design und
Stammdaten — als Fassung eingefroren und mit einer Versionsnummer gespeichert.
Der Shop liest **ausschließlich** aus der aktuell live geschalteten Fassung.

Das hat vier praktische Folgen:

1. **Entwürfe sind sicher.** Du kannst einen Artikel über Tage bearbeiten,
   Preise ausprobieren und Texte umbauen — im Shop ändert sich nichts, bis du
   veröffentlichst.
2. **Veröffentlichen ist ein Ereignis** mit Datum, Version und Urheber. Vor dem
   Klick zeigt das Backend, was sich seit dem letzten Mal geändert hat.
3. **Rollback ist ein Klick.** Eine ältere Fassung wieder live zu schalten
   dauert eine Sekunde.
4. **Der Shop ist schnell.** Katalogseiten kommen aus einer fertigen Struktur,
   kein langsamer Katalog-Query bremst die Kasse aus.

**Was bewusst nicht über die Fassung läuft:** Bestände, Warenkörbe,
Bestellungen, Kunden, Rabattzähler und Bewertungen. Die müssen sofort wirken —
ein ausverkaufter Artikel darf nicht bis zur nächsten Veröffentlichung
weiterverkauft werden, und eine freigegebene Bewertung soll sofort dastehen.

## Was drin ist

**Katalog** — Artikel mit Varianten (bis zu drei Optionsachsen wie Größe und
Farbe), Bildern, Streichpreisen, Artikelnummer, Gewicht, eigenem Steuersatz und
SEO-Feldern. Bestandsführung je Variante mit wählbarer Politik (Verkauf stoppen
oder Überverkauf erlauben) und lückenlosem Journal. Kategorien manuell
zusammengestellt oder regelbasiert (Schlagwort, Typ, Hersteller, Titel, Preis).

**Verkauf** — Warenkorb, Kasse, Bestellungen mit Zahlungs- und Versandstatus,
Teilversand mit Sendungsverfolgung, Teil- und Vollerstattung, Storno mit
Bestandsrückgabe, Ereignisverlauf je Bestellung, fortlaufende Bestellnummern.

**Zahlungen** — Stripe (Karte), PayPal, Kauf auf Rechnung, Vorkasse, Nachnahme
und eine Testzahlung für die Einrichtung. Alle hinter einer gemeinsamen
Schnittstelle; Webhooks werden signaturgeprüft und nur einmal verarbeitet.

**Preise & Steuern** — Bruttopreise nach EU-Praxis, Steuer als enthaltener
Anteil, mehrere Sätze, Versandzonen mit Tarifen und Versandkostenfreiheit ab
Schwellenwert, Rabattcodes (Prozent, Betrag, Gratisversand) mit
Mindestbestellwert, Laufzeit, Nutzungslimit und „einmal pro Kunde“.

**Inhalte** — Seiten und Startseite als visueller Baukasten: Bausteine werden
aus Kacheln ausgewählt, mit der Maus umsortiert und direkt daneben in einer
Live-Vorschau kontrolliert, die dieselbe Ausgabe zeigt wie der Shop. Dazu
Journal-Beiträge, zweistufige Navigation, Bildupload. Impressum, AGB,
Datenschutz und Widerruf legt der Installer als Entwürfe an.

**Bewertungen** — Sterne in der Artikelliste, Notenverteilung und Formular auf
der Artikelseite, Baustein „Kundenstimmen“ für Start- und Inhaltsseiten.
„Verifizierter Kauf“ wird automatisch vergeben, wenn zur E-Mail-Adresse eine
bezahlte Bestellung des Artikels vorliegt (§ 5b Abs. 3 UWG). Moderation gegen
Spam und Beleidigung, öffentliche Antwort je Bewertung — das Backend sagt
deutlich, dass negative Bewertungen nicht aussortiert werden dürfen.

**E-Mail** — Bestellbestätigung, Zahlungseingang und Versandbenachrichtigung,
wahlweise über `mail()` oder einen eigenen SMTP-Zugang.

**Design** — Farben, Schriften, Rasterbreite und Startseite im Backend
einstellbar, mit Sofortvorschau. Neun Vorlagen zum Starten, davon fünf für
eine Branche.

Details zu jedem Bereich stehen in [`docs/`](docs/).

## Aufbau

```
install.php        Einmalige Einrichtung – danach löschen
aufraeumen.php     Entfernt Reste einer früheren Fassung – danach löschen
systemcheck.php    Prüft den Server; läuft auch ohne Datenbank
config.php         Wird vom Installer erzeugt (nicht im Git)

index.php          Startseite         warenkorb.php   Warenkorb
kategorie.php      Kategorie          kasse.php       Kasse
artikel.php        Artikeldetail      bestellung.php  Bestellstatus
seite.php          Inhaltsseite       zahlung.php     Rückkehr vom Anbieter
journal.php        Journal            webhook.php     Zahlungsmeldungen
suche.php          Suche              sitemap.php     Sitemap
bewertung.php      Bewertung abgeben

lib/               Programmklassen (per .htaccess nicht direkt erreichbar)
admin/             Backend
assets/            Stylesheet und JavaScript des Shops
assets/stile/      Fertige Shop-Stile (Golf, Fahrrad, Pferdesport, Kräuter, Kräuterstube)
data/              Datenbank bei SQLite
uploads/           Hochgeladene Bilder
```

Drei Regeln erklären den größten Teil des Codes:

- **Geld ist immer eine Ganzzahl in Cent.** Fließkomma auf Preisen erzeugt die
  Rundungsfehler, die Kunden auf der Rechnung sehen. Gerundet wird nur in
  `lib/Util.php`.
- **Preise berechnet genau eine Stelle** — `lib/Preise.php`. Warenkorb, Kasse und
  gespeicherte Bestellung laufen alle dort durch, damit sie nicht auseinander
  laufen können.
- **Bestellungen sind Dokumente.** Titel, Artikelnummer und Preise werden in die
  Bestellzeile kopiert. Ändert sich später der Katalog, bleibt die Bestellung so,
  wie der Kunde sie abgeschlossen hat.

## Nächste Schritte

- **[docs/BETRIEB.md](docs/BETRIEB.md)** — Einrichtung, Zahlungen anbinden,
  Livegang, Sicherung, häufige Stolpersteine
- **[docs/THEME.md](docs/THEME.md)** — das Frontend an das eigene Design
  anpassen, von den Reglern im Backend bis zum eigenen Template
- **[docs/ARCHITEKTUR.md](docs/ARCHITEKTUR.md)** — Datenmodell, Abläufe und die
  Gründe hinter den Entwurfsentscheidungen
