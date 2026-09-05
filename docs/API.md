# API

Zwei Schnittstellen: die **Storefront-API** unter `/api` ist öffentlich und
bedient Warenkorb und Kasse. Die **Admin-API** unter `/api/admin` verlangt eine
Anmeldung.

Alle Geldbeträge sind Integer in Cent (`1990` = 19,90 €). Zeitstempel sind
ISO-8601 in UTC.

## Fehler

```json
{ "error": "Nur noch 3 Stück verfügbar", "details": { "available": 3 } }
```

| Status | Bedeutung |
|---|---|
| 400 | Eingabe unvollständig oder ungültig |
| 401 | Nicht angemeldet (nur Admin-API) |
| 403 | Keine Berechtigung oder CSRF-Token fehlt |
| 404 | Nicht gefunden |
| 409 | Konflikt — meist Bestand reicht nicht mehr |
| 413 | Anfrage zu groß |
| 500 | Serverfehler; `error_id` verweist auf den Log-Eintrag |

---

# Storefront-API

Der Warenkorb hängt am Cookie `shop_cart`, das beim ersten Zugriff gesetzt
wird. Ein Client muss Cookies mitschicken (`credentials: 'include'`).

## Katalog

### `GET /api/catalog`

Der komplette veröffentlichte Katalog. Antwortet mit `503`, solange nichts
veröffentlicht ist.

```json
{
  "version": 3,
  "generated_at": "2026-03-14T09:12:00.000Z",
  "store": { "name": "Mein Shop", "currency": "EUR", "…": "…" },
  "theme": { "color_primary": "#16181d", "…": "…" },
  "menus": { "main": [{ "label": "Shop", "url": "/collections/alle", "children": [] }] },
  "collections": [
    { "id": 1, "handle": "neuheiten", "title": "Neuheiten", "product_ids": [4, 7] }
  ],
  "products": [
    {
      "id": 4, "handle": "leinenhemd-sommer", "title": "Leinenhemd Sommer",
      "body_html": "<p>…</p>", "vendor": "Hausmarke", "tags": ["neu"],
      "images": [{ "url": "/uploads/…jpg", "alt": "" }],
      "options": [{ "name": "Größe", "values": ["S", "M", "L"] }],
      "variants": [
        { "id": 11, "title": "S", "sku": "LEI-001", "price": 8900,
          "compare_at_price": 11900, "option1": "S" }
      ],
      "min_price": 8900, "max_price": 8900, "max_compare_at": 11900
    }
  ]
}
```

Verfügbarkeiten stehen bewusst **nicht** im Katalog — sie ändern sich zwischen
zwei Veröffentlichungen. Der Warenkorb liefert sie je Position mit.

### `GET /api/products/:handle`

Ein einzelner Artikel aus dem Katalog.

## Warenkorb

| Methode | Pfad | Rumpf |
|---|---|---|
| `GET` | `/api/cart` | — |
| `POST` | `/api/cart/add` | `{ "variant_id": 11, "quantity": 2 }` |
| `POST` | `/api/cart/update` | `{ "variant_id": 11, "quantity": 3 }` (0 entfernt) |
| `POST` | `/api/cart/remove` | `{ "variant_id": 11 }` |
| `POST` | `/api/cart/clear` | — |
| `PATCH` | `/api/cart` | `{ "country": "AT", "discount_code": "SOMMER10", "shipping_rate_id": 2, "email": "…" }` |

Alle geben denselben Warenkorb zurück:

```json
{
  "token": "…", "currency": "EUR", "country": "DE", "item_count": 2,
  "lines": [
    { "variant_id": 11, "product_id": 4, "handle": "leinenhemd-sommer",
      "title": "Leinenhemd Sommer", "variant_title": "M", "sku": "LEI-002",
      "image_url": "/uploads/…jpg", "quantity": 2,
      "price": 8900, "discount": 890, "total": 16910,
      "tax_rate_bp": 1900, "tax_amount": 2700,
      "available": 12, "over_stock": false }
  ],
  "subtotal": 17800,
  "discount_total": 890, "discount_code": "SOMMER10", "discount_error": "",
  "shipping_total": 0,
  "shipping_rate": { "id": 1, "name": "Standardversand", "effective_price": 0 },
  "shipping_rates": [ "…" ],
  "requires_shipping": true,
  "tax_total": 2700,
  "tax_lines": [{ "rate_bp": 1900, "base": 16910, "amount": 2700 }],
  "total": 16910,
  "has_stock_issue": false,
  "below_minimum": false
}
```

Ein ungültiger Rabattcode ist **kein Fehler**: die Antwort kommt mit `200`,
`discount_total: 0` und einer für Kunden lesbaren `discount_error`.

