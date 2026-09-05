-- ---------------------------------------------------------------------------
-- Shop-CMS Schema
--
-- Konventionen:
--   * Alle Geldbeträge sind Integer in der kleinsten Währungseinheit (Cent).
--     Fließkomma-Arithmetik auf Preisen ist die Ursache der meisten Rundungs-
--     bugs in Shops; Cent-Integer machen Summen exakt.
--   * Zeitstempel sind ISO-8601-Strings in UTC (TEXT), damit sie sortierbar
--     und ohne Konvertierung lesbar sind.
--   * Booleans sind INTEGER 0/1.
--   * "status"-Spalten folgen der Shopify-Terminologie (draft/active/archived),
--     damit die Begriffe im Admin vertraut wirken.
-- ---------------------------------------------------------------------------

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- --- Benutzer & Sessions (Admin) -------------------------------------------

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  name          TEXT NOT NULL DEFAULT '',
  role          TEXT NOT NULL DEFAULT 'staff',   -- owner | admin | staff
  active        INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
  token      TEXT PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  user_agent TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);

-- --- Medien -----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS media (
  id         INTEGER PRIMARY KEY,
  filename   TEXT NOT NULL,
  url        TEXT NOT NULL,
  mime       TEXT NOT NULL DEFAULT '',
  size       INTEGER NOT NULL DEFAULT 0,
  width      INTEGER,
  height     INTEGER,
  alt        TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);

-- --- Produkte ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS products (
  id              INTEGER PRIMARY KEY,
  handle          TEXT NOT NULL UNIQUE,          -- URL-Slug im Shop
  title           TEXT NOT NULL,
  subtitle        TEXT NOT NULL DEFAULT '',
  body_html       TEXT NOT NULL DEFAULT '',
  vendor          TEXT NOT NULL DEFAULT '',
  product_type    TEXT NOT NULL DEFAULT '',
  tags            TEXT NOT NULL DEFAULT '',      -- kommagetrennt
  status          TEXT NOT NULL DEFAULT 'draft', -- draft | active | archived
  seo_title       TEXT NOT NULL DEFAULT '',
  seo_description TEXT NOT NULL DEFAULT '',
  position        INTEGER NOT NULL DEFAULT 0,
  published_at    TEXT,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);
CREATE INDEX IF NOT EXISTS idx_products_type ON products(product_type);

