# Das Frontend anpassen

Der Shop startet mit einem bewusst zurückhaltenden, generischen Design. Es ist
so gebaut, dass du es in drei Stufen zu deinem machst — jede Stufe geht weiter
als die vorige, und keine setzt die vorige voraus.

## Stufe 1: Im Backend, ohne Code

**Design** im Backend steuert Farben, Schriften, Eckenradius, Rasterbreite und
die Startseite. Die Werte werden bei jedem Seitenaufruf als CSS-Variablen in den
`<head>` geschrieben:

```html
<style>
:root {
  --hintergrund: #ffffff;
  --text: #16181d;
  --knopf: #16181d;
  --akzent: #2f6f4f;
  --ecken: 10px;
  --breite: 1200px;
  --schrift-titel: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  --spalten: 4;
}
</style>
```

### Fertige Stile

Ganz oben auf der Design-Seite steht die Auswahl **Shop-Stil**. Ein Klick auf
eine Kachel, dann auf *Ausgewählten Stil übernehmen* — das setzt Farben,
Schriften, Ecken, Rasterbreite und Artikel pro Reihe auf einmal.

Es gibt zwei Sorten:

| Stil | Bringt eine Stildatei mit | Gedacht für |
|---|---|---|
| **Golf** | `assets/stile/golf.css` | Sportfachhandel: tiefes Grün, viel Weiß, Versalien, geordnetes Raster |
| **Fahrrad** | `assets/stile/rad.css` | Technik: Schwarz, Signalrot, schmale Versalien, kantig, große Preise |
| **Pferdesport** | `assets/stile/pferd.css` | Katalogsortiment: Marineblau auf Sand, Serifenüberschriften, ruhige Karten |
| **Kräuter** | `assets/stile/kraeuter.css` | Naturprodukte: Creme, weiße Karten, Rot als einzige Signalfarbe, Salbeigrün für den Versandhinweis, Marke mittig |
| Basis, Kontrast, Warm, Dunkel | — | Reine Farbschemata ohne eigene Datei |

Golf, Fahrrad und Pferdesport sind nach dem gebaut, was in der jeweiligen
Branche üblich ist. Der Kräuterstil folgt Bildschirmfotos eines
österreichischen Kräuterherstellers, die der Betreiber beigesteuert hat: Farben
wurden aus den Bildern ausgelesen, dazu Bauart und Formensprache übernommen.

Kein Stil enthält fremde Logos, Wortmarken, Schriften oder Bilder — nur
Systemschriften und CSS. Übernommen ist die Bauart, nicht das Eigentum.

Die Stildatei wird **nach** `assets/shop.css` und **vor** den CSS-Variablen aus
den Design-Einstellungen geladen. Daraus folgt die Arbeitsteilung:

- Die Stildatei macht das, was keine Variable ausdrücken kann: Abstände,
  Rahmen, Versalien, Hover-Verhalten, Bildausschnitte.
- Die Farben und Schriften bleiben im Backend änderbar — auch bei aktivem Stil.
  Wer nach dem Übernehmen die Akzentfarbe ändert, behält den Stil und bekommt
  seine Farbe.

Achtung: Beim Übernehmen eines Stils werden die eingestellten Farben und
Schriften überschrieben. Der Rest — Startseitentexte, Ankündigungsleiste,
eigenes CSS — bleibt unangetastet.

### Einen eigenen Stil hinzufügen

Zwei Schritte, beide klein:

1. `assets/stile/meinstil.css` anlegen. Als Vorlage eignet sich eine der vier
   Dateien; sie sind bewusst kurz und kommentiert.
2. In `lib/Theme.php` einen Eintrag in `Theme::STILE` ergänzen:

```php
'meinstil' => [
    'name'  => 'Mein Stil',
    'text'  => 'Kurze Beschreibung für die Auswahlkachel im Backend.',
    'datei' => 'meinstil',      // Dateiname ohne .css, oder '' für keine Datei
    'werte' => [
        'farbe_hintergrund' => '#ffffff', 'farbe_flaeche' => '#f5f5f5',
        // ... alle Werte wie bei den mitgelieferten Stilen
    ],
],
```

