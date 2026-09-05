/*
 * Datenbankzugriff auf Basis von node:sqlite (in Node 22.5+ eingebaut).
 *
 * Bewusst kein ORM: die Abfragen dieses Shops sind überschaubar, und rohes SQL
 * bleibt lesbar und schnell. Die Helfer hier decken das ab, was ein ORM sonst
 * beisteuert – Parameterbindung, Transaktionen, JSON-Spalten.
 */

import { DatabaseSync } from 'node:sqlite';
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import config from '../config.js';

const here = dirname(fileURLToPath(import.meta.url));

let db = null;

export function getDb() {
  if (db) return db;
  mkdirSync(dirname(config.databaseFile), { recursive: true });
  db = new DatabaseSync(config.databaseFile);
  db.exec('PRAGMA journal_mode = WAL;');
  db.exec('PRAGMA foreign_keys = ON;');
  db.exec('PRAGMA busy_timeout = 5000;');
  return db;
}

/** Legt fehlende Tabellen an. Idempotent – das Schema nutzt IF NOT EXISTS. */
export function migrate(target = getDb()) {
  const sql = readFileSync(resolve(here, 'schema.sql'), 'utf8');
  target.exec(sql);
  return target;
}

/**
 * Öffnet eine eigene In-Memory-Datenbank. Tests benutzen das, damit sie sich
 * nicht gegenseitig oder die Entwicklungsdatenbank beeinflussen.
 */
export function openMemoryDb() {
  const mem = new DatabaseSync(':memory:');
  mem.exec('PRAGMA foreign_keys = ON;');
  migrate(mem);
  return mem;
}

/** Ersetzt die Prozess-Datenbank (Tests). */
export function setDb(next) {
  db = next;
  return db;
}

// --- Query-Helfer -----------------------------------------------------------
//
// node:sqlite bindet nur null/number/string/bigint/Buffer. Booleans und
// undefined kommen in unserem Code aber ständig vor, deshalb normalisieren wir
// Parameter an genau einer Stelle statt an hunderten Aufrufstellen.

function normalize(value) {
  if (value === undefined || value === null) return null;
  if (typeof value === 'boolean') return value ? 1 : 0;
  if (value instanceof Date) return value.toISOString();
  return value;
}

function bind(params) {
  if (Array.isArray(params)) return params.map(normalize);
  const out = {};
  for (const [key, value] of Object.entries(params)) out[key] = normalize(value);
  return out;
}

export function all(sql, params = []) {
  const stmt = getDb().prepare(sql);
  const rows = Array.isArray(params) ? stmt.all(...bind(params)) : stmt.all(bind(params));
  return rows.map((row) => ({ ...row }));
}

export function get(sql, params = []) {
  const stmt = getDb().prepare(sql);
  const row = Array.isArray(params) ? stmt.get(...bind(params)) : stmt.get(bind(params));
  return row ? { ...row } : null;
}

export function run(sql, params = []) {
  const stmt = getDb().prepare(sql);
  const result = Array.isArray(params) ? stmt.run(...bind(params)) : stmt.run(bind(params));
  return {
    changes: Number(result.changes),
    lastInsertRowid: Number(result.lastInsertRowid),
  };
}

export function pluck(sql, params = []) {
  const row = get(sql, params);
  if (!row) return null;
  return Object.values(row)[0];
}

/**
 * Führt fn in einer Transaktion aus. Verschachtelte Aufrufe teilen sich die
 * äußere Transaktion (SQLite kennt kein echtes Nesting), damit Services sich
 * gefahrlos gegenseitig aufrufen können.
 */
let txDepth = 0;
export function transaction(fn) {
  const database = getDb();
  if (txDepth > 0) return fn();
  database.exec('BEGIN');
  txDepth += 1;
  try {
    const result = fn();
    database.exec('COMMIT');
    return result;
  } catch (error) {
    try {
      database.exec('ROLLBACK');
    } catch {
      /* Rollback nach fatalem Fehler darf den Originalfehler nicht verdecken. */
    }
    throw error;
  } finally {
    txDepth -= 1;
  }
}

/** Baut `INSERT INTO t (...) VALUES (...)` aus einem Objekt. */
export function insert(table, data) {
  const keys = Object.keys(data);
  const columns = keys.join(', ');
  const placeholders = keys.map(() => '?').join(', ');
  const result = run(
    `INSERT INTO ${table} (${columns}) VALUES (${placeholders})`,
    keys.map((k) => data[k]),
  );
  return result.lastInsertRowid;
}

/** Baut `UPDATE t SET ... WHERE id = ?` aus einem Objekt. */
export function update(table, id, data, idColumn = 'id') {
  const keys = Object.keys(data);
  if (keys.length === 0) return 0;
  const assignments = keys.map((k) => `${k} = ?`).join(', ');
  const result = run(
    `UPDATE ${table} SET ${assignments} WHERE ${idColumn} = ?`,
    [...keys.map((k) => data[k]), id],
  );
  return result.changes;
}

export function remove(table, id, idColumn = 'id') {
  return run(`DELETE FROM ${table} WHERE ${idColumn} = ?`, [id]).changes;
}

/** JSON-Spalte tolerant lesen: kaputte Daten kippen nie eine Seite. */
export function parseJson(text, fallback) {
  if (text === null || text === undefined || text === '') return fallback;
  try {
    return JSON.parse(text);
  } catch {
    return fallback;
  }
}

export const nowIso = () => new Date().toISOString();
