# Das Frontend anpassen

Der Shop startet mit einem bewusst zurückhaltenden, generischen Design. Es ist
so gebaut, dass du es in drei Stufen zu deinem machst — jede Stufe geht weiter
als die vorige, und keine erfordert die vorige.

## Stufe 1: Im Backend, ohne Code

**Design** im Backend steuert Farben, Schriften, Eckenradius, Rasterbreite und
die Startseite. Die Werte werden bei jedem Seitenaufruf als
CSS-Custom-Properties in den `<head>` geschrieben:

```html
<style>
:root {
  --bg: #ffffff;
  --text: #16181d;
  --primary: #16181d;
  --accent: #2f6f4f;
  --radius: 10px;
  --container: 1200px;
  --font-heading: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  --grid-columns: 4;
}
</style>
```

Vier Vorlagen (Basis, Kontrast, Warm, Dunkel) setzen alle Farben auf einmal.
Die Sofortvorschau daneben zeigt das Ergebnis, bevor du speicherst.

Für kleinere Eingriffe gibt es dort auch ein Feld **Eigenes CSS**. Es wird nach
dem Basis-Stylesheet eingebunden und überschreibt es damit:

```css
.site-header { border-bottom: 3px solid var(--accent); }
.product-card .media { border-radius: 0; }
.btn { text-transform: uppercase; letter-spacing: 0.08em; }
```

## Stufe 2: Eigenes Stylesheet

Für ein richtiges eigenes Design ersetzt du `public/theme/base.css`. Die
Storefront-Templates hängen an CSS-Klassen, nicht an bestimmten Regeln — die
Struktur ändert sich also nicht.

Die Klassen, die es gibt:

| Bereich | Klassen |
|---|---|
| Rahmen | `.container`, `.section`, `.section-head`, `.page-head`, `.breadcrumbs` |
| Kopf/Fuß | `.announcement`, `.site-header`, `.header-inner`, `.brand`, `.main-nav`, `.nav-item`, `.submenu`, `.cart-link`, `.cart-count`, `.site-footer`, `.footer-grid` |
| Startseite | `.hero`, `.hero-inner`, `.hero.has-image` |
| Katalog | `.product-grid`, `.product-card`, `.product-card .media`, `.price`, `.price .compare`, `.badge`, `.toolbar`, `.pagination` |
| Artikel | `.product-layout`, `.gallery`, `.thumbs`, `.product-info`, `.option-group`, `.option-values`, `.quantity`, `.add-to-cart-row`, `.stock-note`, `.rte` |
| Warenkorb/Kasse | `.cart-layout`, `.checkout-layout`, `.line-item`, `.summary`, `.summary-row`, `.field`, `.field-row`, `.fieldset`, `.choice`, `.discount-form` |
| Bestellung | `.order-status`, `.status-pills`, `.pill`, `.data-list` |
| Sonstiges | `.notice`, `.empty-state`, `.btn`, `.btn-secondary`, `.content-narrow`, `.post-grid` |

Halte die Custom Properties am Leben, dann funktionieren die Backend-Regler
weiter. Wenn du fest verdrahtete Farben bevorzugst, ignoriere sie einfach — das
Design-Formular hat dann nur keine Wirkung mehr.

Auf die Auszeichnung selbst wirken sich außerdem aus:

- **Artikel pro Reihe** setzt `--grid-columns`
- **Inhaltsbreite** setzt `--container`
- **Streichpreise zeigen** blendet `.badge` und `.price .compare` aus
- **Hersteller zeigen** blendet `.vendor` in der Artikelkachel ein

## Stufe 3: Eigene Templates

Die Seiten werden serverseitig gerendert:

- `src/theme/layout.js` — der Rahmen: `<head>` mit SEO-Angaben, Kopf mit
  Navigation, Fuß mit Rechtslinks, dazu `productCard()` und `priceHtml()`
- `src/theme/views.js` — die Seiten selbst: `home`, `collection`, `product`,
  `cart`, `checkout`, `orderStatus`, `contentPage`, `blogIndex`, `blogPost`,
  `search`, `notFound`

Beide benutzen ein Tagged Template, das interpolierte Werte automatisch
escaped:

```js
import { html, raw } from '../lib/html.js';

html`<h1>${produkt.title}</h1>`           // escaped – der Normalfall
html`<div class="rte">${raw(body_html)}</div>`  // bewusst ungeprüft
```

`raw()` ist absichtlich sichtbar: so ist an jeder Stelle im Code erkennbar, wo
ungeprüftes HTML in die Seite gelangt. Der Rich-Text aus dem Backend ist schon
beim Speichern bereinigt worden (`sanitizeHtml`), doppelt hält besser.

### Beispiel: Kategorie-Seite umbauen

`views.collection` bekommt `{ collection, products, soldOut, sort, page, pageCount }`
und gibt gerendertes HTML zurück. Ein Filter nach Hersteller ließe sich so
ergänzen: das Formular in `views.collection` einbauen, den Parameter in
`src/routes/storefront.js` bei der Route `/collections/:handle` aus
`ctx.query` lesen und die Produktliste vor `sortProducts` filtern.

## Ein komplett eigenes Frontend

Willst du die Storefront in Next.js, Astro, Svelte oder als App bauen, brauchst
du dieses Repository nur als Backend. Zwei Wege:

**Über die API.** `GET /api/catalog` liefert den kompletten veröffentlichten
Katalog inklusive Design-Einstellungen und Navigation als JSON. Warenkorb und
Kasse laufen über `/api/cart` und `/api/checkout` — die Endpunkte stehen in
[API.md](API.md).

**Über den statischen Export.** `npm run export` schreibt neben den HTML-Seiten
eine `catalog.json` mit demselben Inhalt. Für Generatoren, die zur Bauzeit lesen,
ist das der einfachere Weg.

In beiden Fällen bleibt das Backend die Quelle für Bestände, Bestellungen und
Zahlungen — der Katalog kann statisch sein, die Kasse nicht.

## Bilder

Uploads landen unter `public/uploads/` und werden als `/uploads/dateiname`
ausgeliefert. Der Dateiname wird beim Upload neu vergeben, damit ein hochgeladener
Name keine bestehende Datei überschreiben und keinen Pfad enthalten kann.
Erlaubt sind JPEG, PNG, WebP, AVIF, GIF und SVG bis 8 MB.

Es gibt bewusst keine automatische Bildskalierung: sie bräuchte eine native
Bibliothek und damit den ersten echten Dependency. Für kleine Kataloge lohnt
sich das nicht — skaliere die Bilder vor dem Upload, oder stelle einen
Bild-CDN davor und trage dessen URLs als Bildadressen ein.