Der Schlüssel der Liste ist zugleich der einzige erlaubte Dateiname — die
Einstellung aus der Datenbank wird nie direkt in die Adresse geschrieben.
Danach steht der Stil im Backend zur Auswahl.

Für kleinere Eingriffe gibt es dort auch ein Feld **Eigenes CSS**. Es wird nach
dem Basis-Stylesheet eingebunden und überschreibt es damit:

```css
.kopf { border-bottom: 3px solid var(--akzent); }
.kachel-bild { border-radius: 0; }
.knopf { text-transform: uppercase; letter-spacing: 0.08em; }
```

Änderungen am Design werden — wie alles andere — erst nach dem
**Veröffentlichen** im Shop sichtbar.

### Mitgelieferte Schrift

Unter `assets/schriften/` liegt **Source Sans 3** (Adobe, SIL Open Font
License) als WOFF2 in drei Schnitten. Der Shop liefert sie selbst aus — nicht
über Google Fonts, denn dabei ginge bei jedem Seitenaufruf die IP-Adresse des
Besuchers an einen Server in den USA; dafür sind deutsche Seitenbetreiber
bereits abgemahnt worden.

Im Backend steht sie unter *Design → Typografie* als **„Source Sans 3
(mitgeliefert)"** zur Auswahl. Wird eine andere Schrift eingestellt, lädt der
Browser die Dateien gar nicht erst — die `@font-face`-Regeln in `shop.css`
kosten dann nichts.

### Der Aufbau des Kopfes

Der Kopf besteht aus drei Bändern übereinander — der Aufbau, den der deutsche
Fachhandel praktisch durchgängig benutzt:

```
.servicezeile     Öffnungszeiten links, Telefon und Kontakt rechts   (abschaltbar)
.hinweisleiste    Ankündigung über die volle Breite                  (abschaltbar)
.kopf
  .kopf-innen     Menüknopf, Marke, Suche mit Knopf, Warenkorb
  .kopf-nav       Kategorienband mit .hauptmenue
.vorteilsleiste   Drei bis vier Vorteile mit Haken                   (abschaltbar)
```

Was die Bänder enthalten, steht unter **Design → Servicezeile & Vorteile**.
Telefonnummer und Kontaktadresse kommen aus den Shop-Einstellungen.

Diese Texte stehen öffentlich im Shop. In Deutschland sind Werbeaussagen
verbindlich — „Versandkostenfrei ab 50 €“ gehört da nur hinein, wenn es auch
gilt. Die mitgelieferten Vorgaben sind deshalb bewusst zurückhaltend.

Ein Stil kann die Bänder völlig unterschiedlich behandeln: Golf, Fahrrad und
Pferdesport färben das Kategorienband in der Hausfarbe ein, Kräuter lässt es
weiß und rückt Marke und Menü in die Mitte — so treten Hersteller auf, die ihre
eigenen Produkte verkaufen statt dreißigtausend fremde.

Das Untermenü (`.untermenue`) ist im Kräuterstil ein Band über die volle
Inhaltsbreite mit automatischen Spalten — dafür gibt der Menüpunkt seine
Positionierung ab (`position: static`) und das Band übernimmt sie. Kein
zusätzliches Markup, kein JavaScript.

Der Fuß besteht aus vier Spalten: Marke mit Sozialsymbolen, zwei Linkspalten
in `<details>` (am Schreibtisch offen, auf dem Telefon von `shop.js`
zugeklappt) und einem Hilfeblock mit großer Telefonnummer, Servicezeiten,
E-Mail, Anschrift und USt-IdNr.

In der Artikelkachel stehen außerdem der Nachlass in Prozent (`.marker`, etwa
„−25 %“), der Lieferstatus mit farbigem Punkt (`.kachel-lager`), der Grundpreis
(`.grundpreis`) und der Steuerhinweis (`.preishinweis`).

