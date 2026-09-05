# Shop-CMS

Ein vollständiges Shop-System aus einem Guss: Im **Backend** pflegst du Artikel,
Kategorien, Inhalte und Einstellungen. Mit einem Klick auf **Veröffentlichen**
geht dieser Stand in den **Shop** — vorher sieht ihn niemand.

Zahlungen, Versandzonen, Steuern, Rabatte, Bestandsführung, Bestellabwicklung
und Rechtstexte sind eingebaut.

```
┌──────────────────┐   Veröffentlichen   ┌──────────────────┐
│     Backend      │  ─────────────────▶ │    Storefront    │
│  /admin          │   Snapshot v1, v2…  │  /               │
│  Entwürfe        │                     │  nur Veröffent-  │
│  Arbeitsstand    │  ◀─── Rollback ──── │  lichtes         │
└──────────────────┘                     └──────────────────┘
        │                                          │
        └──────────── eine SQLite-Datenbank ───────┘
           Bestände, Warenkörbe und Bestellungen
           laufen daran vorbei und wirken sofort
```

## Schnellstart

Vorausgesetzt ist **Node.js 22.5 oder neuer** — mehr nicht. Das Projekt hat
bewusst *keine* Runtime-Dependencies: kein `npm install`, kein Build-Schritt.

```bash
cp .env.example .env      # optional – ohne .env laufen sinnvolle Vorgaben
npm run seed              # Datenbank, Zugang und Beispielkatalog anlegen
npm start
```

| Adresse | Was |
|---|---|
| http://localhost:3000 | Der Shop |
| http://localhost:3000/admin | Das Backend |

Der Seed legt den Zugang `admin@example.com` mit dem Passwort `admin12345` an
(änderbar über `ADMIN_EMAIL` / `ADMIN_PASSWORD` in der `.env`).

```bash
npm test                  # 76 Tests
npm run dev               # mit Auto-Neustart bei Dateiänderungen
npm run export            # statische Website nach ./dist erzeugen
npm run reset -- --yes    # Datenbank löschen (danach: npm run seed)
```

## Der Veröffentlichungsablauf

Das ist der Kern des Systems und der Grund für einige seiner Entwurfsentscheidungen.

**Beim Veröffentlichen** wird der gesamte veröffentlichungsfähige Zustand —
aktive Artikel, sichtbare Kategorien, Seiten, Beiträge, Menüs, Design und
Stammdaten — als JSON-Snapshot eingefroren und mit einer Versionsnummer
gespeichert. Die Storefront liest **ausschließlich** aus dem aktuell live
geschalteten Snapshot.

Das hat vier praktische Folgen:

1. **Entwürfe sind sicher.** Du kannst einen Artikel über Tage bearbeiten,
   Preise ausprobieren und Texte umbauen — im Shop ändert sich nichts, bis du
   veröffentlichst. Ein Test hält genau das fest.
2. **Veröffentlichen ist ein Ereignis** mit Datum, Version und Urheber. Vor dem
   Klick zeigt das Backend, was sich seit dem letzten Mal geändert hat.
3. **Rollback ist trivial.** Eine alte Version wieder live zu schalten ist ein
   einziges `UPDATE` — kein Rückwärtsmigrieren von Daten.
4. **Der Shop ist schnell.** Katalogseiten kommen aus dem Arbeitsspeicher, kein
   langsamer Katalog-Query bremst die Kasse aus.

**Was bewusst nicht über den Snapshot läuft:** Bestände, Warenkörbe,
Bestellungen, Kunden und Rabattzähler. Die müssen sofort wirken — ein
ausverkaufter Artikel darf nicht bis zur nächsten Veröffentlichung
weiterverkauft werden.

## Was drin ist

**Katalog** — Artikel mit Varianten (bis zu drei Optionsachsen wie Größe und
Farbe), Bildern, Streichpreisen, SKU, Gewicht, eigenem Steuersatz und SEO-Feldern.
Bestandsführung je Variante mit wählbarer Politik (Verkauf stoppen oder
Überverkauf erlauben) und lückenlosem Bewegungsjournal. Kategorien manuell
zusammengestellt oder regelbasiert (Schlagwort, Typ, Hersteller, Titel, Preis).

**Verkauf** — Warenkorb, Kasse, Bestellungen mit Zahlungs- und Versandstatus,
Teilversand mit Sendungsverfolgung, Teil- und Vollerstattung, Storno mit
Bestandsrückgabe, Ereignisverlauf je Bestellung, fortlaufende Bestellnummern.

