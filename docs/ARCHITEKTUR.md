# Architektur

Warum das System so gebaut ist, wie es gebaut ist.

## Die drei Regeln

**Geld ist immer ein Integer in Cent.** `19,90 €` ist `1990`. Fließkomma auf
Preisen erzeugt genau die Rundungsfehler, die Kunden auf dem Beleg sehen und
die die Buchhaltung nicht ausgleichen kann. Gerundet wird ausschließlich in
`src/lib/money.js`; `distribute()` verteilt einen Betrag proportional auf
Positionen, ohne dass dabei Cent verloren gehen — der Rest wandert an die
Position mit dem größten Rundungsverlust.

**Preise berechnet genau eine Stelle.** `src/services/pricing.js` bedient
Warenkorb-Vorschau, Kassenseite und die gespeicherte Bestellung. Gäbe es zwei
Implementierungen, würden sie irgendwann um einen Cent auseinanderlaufen — und
das sieht der Kunde als Erster.

**Bestellungen sind Dokumente, keine Sicht auf den Katalog.** Titel, SKU, Preis
und Steuersatz werden in die Bestellposition kopiert. Ändert der Betreiber
später den Preis oder benennt den Artikel um, bleibt die Bestellung so, wie der
Kunde sie abgeschlossen hat. Das ist handelsrechtlich nötig und macht Belege
reproduzierbar.

## Schichten

```
routes/     HTTP: Anfrage lesen, Antwort schreiben, sonst nichts
services/   Geschäftslogik: Preise, Warenkorb, Bestand, Checkout, Veröffentlichen
models/     Datenzugriff je Fachbereich, mit Eingabeprüfung
db/         SQLite: Verbindung, Transaktionen, Schema
lib/        Bausteine ohne Fachwissen: Geld, HTML, Router, Auth, Slugs
```

Die Richtung ist einbahnig: Routen rufen Services, Services rufen Models,
Models rufen `db`. Eine Route enthält nie eine Preisberechnung, ein Model nie
eine HTTP-Antwort.

## Datenmodell

Die kaufbare Einheit ist die **Variante**, nicht das Produkt. Preis und Bestand
hängen an ihr. Auch ein Artikel ohne Optionen hat genau eine Default-Variante —
das erspart im Warenkorb, im Checkout und in der Bestellung jede
Sonderbehandlung von „Artikel ohne Varianten“.

```
products ──┬── product_images
           ├── product_options      (die Achsen: Größe, Farbe …)
           └── variants ────────────┬── inventory_moves   (Journal)
                                    └── order_lines       (Bestellpositionen)

collections ── collection_products  (nur bei manuellen Kategorien)
               Regeln automatischer Kategorien liegen als JSON in der
               Kategorie und werden beim Lesen ausgewertet

carts ── cart_lines                 speichern nur Variante und Menge, nie Preise
orders ─┬── order_lines
        ├── order_events            der Verlauf, den das Backend zeigt
        ├── fulfillments
        ├── refunds
        └── payments

publications                        die eingefrorenen Snapshots
```

Bestandteile, die auffallen könnten:

- **`carts` speichert keine Preise.** Preise kommen bei jedem Aufruf frisch aus
  der Engine — sonst könnte ein alter Warenkorb eine Preisänderung unterlaufen.
- **`inventory_moves` ist ein Journal.** Jede Bestandsänderung hinterlässt eine
  Zeile mit Grund, Bestellung und Benutzer. Ohne Journal ist Lagerhaltung nicht
  auditierbar; die Frage „warum stehen hier drei?“ bleibt sonst unbeantwortbar.
- **`webhook_events` speichert vor der Verarbeitung.** Schlägt die Verarbeitung
  fehl, ist das Ereignis trotzdem dokumentiert. Die Eindeutigkeit von
  `(provider, event_id)` macht die Verarbeitung idempotent.

## Der Veröffentlichungs-Snapshot

`services/publish.js` serialisiert bei jedem Veröffentlichen den kompletten
veröffentlichungsfähigen Zustand nach JSON und legt ihn mit einer
Versionsnummer ab. Die Storefront liest ausschließlich aus dem Snapshot, der
gerade `live = 1` trägt; er wird nach dem ersten Zugriff im Arbeitsspeicher
gehalten.

**Was in den Snapshot geht:** aktive Artikel mit Varianten und Bildern,
sichtbare Kategorien (Regeln werden dabei zu festen Produktlisten aufgelöst),
veröffentlichte Seiten und Beiträge, Menüs, Design und Stammdaten.

**Was bewusst draußen bleibt:** Bestände, Warenkörbe, Bestellungen, Kunden und
Rabattzähler. Die müssen sofort wirken. Ein ausverkaufter Artikel darf nicht
bis zur nächsten Veröffentlichung weiterverkauft werden — deshalb liest die
Storefront Verfügbarkeiten direkt aus der Datenbank, auch wenn der Rest der
Seite aus dem Snapshot kommt.

Die Alternative wäre gewesen, im Shop einfach auf `status = 'active'` zu
filtern. Das hätte drei Dinge gekostet: das gefahrlose Arbeiten an aktiven
Artikeln, den Rollback und die Änderungsliste vor dem Veröffentlichen.