### Preis- und Vertrauensangaben

Diese Angaben entscheiden mit, ob ein Shop als echt wahrgenommen wird — und
zwei davon sind in Deutschland Pflicht:

| Angabe | Wo | Warum |
|---|---|---|
| **Grundpreis** („19,49 €/l“) | Kachel und Artikelseite | Pflicht nach Preisangabenverordnung für alles, was nach Gewicht, Volumen, Länge oder Fläche verkauft wird. Gepflegt wird er pro Variante über **Inhalt** und **Einheit**. |
| **inkl. MwSt., zzgl. Versand** | Kachel und Artikelseite | Pflicht, sobald ein Preis genannt wird. |
| **Lieferzeit** | am Kaufknopf | Einstellungen → Shop-Daten |
| **Widerrufsfrist** | am Kaufknopf | Einstellungen → Shop-Daten, mindestens 14 Tage |
| **Zahlungsarten** | am Kaufknopf und im Fuß | kommen aus den tatsächlich freigeschalteten Zahlarten |
| **Anschrift, Telefon, USt-IdNr.** | Fuß | Einstellungen → Shop-Daten |
| **Gütesiegel** | Fuß | Einstellungen → Shop-Daten, nur eintragen, wenn wirklich vorhanden |

Der Grundpreis rechnet auf 1 l bzw. 1 kg um; bei Mengen bis 250 g/ml auf
100 g/ml, weil „249,00 €/l“ bei einer 50-ml-Flasche niemandem hilft. Bei
Stückware bleibt die Zeile leer.

## Stufe 2: Eigenes Stylesheet

Für ein richtiges eigenes Design ersetzt du `assets/shop.css`. Die Templates
hängen an CSS-Klassen, nicht an bestimmten Regeln — die Seitenstruktur ändert
sich also nicht.

Die Klassen, die es gibt:

| Bereich | Klassen |
|---|---|
| Rahmen | `.behaelter`, `.abschnitt`, `.abschnitt-kopf`, `.seitenkopf`, `.brotkrumen`, `.schmal` |
| Kopf/Fuß | `.servicezeile`, `.servicezeile-innen`, `.service-rechts`, `.service-telefon`, `.hinweisleiste`, `.kopf`, `.kopf-innen`, `.marke`, `.kopf-nav`, `.hauptmenue`, `.menuepunkt`, `.untermenue`, `.kopf-aktionen`, `.suchfeld`, `.warenkorb-link`, `.warenkorb-wort`, `.warenkorb-zahl`, `.vorteilsleiste`, `.vorteile-innen`, `.vorteil`, `.fuss`, `.fuss-raster`, `.fuss-unten` |
| Startseite | `.buehne`, `.buehne-innen`, `.buehne.mit-bild` |
| Katalog | `.raster`, `.kachel`, `.kachel-bild`, `.kein-bild`, `.preis`, `.preis-jetzt`, `.preis-vorher`, `.preis-sale`, `.marker`, `.marker-aus`, `.kachel-lager`, `.lager-da`, `.lager-knapp`, `.lager-aus`, `.hersteller`, `.werkzeugleiste`, `.blaetterei` |
| Artikel | `.artikel-seite`, `.galerie-gross`, `.galerie-klein`, `.artikel-info`, `.optionsgruppe`, `.optionswerte`, `.menge`, `.kaufzeile`, `.lagerhinweis`, `.steuerhinweis`, `.rte` |
| Warenkorb/Kasse | `.zweispaltig`, `.korbzeile`, `.zusammenfassung`, `.summenzeile`, `.feld`, `.feldzeile`, `.feldblock`, `.auswahl`, `.hakenzeile`, `.gutscheinform` |
| Bestellung | `.bestellseite`, `.marken`, `.pille`, `.datenpaar` |
| Journal | `.beitragsraster`, `.beitragskachel` |
| Sonstiges | `.meldung`, `.meldung-fehler`, `.meldung-erfolg`, `.meldung-info`, `.leer`, `.knopf`, `.knopf-leer`, `.knopf-breit`, `.nebentext`, `.klein` |