**Zahlungen** — Stripe (Checkout), PayPal (Orders v2), Kauf auf Rechnung,
Vorkasse, Nachnahme und eine Testzahlung für die Einrichtung. Alle hinter einer
gemeinsamen Schnittstelle; Webhooks werden signaturgeprüft und nur einmal
verarbeitet.

**Preise & Steuern** — Bruttopreise nach EU-Praxis, Steuer als enthaltener
Anteil, mehrere Sätze, Versandzonen mit Tarifen und Versandkostenfreiheit ab
Schwellenwert, Rabattcodes (Prozent, Betrag, Gratisversand) mit
Mindestbestellwert, Laufzeit, Nutzungslimit und „einmal pro Kunde“.

**Inhalte** — Seiten, Journal-Beiträge, zweistufige Navigation, Mediathek mit
Upload. Impressum, AGB, Datenschutz und Widerruf legt der Seed als Entwürfe an.

**Design** — Farben, Schriften, Rasterbreite und Startseite im Backend
einstellbar, mit Sofortvorschau. Vier Vorlagen zum Starten.

Details zu jedem Bereich stehen in [`docs/`](docs/).

## Aufbau

```
src/
  config.js          Konfiguration (.env wird selbst eingelesen)
  server.js          HTTP-Server; bedient Backend, APIs und Shop
  db/                Schema, Verbindung, Migration, Startdatenbestand
  lib/               Geld, Slugs, HTML-Bereinigung, Router, Auth, Prüfung
  models/            Datenzugriff je Fachbereich
  services/          Geschäftslogik: Preise, Warenkorb, Bestand,
                     Checkout, Veröffentlichen, statischer Export
  payments/          Zahlungsanbieter hinter einer gemeinsamen Schnittstelle
  routes/            Admin-API, Storefront-API, Storefront-Seiten, Webhooks
  theme/             Serverseitiges Rendering der Storefront
admin/               Backend-Oberfläche (Vanilla JS, kein Build)
public/theme/        Das generische Shop-Stylesheet
tests/               76 Tests (node:test)
```

Drei Regeln erklären den größten Teil des Codes:

- **Geld ist immer ein Integer in Cent.** Fließkomma auf Preisen erzeugt die
  Rundungsfehler, die Kunden auf dem Beleg sehen. Gerundet wird nur in
  `lib/money.js`.
- **Preise berechnet genau eine Stelle** — `services/pricing.js`. Warenkorb,
  Kasse und gespeicherte Bestellung laufen alle dort durch, damit sie nicht
  auseinanderlaufen können.
- **Bestellungen sind Dokumente.** Titel, SKU und Preise werden in die
  Bestellposition kopiert. Ändert sich später der Katalog, bleibt die
  Bestellung so, wie der Kunde sie abgeschlossen hat.

## Warum ohne Dependencies

Node 22.5 bringt SQLite mit (`node:sqlite`), und der Rest — HTTP-Server,
Passwort-Hashing, HMAC-Signaturprüfung, `fetch` für Stripe und PayPal — ist
ohnehin eingebaut. Das Projekt startet damit auf jedem Host mit Node, ohne
Installationsschritt, ohne Lockfile-Konflikte und ohne dass eine
Supply-Chain-Lücke in einem Transitiv-Paket zum Sicherheitsproblem im Shop wird.

Für einen eigenen Shop reicht ein einzelner Node-Prozess mit SQLite bei weitem.
Wenn es einmal eng wird, ist der Storefront-Teil der skalierbare — und der liest
aus dem Snapshot im Arbeitsspeicher.

## Nächste Schritte

- **[docs/BETRIEB.md](docs/BETRIEB.md)** — Livegang, Zahlungen einrichten,
  Webhooks, Sicherung, Hosting (auch statischer Export)
- **[docs/THEME.md](docs/THEME.md)** — das Frontend an das eigene Design
  anpassen, von CSS-Variablen bis zum eigenen Template
- **[docs/ARCHITEKTUR.md](docs/ARCHITEKTUR.md)** — Datenmodell, Abläufe und die
  Gründe hinter den Entwurfsentscheidungen
- **[docs/API.md](docs/API.md)** — alle Endpunkte, für ein entkoppeltes
  Frontend oder eigene Werkzeuge