Reicht der Bestand nicht, antwortet `add`/`update` mit `400` und nennt in
`details.available` die verfügbare Menge.

## Rabatt und Versand vorab prüfen

### `POST /api/discount/check` — `{ "code": "SOMMER10" }`

```json
{ "ok": true, "reason": "", "discount": { "code": "SOMMER10", "type": "percentage" } }
```

### `GET /api/shipping/rates?country=AT`

```json
{ "country": "AT", "rates": [
  { "id": 3, "name": "Standardversand", "price": 990, "effective_price": 990,
    "free_over": 12000, "free_applied": false, "delivery_time": "3–5 Werktage" }
]}
```

## Kasse

### `GET /api/checkout`

Warenkorb, verfügbare Zahlarten und die Kasseneinstellungen in einer Antwort.

### `GET /api/payment-providers`

```json
{ "providers": [
  { "id": "stripe", "label": "Kredit- / Debitkarte", "redirects": true },
  { "id": "invoice", "label": "Kauf auf Rechnung",
    "instructions": "Zahlbar innerhalb von 14 Tagen.", "redirects": false }
]}
```

### `POST /api/checkout`

```json
{
  "email": "kunde@example.com",
  "phone": "",
  "payment_provider": "stripe",
  "accept_terms": true,
  "accepts_marketing": false,
  "note": "",
  "shipping_address": {
    "first_name": "Anna", "last_name": "Beispiel", "company": "",
    "address1": "Hauptstraße 1", "address2": "",
    "zip": "10115", "city": "Berlin", "country": "DE"
  },
  "billing_same": true
}
```

Antwort:

```json
{
  "order": { "id": 42, "number": 1042, "token": "…", "total": 16910,
             "currency": "EUR", "financial_status": "pending" },
  "action": "redirect",
  "redirect_url": "https://checkout.stripe.com/…",
  "status_url": "/order/…"
}
```

`action` ist `"redirect"` (Kunde zum Anbieter schicken) oder `"complete"`
(Bestellung steht, direkt zur Statusseite). Nach der Rückkehr vom Anbieter ruft
der Shop `POST /api/orders/:token/confirm` auf und fragt den Zahlungsstatus
beim Anbieter nach — die bloße Rückkehr gilt nicht als Zahlungsnachweis.

## Bestellstatus

### `GET /api/orders/:token`

Der Token ist der lange Zufallswert aus `status_url`. Interne Notizen,
Zahlungsrohdaten und der Ereignisverlauf sind aus der Kundenansicht entfernt.

### `POST /api/orders/:token/confirm`

Fragt den Zahlungsstatus beim Anbieter nach und bucht ihn. Idempotent.

---

# Admin-API

## Anmelden

### `POST /api/admin/login` — `{ "email": "…", "password": "…" }`

```json
{ "user": { "id": 1, "email": "…", "name": "Inhaber", "role": "owner" },
  "csrf_token": "…" }
```

Setzt das Cookie `shop_admin_session` (HttpOnly, SameSite=Lax). **Jede
schreibende Anfrage** muss das Token als Header `X-CSRF-Token` mitschicken —
sonst antwortet der Server mit `403`.

`GET /api/admin/me` liefert Benutzer, ein frisches CSRF-Token und den
Veröffentlichungsstand. `POST /api/admin/logout` beendet die Sitzung sofort und
serverseitig.

## Rollen

`owner` und `admin` dürfen alles. `staff` darf Katalog, Bestellungen, Kunden und
Inhalte pflegen, aber keine Einstellungen, Versandzonen, Steuersätze, Benutzer
oder Rollbacks ändern.

## Endpunkte

### Übersicht
- `GET /dashboard?days=30` — Kennzahlen, Vergleich zum Vorzeitraum, Tagesumsätze,
  Bestseller, knappe Bestände, letzte Bestellungen, offene Änderungen

### Artikel
- `GET /products?status=&search=&type=&vendor=&tag=&collection_id=&sort=&limit=&offset=`
- `GET /products/meta` — Hersteller, Typen, Schlagwörter, Steuersätze, Kategorien
- `GET|PUT|DELETE /products/:id`
- `POST /products`
- `POST /products/:id/duplicate` — Kopie als Entwurf
- `POST /products/:id/status` — `{ "status": "active" }`
- `POST /products/bulk` — `{ "ids": [1,2], "action": "status|delete|add_tag|add_to_collection", … }`

Beim Anlegen und Ändern:

