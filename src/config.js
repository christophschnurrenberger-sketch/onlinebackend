/*
 * Konfiguration.
 *
 * Wir lesen eine .env-Datei selbst ein statt dotenv zu installieren: das Projekt
 * kommt bewusst ohne Runtime-Dependencies aus, damit es auf jedem Host mit
 * Node >= 22.5 ohne "npm install" startet. Echte Umgebungsvariablen gewinnen
 * immer gegen die Datei, damit Hosting-Plattformen die Datei überschreiben können.
 */

import { readFileSync, existsSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');

function loadEnvFile(file) {
  if (!existsSync(file)) return;
  const text = readFileSync(file, 'utf8');
  for (const rawLine of text.split('\n')) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) continue;
    const eq = line.indexOf('=');
    if (eq === -1) continue;
    const key = line.slice(0, eq).trim();
    let value = line.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (process.env[key] === undefined) process.env[key] = value;
  }
}

loadEnvFile(resolve(ROOT, '.env'));

const env = process.env;

function bool(value, fallback = false) {
  if (value === undefined || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

function list(value, fallback = []) {
  if (!value) return fallback;
  return String(value)
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);
}

export const config = {
  env: env.NODE_ENV || 'development',
  get isProduction() {
    return this.env === 'production';
  },
  port: Number(env.PORT || 3000),
  host: env.HOST || '0.0.0.0',
  baseUrl: (env.BASE_URL || `http://localhost:${env.PORT || 3000}`).replace(/\/+$/, ''),

  databaseFile: resolve(ROOT, env.DATABASE_FILE || './data/shop.db'),
  exportDir: resolve(ROOT, env.EXPORT_DIR || './dist'),
  uploadDir: resolve(ROOT, 'public/uploads'),

  // Ohne gesetztes Secret laufen Sessions nur bis zum Neustart – das ist für
  // die lokale Entwicklung genau richtig und in Produktion ein lauter Fehler.
  sessionSecret: env.SESSION_SECRET || randomBytes(32).toString('hex'),
  sessionSecretProvided: Boolean(env.SESSION_SECRET),
  sessionTtlHours: Number(env.SESSION_TTL_HOURS || 24 * 14),
  secureCookies: bool(env.SECURE_COOKIES, env.NODE_ENV === 'production'),

  admin: {
    email: env.ADMIN_EMAIL || 'admin@example.com',
    password: env.ADMIN_PASSWORD || 'admin12345',
  },

  payments: {
    enabled: list(env.PAYMENT_PROVIDERS, ['mock', 'invoice', 'prepayment']),
    stripe: {
      secretKey: env.STRIPE_SECRET_KEY || '',
      publishableKey: env.STRIPE_PUBLISHABLE_KEY || '',
      webhookSecret: env.STRIPE_WEBHOOK_SECRET || '',
    },
    paypal: {
      clientId: env.PAYPAL_CLIENT_ID || '',
      clientSecret: env.PAYPAL_CLIENT_SECRET || '',
      env: env.PAYPAL_ENV || 'sandbox',
      webhookId: env.PAYPAL_WEBHOOK_ID || '',
    },
  },
};

export default config;
