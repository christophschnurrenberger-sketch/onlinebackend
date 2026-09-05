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

Vier Vorlagen (Basis, Kontrast, Warm, Dunkel) setzen alle Farben auf einmal.
Die Vorschau daneben zeigt das Ergebnis, bevor du speicherst.

Für kleinere Eingriffe gibt es dort auch ein Feld **Eigenes CSS**. Es wird nach
dem Basis-Stylesheet eingebunden und überschreibt es damit:

```css
.kopf { border-bottom: 3px solid var(--akzent); }
.kachel-bild { border-radius: 0; }
.knopf { text-transform: uppercase; letter-spacing: 0.08em; }
```

Änderungen am Design werden — wie alles andere — erst nach dem
**Veröffentlichen** im Shop sichtbar.

## Stufe 2: Eigenes Stylesheet

Für ein richtiges eigenes Design ersetzt du `assets/shop.css`. Die Templates
hängen an CSS-Klassen, nicht an bestimmten Regeln — die Seitenstruktur ändert
sich also nicht.

Die Klassen, die es gibt:

| Bereich | Klassen |
|---|---|
| Rahmen | `.behaelter`, `.abschnitt`, `.abschnitt-kopf`, `.seitenkopf`, `.brotkrumen`, `.schmal` |
| Kopf/Fuß | `.hinweisleiste`, `.kopf`, `.kopf-innen`, `.marke`, `.hauptmenue`, `.menuepunkt`, `.untermenue`, `.kopf-aktionen`, `.suchfeld`, `.warenkorb-link`, `.warenkorb-zahl`, `.fuss`, `.fuss-raster`, `.fuss-unten` |
| Startseite | `.buehne`, `.buehne-innen`, `.buehne.mit-bild` |
| Katalog | `.raster`, `.kachel`, `.kachel-bild`, `.kein-bild`, `.preis`, `.preis-jetzt`, `.preis-vorher`, `.preis-sale`, `.marker`, `.marker-aus`, `.hersteller`, `.werkzeugleiste`, `.blaetterei` |
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
