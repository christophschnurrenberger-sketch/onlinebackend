# Betrieb

Von der lokalen Installation zum laufenden Shop.

## 1. Shop einrichten

Nach `npm run seed` und `npm start` sind die wichtigsten Handgriffe:

1. **Einstellungen → Shop-Daten** — Name, Anschrift, E-Mail, Währung. Die
   Anschrift landet im Impressum und auf Belegen.
2. **Einstellungen → Steuern** — Der Seed legt 19 % und 7 % an. Der Standardsatz
   gilt für alle Artikel ohne eigenen Satz.
3. **Einstellungen → Versand** — Zonen und Tarife. Eine Zone **ohne Länderliste**
   ist die Auffangzone für alle übrigen Länder; ohne sie kann aus nicht
   aufgeführten Ländern niemand bestellen.
4. **Inhalte → Seiten** — Impressum, AGB, Datenschutz und Widerruf liegen als
   Entwürfe bereit. **Diese Texte sind Platzhalter und müssen durch geprüfte
   ersetzt werden** — im deutschsprachigen Handel sind sie abmahnfähig.
5. **Artikel** anlegen, **Design** einstellen, **Veröffentlichen**.

## 2. Zahlungen

Zugangsdaten stehen in der `.env`, nie in der Datenbank. Erst wenn die Schlüssel
gesetzt sind, lässt sich die Zahlart im Backend aktivieren.

### Stripe (Karte, Apple Pay, Google Pay)

```bash
STRIPE_SECRET_KEY=sk_live_…          # dashboard.stripe.com/apikeys
STRIPE_PUBLISHABLE_KEY=pk_live_…
STRIPE_WEBHOOK_SECRET=whsec_…        # aus der Webhook-Einrichtung unten
```

Webhook im Stripe-Dashboard anlegen unter *Entwickler → Webhooks*:

- Adresse: `https://dein-shop.de/webhooks/stripe`
- Ereignisse: `checkout.session.completed`, `checkout.session.expired`,
  `checkout.session.async_payment_succeeded`,
  `checkout.session.async_payment_failed`, `charge.refunded`

Ohne `STRIPE_WEBHOOK_SECRET` werden eingehende Webhooks **abgelehnt** — das ist
Absicht: eine unsignierte Zahlungsbestätigung darf keine Bestellung als bezahlt
markieren.

### PayPal

```bash
PAYPAL_CLIENT_ID=…                   # developer.paypal.com
PAYPAL_CLIENT_SECRET=…
PAYPAL_ENV=live                      # oder sandbox zum Testen
PAYPAL_WEBHOOK_ID=…
```

Webhook-Adresse: `https://dein-shop.de/webhooks/paypal`, Ereignisse
`CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`,
`PAYMENT.CAPTURE.REFUNDED`.

### Rechnung, Vorkasse, Nachnahme

Brauchen keine Zugangsdaten. Die Bestellung bleibt auf *offen*, bis du sie im
Backend unter **Bestellungen → Als bezahlt markieren** buchst. Den Hinweistext
für den Kunden (etwa die Bankverbindung) stellst du unter
**Einstellungen → Zahlungen** ein.

### Testzahlung

Die Zahlart `mock` schließt sofort ab, ohne echten Anbieter. Damit lässt sich
der gesamte Ablauf bis zur Bestellverwaltung durchspielen. **In Produktion
abschalten.**

## 3. Warum Webhooks wichtig sind

Nach der Zahlung wird der Kunde in den Shop zurückgeleitet, und dabei prüfen wir
den Zahlungsstatus direkt beim Anbieter nach. Schließt der Kunde vorher den
Browser, fällt dieser Weg aus — dann ist der Webhook der einzige Weg, vom
Geldeingang zu erfahren. Er ist deshalb kein Extra, sondern der verlässliche
Pfad; die Rückkehr im Browser ist nur der schnelle.

Jedes Ereignis wird gespeichert und genau einmal verarbeitet. Doppelte
Zustellungen — die es bei jedem Anbieter gibt — verändern nichts.

## 4. Livegang

```bash
NODE_ENV=production
BASE_URL=https://dein-shop.de
SESSION_SECRET=<64 zufällige Hex-Zeichen>
SECURE_COOKIES=true
PAYMENT_PROVIDERS=stripe,paypal,invoice,prepayment
```

Das Secret erzeugen:

```bash
node -e "console.log(require('node:crypto').randomBytes(32).toString('hex'))"
```