CREATE TABLE IF NOT EXISTS product_images (
  id         INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  url        TEXT NOT NULL,
  alt        TEXT NOT NULL DEFAULT '',
  position   INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_product_images_product ON product_images(product_id);

-- Optionen sind die Achsen der Variantenmatrix (z. B. "Größe", "Farbe").
CREATE TABLE IF NOT EXISTS product_options (
  id         INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name       TEXT NOT NULL,
  values_json TEXT NOT NULL DEFAULT '[]',
  position   INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_product_options_product ON product_options(product_id);

-- Die Variante ist die kaufbare Einheit: Preis und Bestand hängen hier, nicht
-- am Produkt. Produkte ohne Varianten bekommen genau eine Default-Variante.
CREATE TABLE IF NOT EXISTS variants (
  id                 INTEGER PRIMARY KEY,
  product_id         INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  title              TEXT NOT NULL DEFAULT 'Standard',
  sku                TEXT NOT NULL DEFAULT '',
  barcode            TEXT NOT NULL DEFAULT '',
  price              INTEGER NOT NULL DEFAULT 0,   -- Cent, brutto
  compare_at_price   INTEGER,                      -- Streichpreis, Cent
  cost               INTEGER,                      -- Einkaufspreis, Cent
  option1            TEXT NOT NULL DEFAULT '',
  option2            TEXT NOT NULL DEFAULT '',
  option3            TEXT NOT NULL DEFAULT '',
  inventory_quantity INTEGER NOT NULL DEFAULT 0,
  track_inventory    INTEGER NOT NULL DEFAULT 1,
  inventory_policy   TEXT NOT NULL DEFAULT 'deny', -- deny | continue (Überverkauf)
  weight_grams       INTEGER NOT NULL DEFAULT 0,
  requires_shipping  INTEGER NOT NULL DEFAULT 1,
  taxable            INTEGER NOT NULL DEFAULT 1,
  tax_rate_id        INTEGER REFERENCES tax_rates(id) ON DELETE SET NULL,
  image_url          TEXT NOT NULL DEFAULT '',
  position           INTEGER NOT NULL DEFAULT 0,
  created_at         TEXT NOT NULL,
  updated_at         TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_variants_product ON variants(product_id);
CREATE INDEX IF NOT EXISTS idx_variants_sku ON variants(sku);

CREATE TABLE IF NOT EXISTS inventory_moves (
  id         INTEGER PRIMARY KEY,
  variant_id INTEGER NOT NULL REFERENCES variants(id) ON DELETE CASCADE,
  delta      INTEGER NOT NULL,
  reason     TEXT NOT NULL DEFAULT '',   -- sale | restock | correction | refund
  order_id   INTEGER REFERENCES orders(id) ON DELETE SET NULL,
  user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_inventory_moves_variant ON inventory_moves(variant_id);

-- --- Kategorien / Kollektionen ----------------------------------------------

CREATE TABLE IF NOT EXISTS collections (
  id              INTEGER PRIMARY KEY,
  handle          TEXT NOT NULL UNIQUE,
  title           TEXT NOT NULL,
  body_html       TEXT NOT NULL DEFAULT '',
  image_url       TEXT NOT NULL DEFAULT '',
  rule_type       TEXT NOT NULL DEFAULT 'manual',  -- manual | auto
  rules_json      TEXT NOT NULL DEFAULT '[]',
  rules_match     TEXT NOT NULL DEFAULT 'all',     -- all | any
  sort_order      TEXT NOT NULL DEFAULT 'manual',  -- manual | title-asc | price-asc | price-desc | created-desc
  published       INTEGER NOT NULL DEFAULT 0,
  position        INTEGER NOT NULL DEFAULT 0,
  seo_title       TEXT NOT NULL DEFAULT '',
  seo_description TEXT NOT NULL DEFAULT '',
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS collection_products (
  collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
  product_id    INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  position      INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id, product_id)
);

-- --- Inhalte ----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS pages (
  id              INTEGER PRIMARY KEY,
  handle          TEXT NOT NULL UNIQUE,
  title           TEXT NOT NULL,
  body_html       TEXT NOT NULL DEFAULT '',
  published       INTEGER NOT NULL DEFAULT 0,
  seo_title       TEXT NOT NULL DEFAULT '',
  seo_description TEXT NOT NULL DEFAULT '',
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS posts (
  id              INTEGER PRIMARY KEY,
  handle          TEXT NOT NULL UNIQUE,
  title           TEXT NOT NULL,
  excerpt         TEXT NOT NULL DEFAULT '',
  body_html       TEXT NOT NULL DEFAULT '',
  image_url       TEXT NOT NULL DEFAULT '',
  author          TEXT NOT NULL DEFAULT '',
  tags            TEXT NOT NULL DEFAULT '',
  published       INTEGER NOT NULL DEFAULT 0,
  published_at    TEXT,
  seo_title       TEXT NOT NULL DEFAULT '',
  seo_description TEXT NOT NULL DEFAULT '',
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS menus (
  id     INTEGER PRIMARY KEY,
  handle TEXT NOT NULL UNIQUE,   -- main | footer
  title  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS menu_items (
  id        INTEGER PRIMARY KEY,
  menu_id   INTEGER NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
  parent_id INTEGER REFERENCES menu_items(id) ON DELETE CASCADE,
  label     TEXT NOT NULL,
  url       TEXT NOT NULL,
  position  INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_menu_items_menu ON menu_items(menu_id);

-- --- Kunden -----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS customers (
  id                INTEGER PRIMARY KEY,
  email             TEXT NOT NULL UNIQUE,
  first_name        TEXT NOT NULL DEFAULT '',
  last_name         TEXT NOT NULL DEFAULT '',
  phone             TEXT NOT NULL DEFAULT '',
  company           TEXT NOT NULL DEFAULT '',
  accepts_marketing INTEGER NOT NULL DEFAULT 0,
  note              TEXT NOT NULL DEFAULT '',
  tags              TEXT NOT NULL DEFAULT '',
  orders_count      INTEGER NOT NULL DEFAULT 0,
  total_spent       INTEGER NOT NULL DEFAULT 0,
  created_at        TEXT NOT NULL,
  updated_at        TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS addresses (
  id          INTEGER PRIMARY KEY,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  first_name  TEXT NOT NULL DEFAULT '',
  last_name   TEXT NOT NULL DEFAULT '',
  company     TEXT NOT NULL DEFAULT '',
  address1    TEXT NOT NULL DEFAULT '',
  address2    TEXT NOT NULL DEFAULT '',
  zip         TEXT NOT NULL DEFAULT '',
  city        TEXT NOT NULL DEFAULT '',
  province    TEXT NOT NULL DEFAULT '',
  country     TEXT NOT NULL DEFAULT 'DE',
  phone       TEXT NOT NULL DEFAULT '',
  is_default  INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_addresses_customer ON addresses(customer_id);

-- --- Steuern & Versand ------------------------------------------------------

CREATE TABLE IF NOT EXISTS tax_rates (
  id         INTEGER PRIMARY KEY,
  name       TEXT NOT NULL,
  rate_bp    INTEGER NOT NULL,            -- Basispunkte: 1900 = 19,00 %
  country    TEXT NOT NULL DEFAULT 'DE',
  is_default INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS shipping_zones (
  id             INTEGER PRIMARY KEY,
  name           TEXT NOT NULL,
  countries_json TEXT NOT NULL DEFAULT '[]',  -- ["DE","AT"] – leer = Rest der Welt
  position       INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS shipping_rates (
  id            INTEGER PRIMARY KEY,
  zone_id       INTEGER NOT NULL REFERENCES shipping_zones(id) ON DELETE CASCADE,
  name          TEXT NOT NULL,
  description   TEXT NOT NULL DEFAULT '',
  price         INTEGER NOT NULL DEFAULT 0,   -- Cent
  min_subtotal  INTEGER,                      -- Bedingung: Warenwert ab
  max_subtotal  INTEGER,                      -- Bedingung: Warenwert bis
  free_over     INTEGER,                      -- kostenlos ab Warenwert
  delivery_time TEXT NOT NULL DEFAULT '',
  position      INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_shipping_rates_zone ON shipping_rates(zone_id);

-- --- Rabatte ----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS discounts (
  id               INTEGER PRIMARY KEY,
  code             TEXT NOT NULL UNIQUE,
  title            TEXT NOT NULL DEFAULT '',
  type             TEXT NOT NULL DEFAULT 'percentage', -- percentage | fixed | free_shipping
  value            INTEGER NOT NULL DEFAULT 0,         -- Prozent*100 oder Cent
  applies_to       TEXT NOT NULL DEFAULT 'all',        -- all | collection | product
  target_ids_json  TEXT NOT NULL DEFAULT '[]',
  min_subtotal     INTEGER NOT NULL DEFAULT 0,
  usage_limit      INTEGER,
  once_per_customer INTEGER NOT NULL DEFAULT 0,
  used_count       INTEGER NOT NULL DEFAULT 0,
  starts_at        TEXT,
  ends_at          TEXT,
  active           INTEGER NOT NULL DEFAULT 1,
  created_at       TEXT NOT NULL,
  updated_at       TEXT NOT NULL
);

-- --- Warenkörbe -------------------------------------------------------------

CREATE TABLE IF NOT EXISTS carts (
  id            INTEGER PRIMARY KEY,
  token         TEXT NOT NULL UNIQUE,
  customer_id   INTEGER REFERENCES customers(id) ON DELETE SET NULL,
  email         TEXT NOT NULL DEFAULT '',
  discount_code TEXT NOT NULL DEFAULT '',
  country       TEXT NOT NULL DEFAULT 'DE',
  shipping_rate_id INTEGER REFERENCES shipping_rates(id) ON DELETE SET NULL,
  note          TEXT NOT NULL DEFAULT '',
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cart_lines (
  id         INTEGER PRIMARY KEY,
  cart_id    INTEGER NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
  variant_id INTEGER NOT NULL REFERENCES variants(id) ON DELETE CASCADE,
  quantity   INTEGER NOT NULL DEFAULT 1,
  UNIQUE (cart_id, variant_id)
);

-- --- Bestellungen -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS orders (
  id                 INTEGER PRIMARY KEY,
  number             INTEGER NOT NULL UNIQUE,      -- fortlaufende Bestellnummer
  token              TEXT NOT NULL UNIQUE,         -- für die Statusseite ohne Login
  customer_id        INTEGER REFERENCES customers(id) ON DELETE SET NULL,
  email              TEXT NOT NULL DEFAULT '',
  phone              TEXT NOT NULL DEFAULT '',
  status             TEXT NOT NULL DEFAULT 'open', -- open | archived | cancelled
  financial_status   TEXT NOT NULL DEFAULT 'pending', -- pending | authorized | paid | partially_refunded | refunded | voided
  fulfillment_status TEXT NOT NULL DEFAULT 'unfulfilled', -- unfulfilled | partial | fulfilled
  currency           TEXT NOT NULL DEFAULT 'EUR',
  subtotal           INTEGER NOT NULL DEFAULT 0,
  discount_total     INTEGER NOT NULL DEFAULT 0,
  shipping_total     INTEGER NOT NULL DEFAULT 0,
  tax_total          INTEGER NOT NULL DEFAULT 0,
  total              INTEGER NOT NULL DEFAULT 0,
  refunded_total     INTEGER NOT NULL DEFAULT 0,
  discount_code      TEXT NOT NULL DEFAULT '',
  shipping_method    TEXT NOT NULL DEFAULT '',
  shipping_address   TEXT NOT NULL DEFAULT '{}',   -- JSON
  billing_address    TEXT NOT NULL DEFAULT '{}',   -- JSON
  payment_provider   TEXT NOT NULL DEFAULT '',
  payment_reference  TEXT NOT NULL DEFAULT '',
  payment_status_detail TEXT NOT NULL DEFAULT '',
  note               TEXT NOT NULL DEFAULT '',
  customer_note      TEXT NOT NULL DEFAULT '',
  cancelled_at       TEXT,
  paid_at            TEXT,
  created_at         TEXT NOT NULL,
  updated_at         TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status, financial_status);

CREATE TABLE IF NOT EXISTS order_lines (
  id            INTEGER PRIMARY KEY,
  order_id      INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  variant_id    INTEGER REFERENCES variants(id) ON DELETE SET NULL,
  product_id    INTEGER REFERENCES products(id) ON DELETE SET NULL,
  title         TEXT NOT NULL,
  variant_title TEXT NOT NULL DEFAULT '',
  sku           TEXT NOT NULL DEFAULT '',
  image_url     TEXT NOT NULL DEFAULT '',
  quantity      INTEGER NOT NULL DEFAULT 1,
  price         INTEGER NOT NULL DEFAULT 0,   -- Einzelpreis brutto in Cent
  discount      INTEGER NOT NULL DEFAULT 0,   -- anteiliger Rabatt in Cent
  total         INTEGER NOT NULL DEFAULT 0,   -- price*quantity - discount
  tax_rate_bp   INTEGER NOT NULL DEFAULT 0,
  tax_amount    INTEGER NOT NULL DEFAULT 0,
  fulfilled_quantity INTEGER NOT NULL DEFAULT 0,
  requires_shipping INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_order_lines_order ON order_lines(order_id);

CREATE TABLE IF NOT EXISTS order_events (
  id         INTEGER PRIMARY KEY,
  order_id   INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  type       TEXT NOT NULL,
  message    TEXT NOT NULL DEFAULT '',
  data_json  TEXT NOT NULL DEFAULT '{}',
  user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_order_events_order ON order_events(order_id);

CREATE TABLE IF NOT EXISTS fulfillments (
  id              INTEGER PRIMARY KEY,
  order_id        INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  carrier         TEXT NOT NULL DEFAULT '',
  tracking_number TEXT NOT NULL DEFAULT '',
  tracking_url    TEXT NOT NULL DEFAULT '',
  lines_json      TEXT NOT NULL DEFAULT '[]',
  notified        INTEGER NOT NULL DEFAULT 0,
  created_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS refunds (
  id         INTEGER PRIMARY KEY,
  order_id   INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  amount     INTEGER NOT NULL,
  reason     TEXT NOT NULL DEFAULT '',
  restock    INTEGER NOT NULL DEFAULT 0,
  reference  TEXT NOT NULL DEFAULT '',
  user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL
);

-- --- Zahlungen --------------------------------------------------------------

CREATE TABLE IF NOT EXISTS payments (
  id           INTEGER PRIMARY KEY,
  order_id     INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  provider     TEXT NOT NULL,
  reference    TEXT NOT NULL DEFAULT '',
  amount       INTEGER NOT NULL DEFAULT 0,
  currency     TEXT NOT NULL DEFAULT 'EUR',
  status       TEXT NOT NULL DEFAULT 'pending', -- pending | authorized | paid | failed | refunded
  raw_json     TEXT NOT NULL DEFAULT '{}',
  created_at   TEXT NOT NULL,
  updated_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_payments_order ON payments(order_id);

-- Rohdaten eingehender Webhooks. Der Eintrag entsteht vor der Verarbeitung und
-- macht die Verarbeitung idempotent: dieselbe event_id wird nie zweimal gebucht.
CREATE TABLE IF NOT EXISTS webhook_events (
  id           INTEGER PRIMARY KEY,
  provider     TEXT NOT NULL,
  event_id     TEXT NOT NULL,
  type         TEXT NOT NULL DEFAULT '',
  payload_json TEXT NOT NULL DEFAULT '{}',
  processed_at TEXT,
  error        TEXT NOT NULL DEFAULT '',
  created_at   TEXT NOT NULL,
  UNIQUE (provider, event_id)
);

-- --- Einstellungen ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS settings (
  key        TEXT PRIMARY KEY,
  value_json TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

-- --- Veröffentlichungen -----------------------------------------------------
--
-- Der Kern des "Push ins Frontend": Ein Publish friert den kompletten
-- veröffentlichungsfähigen Zustand als JSON-Snapshot ein. Die Storefront liest
-- ausschließlich aus dem Snapshot, nie aus den Arbeitstabellen – Entwürfe im
-- Backend können deshalb nie versehentlich im Shop erscheinen, und ein Rollback
-- ist ein einzelnes UPDATE auf "live".
CREATE TABLE IF NOT EXISTS publications (
  id            INTEGER PRIMARY KEY,
  version       INTEGER NOT NULL UNIQUE,
  note          TEXT NOT NULL DEFAULT '',
  snapshot_json TEXT NOT NULL,
  stats_json    TEXT NOT NULL DEFAULT '{}',
  live          INTEGER NOT NULL DEFAULT 0,
  user_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_publications_live ON publications(live);