## Der Bestell-Ablauf

```
1. Prüfen      Warenkorb nicht leer, Bestand reicht, Mindestbestellwert erreicht,
               E-Mail und Adresse gültig, Zahlart verfügbar, AGB akzeptiert

2. Neu bepreisen  Die Adresse kann das Land und damit den Versandpreis ändern

3. Transaktion    Kunde anlegen oder ergänzen
                  Bestellung schreiben
                  Bestand ausbuchen  ← wirft, wenn jemand schneller war;
                                       die Transaktion nimmt dann alles zurück
                  Rabattzähler erhöhen

4. Zahlung        Außerhalb der Transaktion: ein HTTP-Aufruf zu Stripe oder
                  PayPal darf keine offene SQLite-Transaktion blockieren

5. Ergebnis       'complete' → fertig, oder 'redirect' → Kunde geht zum Anbieter
```

Der Bestand wird schon in Schritt 3 gebucht, nicht erst nach der Zahlung: sonst
könnten zwei Kunden im Zahlungsfenster dasselbe letzte Stück kaufen. Bricht die
Zahlung ab, gibt `checkout.fail()` die Ware wieder frei und storniert die
Bestellung.

## Zahlungsanbieter

Jeder Anbieter erfüllt dieselbe kleine Schnittstelle:

```js
{
  id, label, available(),
  start(order, ctx),          // { action: 'redirect'|'complete', url?, reference? }
  confirm(order, payload),    // Rückkehr aus dem Anbieter-Flow prüfen
  refund(order, amount),      // optional
  verifyWebhook(raw, headers),
  parseEvent(event),          // Anbieter-Ereignis → { orderId, status, reference }
}
```

`start` kennt genau zwei Ausgänge — der Kunde geht zum Anbieter, oder die
Bestellung ist sofort abschließbar. Mehr Fälle braucht es nicht, und der
Checkout-Code bleibt dadurch geradlinig. Eine neue Zahlart ist eine Datei in
`src/payments/` und ein Eintrag in der Liste in `src/payments/index.js`.

Zwei Regeln gelten für jeden Webhook: **Signatur prüfen, bevor irgendetwas
gebucht wird**, und **jede `event_id` nur einmal verarbeiten**.

## Sicherheit

| Angriff | Gegenmaßnahme |
|---|---|
| Passwort-Diebstahl aus der Datenbank | scrypt mit Salt je Passwort (`lib/auth.js`) |
| Session-Übernahme | Zufälliges Token, HttpOnly, SameSite=Lax, serverseitig gespeichert — Logout wirkt sofort |
| CSRF | Session-gebundenes Token als Header bei jeder schreibenden Admin-Anfrage |
| Store-XSS über den Rich-Text | `sanitizeHtml` beim Speichern: Whitelist für Tags und Attribute, `javascript:`- und `data:`-URLs raus |
| XSS über Templates | `html`-Tagged-Template escaped alles außer ausdrücklichem `raw()` |
| SQL-Injection | Ausschließlich parametrisierte Abfragen |
| Pfad-Ausbruch beim Ausliefern | Normalisierter Pfad muss im Wurzelverzeichnis bleiben (`sendFile`) |
| Bösartige Uploads | Typ-Whitelist, Größenlimit, serverseitig vergebener Dateiname |
| Gefälschte Zahlungsbestätigung | HMAC-Signaturprüfung (Stripe), Rückfrage beim Anbieter (PayPal) |
| Timing-Angriff auf den Login | Auch ohne Treffer wird ein Hash geprüft; Vergleiche laufen zeitkonstant |
| Preismanipulation vom Client | Der Client schickt nur Varianten-ID und Menge; alle Preise kommen vom Server |
| Überverkauf durch Parallelzugriff | Bestandsbuchung in derselben Transaktion wie die Bestellung |

## Grenzen

Die Architektur trägt einen Shop bis in den mittleren fünfstelligen Bereich an
Artikeln und Bestellungen pro Jahr auf einem einzelnen kleinen Server. Wo sie
endet:

- **Ein Prozess, eine Datei.** SQLite schreibt seriell. Bei Dutzenden
  gleichzeitigen Bestellungen pro Sekunde wäre PostgreSQL angebracht — der
  Datenzugriff liegt gebündelt in `models/`, der Umbau bliebe lokal.
- **Kein E-Mail-Versand.** Bestellbestätigungen müssen angebunden werden; die
  Stelle dafür ist `services/checkout.js` nach dem Anlegen der Bestellung.
- **Keine Bildskalierung.** Bräuchte eine native Bibliothek; siehe
  [THEME.md](THEME.md).
- **Eine Sprache, eine Währung.** Mehrsprachigkeit hieße: übersetzbare Felder je
  Sprache und ein Sprachsegment in den Routen.
- **Volltextsuche ist eine lineare Suche** über den Snapshot. Bis einige tausend
  Artikel ist das schneller als jeder Index; darüber wäre SQLite FTS5 der
  nächste Schritt.