Ohne gesetztes `SESSION_SECRET` wird bei jedem Start ein neues erzeugt — alle
Anmeldungen enden dann beim Neustart. Der Server warnt beim Start davor.

### Als Systemdienst

```ini
# /etc/systemd/system/shop.service
[Unit]
Description=Shop-CMS
After=network.target

[Service]
Type=simple
User=shop
WorkingDirectory=/var/www/shop
ExecStart=/usr/bin/node --disable-warning=ExperimentalWarning src/server.js
Restart=always
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

### Hinter einem Reverse Proxy

```nginx
server {
  listen 443 ssl http2;
  server_name dein-shop.de;

  client_max_body_size 10M;   # für Bild-Uploads im Backend

  location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  }
}
```

TLS ist Pflicht: Ohne HTTPS wandern Session-Cookies und Kundenadressen im
Klartext durchs Netz, und Stripe wie PayPal verweigern unverschlüsselte
Rückleitungen.

## 5. Statischer Export

**Veröffentlichen → Statischer Export** (oder `npm run export`) erzeugt aus der
live geschalteten Version fertige HTML-Dateien in `./dist`:

```bash
npm run export -- --base-url=https://dein-shop.de
```

Der Ordner lässt sich auf jeden Webspace, zu Netlify, Vercel oder GitHub Pages
hochladen. Enthalten sind Startseite, alle Kategorie-, Artikel- und
Inhaltsseiten, `sitemap.xml`, `robots.txt`, das Stylesheet, die hochgeladenen
Bilder und `catalog.json` für ein eigenes Frontend.

**Wichtig:** Warenkorb und Kasse brauchen weiterhin das laufende Backend. Der
Export ist für den Katalog gedacht — soll auch die Kasse statisch erreichbar
sein, muss die Backend-API unter derselben Domain erreichbar sein (etwa per
Reverse-Proxy-Regel für `/api` und `/checkout`).

Wer das Backend ohnehin öffentlich betreibt, braucht den Export nicht: die
Storefront wird dann direkt ausgeliefert.

## 6. Sicherung

Die gesamte Datenbank ist **eine Datei**. Ein Sicherungslauf reicht:

```bash
#!/bin/bash
# Der WAL-Modus erlaubt Sicherungen im laufenden Betrieb – aber nur über
# ".backup", nicht per cp: eine kopierte Datei kann mitten in einer
# Transaktion erwischt werden.
sqlite3 /var/www/shop/data/shop.db ".backup '/backup/shop-$(date +%F).db'"
tar czf "/backup/uploads-$(date +%F).tar.gz" /var/www/shop/public/uploads
find /backup -name 'shop-*.db' -mtime +30 -delete
```

Steht `sqlite3` nicht zur Verfügung, tut es auch der Node-Weg:

```bash
node -e "const {DatabaseSync}=require('node:sqlite'); \
  new DatabaseSync('data/shop.db').exec(\"VACUUM INTO '/backup/shop.db'\")"
```

Zu sichern sind zwei Dinge: `data/shop.db` und `public/uploads/`.

## 7. Was der Server von sich aus tut

- Abgelaufene Sitzungen und Warenkörbe älter als 30 Tage werden alle sechs
  Stunden aufgeräumt.
- Anfragen über 500 ms landen als Warnung im Log.
- Interne Fehler bekommen eine kurze Referenz-ID: der Kunde sieht nur die ID,
  die Einzelheiten stehen im Log.
- Jede Antwort trägt `Content-Security-Policy`, `X-Content-Type-Options`,
  `X-Frame-Options` und `Referrer-Policy`.

## 8. Wenn etwas klemmt

| Symptom | Ursache |
|---|---|
| Shop zeigt „Noch nichts veröffentlicht“ | Im Backend auf **Veröffentlichen** klicken |
| Artikel fehlt im Shop | Status ist *Entwurf*, oder seit der Änderung wurde nicht veröffentlicht |
| „In dieses Land liefern wir nicht“ | Keine Versandzone für das Land und keine Auffangzone |
| Zahlart fehlt im Checkout | Schlüssel in der `.env` fehlt oder Zahlart unter Einstellungen nicht aktiviert |
| Zahlungen kommen nicht an | Webhook-Adresse oder -Secret prüfen; die Rohdaten stehen in der Tabelle `webhook_events` samt Fehlermeldung |
| Nach dem Neustart abgemeldet | `SESSION_SECRET` ist nicht gesetzt |
| Upload schlägt fehl | Datei über 8 MB oder kein Bildformat; hinter nginx `client_max_body_size` prüfen |