Halte die CSS-Variablen am Leben, dann funktionieren die Backend-Regler weiter.
Wenn du fest verdrahtete Farben bevorzugst, ignoriere sie einfach — das
Design-Formular hat dann nur keine Wirkung mehr.

Auf die Auszeichnung selbst wirken sich außerdem aus:

- **Artikel pro Reihe** setzt `--spalten`
- **Inhaltsbreite** setzt `--breite`
- **Streichpreise zeigen** blendet `.marker` und `.preis-vorher` aus
- **Hersteller zeigen** blendet `.hersteller` in der Artikelkachel ein

## Stufe 3: Eigene Templates

Die Seiten sind ganz normale PHP-Dateien im Hauptverzeichnis:

```
index.php       Startseite
kategorie.php   Kategorie mit Artikelraster
artikel.php     Artikeldetail
warenkorb.php   Warenkorb
kasse.php       Kasse
bestellung.php  Bestellstatus
seite.php       Inhaltsseite
journal.php     Journal (Liste und Einzelbeitrag)
suche.php       Suche
```

Den gemeinsamen Rahmen — `<head>`, Kopf mit Navigation, Fuß mit Rechtslinks —
liefert `lib/Theme.php`:

```php
Theme::kopf(['titel' => 'Kategorien']);   // alles bis <main>
// … eigener Inhalt …
Theme::fuss();                             // </main> plus Fußzeile
```

Weitere Bausteine dort:

- `Theme::fassung()` — die veröffentlichte Fassung als Array
- `Theme::e('shop_name')` — eine Einstellung aus der Fassung
- `Theme::kachel($artikel, $bestaende)` — eine Artikelkachel
- `Theme::preisText($artikel)` — Preis mit Streichpreis und „ab“
- `Theme::bestaende([...])` — Verfügbarkeiten, direkt aus der Datenbank
- `Theme::meldung($text, 'fehler')` — Hinweisbox

Ausgaben werden mit `Util::e()` abgesichert. Nur bewusst gesetztes HTML — der
Rich-Text aus dem Backend — wird direkt ausgegeben; das ist beim Speichern
bereits bereinigt worden.

### Beispiel: Kategorieseite umbauen

`kategorie.php` holt die Kategorie aus der Fassung, sortiert die Artikel und
gibt sie aus. Ein Filter nach Hersteller ließe sich so ergänzen: das Formular in
die `.werkzeugleiste` einbauen, den Parameter mit `Util::get('hersteller')`
lesen und die Artikelliste vor dem Sortieren filtern.

## Ein komplett eigenes Frontend

Willst du die Storefront ganz woanders bauen, liegt der veröffentlichte Katalog
als JSON in der Datenbank bereit:

```php
require 'lib/bootstrap.php';
$fassung = Veroeffentlichung::live();
// $fassung['artikel'], ['kategorien'], ['seiten'], ['menues'], ['einstellungen']
```

Warenkorb und Kasse brauchen weiterhin dieses System — der Katalog kann
statisch sein, die Kasse nicht.

## Bilder

Uploads landen in `uploads/` und werden als `uploads/dateiname.jpg`
ausgeliefert. Der Dateiname wird beim Hochladen neu vergeben, damit ein
hochgeladener Name keine bestehende Datei überschreiben und keinen Pfad
enthalten kann. Erlaubt sind JPEG, PNG, WebP, AVIF und GIF bis 8 MB; geprüft
wird der tatsächliche Inhalt, nicht die Angabe des Browsers.

Es gibt bewusst keine automatische Bildskalierung — sie bräuchte je nach Server
unterschiedliche Bibliotheken und wäre eine Fehlerquelle mehr. Skaliere die
Bilder vor dem Upload (etwa auf 1600 px Kantenlänge), oder stelle einen
Bild-CDN davor und trage dessen Adressen als Bildadressen ein.