```json
{
  "title": "Leinenhemd", "status": "active", "handle": "leinenhemd",
  "body_html": "<p>…</p>", "vendor": "…", "product_type": "…",
  "tags": ["neu", "sommer"],
  "images": [{ "url": "/uploads/…jpg", "alt": "" }],
  "options": [{ "name": "Größe", "values": ["S", "M"] }],
  "variants": [
    { "id": 11, "title": "S", "price": "89,00", "compare_at_price": "119,00",
      "sku": "LEI-001", "inventory_quantity": 12,
      "track_inventory": true, "inventory_policy": "deny" }
  ]
}
```

Preise werden sowohl als Cent-Integer (`8900`) als auch als Text (`"89,00"`,
`"89.00"`) angenommen. Varianten **mit** `id` werden aktualisiert, **ohne** `id`
neu angelegt, und nicht mitgeschickte werden gelöscht — bestehende IDs bleiben
also erhalten, damit Bestellpositionen und Bestandsbewegungen ihre Referenz
behalten. `body_html` wird beim Speichern bereinigt.

### Bestand
- `GET /inventory?search=`
- `POST /inventory/:variantId` — `{ "set": 20 }` oder `{ "delta": -3, "reason": "correction" }`
- `GET /inventory/:variantId/moves`

### Kategorien
- `GET /collections?search=` · `GET|PUT|DELETE /collections/:id` · `POST /collections`

```json
{ "title": "Neuheiten", "published": true,
  "rule_type": "auto", "rules_match": "all",
  "rules": [{ "field": "tag", "operator": "equals", "value": "neu" }],
  "sort_order": "created-desc" }
```

Felder: `tag`, `product_type`, `vendor`, `title`, `price`.
Operatoren: `equals`, `not_equals`, `contains`, `starts_with`, `greater_than`,
`less_than`. Bei `rule_type: "manual"` stattdessen `product_ids` schicken.

### Bestellungen
- `GET /orders?search=&status=&financial_status=&fulfillment_status=&customer_id=&from=&to=&sort=`
- `GET /orders/:id`
- `POST /orders/:id/paid` — `{ "reference": "Überweisung 12.03." }`
- `POST /orders/:id/fulfill` — `{ "lines": [{ "line_id": 9, "quantity": 2 }], "carrier": "DHL", "tracking_number": "…", "tracking_url": "…" }` (ohne `lines` wird alles Offene versendet)
- `POST /orders/:id/refund` — `{ "amount": 1000, "reason": "…", "restock": true }`
- `POST /orders/:id/cancel` — `{ "reason": "…", "restock": true }`
- `POST /orders/:id/archive` — `{ "archived": true }`
- `POST /orders/:id/note` — `{ "note": "…" }`

### Kunden, Rabatte, Inhalte
- `GET /customers?search=&sort=` · `GET|PUT|DELETE /customers/:id` · `POST /customers`
- `GET /discounts?search=` · `POST /discounts` · `PUT|DELETE /discounts/:id`
- `GET /pages` · `GET|PUT|DELETE /pages/:id` · `POST /pages`
- `GET /posts` · `GET|PUT|DELETE /posts/:id` · `POST /posts`
- `GET /menus` · `PUT /menus/:handle` — `{ "items": [{ "label": "…", "url": "…", "children": [] }] }`
- `GET /media` · `DELETE /media/:id`
- `POST /admin/upload` — `multipart/form-data`, Feld `file`, max. 8 MB

### Einstellungen (`owner` / `admin`)
- `GET /settings` — alle Gruppen, Versandzonen, Steuersätze, Zahlarten
- `PUT /settings/:group` — `store`, `theme`, `checkout`, `payments`, `legal`
- `POST /shipping/zones` · `PUT|DELETE /shipping/zones/:id`
- `POST /tax-rates` · `PUT|DELETE /tax-rates/:id`
- `GET /users` · `POST /users` · `DELETE /users/:id`
- `POST /users/:id/password` — das eigene Passwort darf jeder ändern

### Veröffentlichen
- `GET /publish` — offene Änderungen, Versionsverlauf, live geschaltete Version
- `POST /publish` — `{ "note": "Herbstkollektion" }`
- `POST /publish/rollback` — `{ "version": 3 }`
- `POST /publish/export` — `{ "base_url": "https://dein-shop.de" }`

---

# Webhooks

- `POST /webhooks/stripe` — HMAC-Signatur im Header `Stripe-Signature`
- `POST /webhooks/paypal` — Signaturprüfung per Rückfrage bei PayPal

Ohne gültige Signatur antworten beide mit `400` und buchen nichts. Jede
`event_id` wird höchstens einmal verarbeitet; die Rohdaten landen in der
Tabelle `webhook_events` und sind bei Problemen dort nachlesbar.
