/*
 * Authentifizierung für das Backend.
 *
 * Passwörter: scrypt aus node:crypto. Kein bcrypt-Paket nötig, und scrypt ist
 * speicherhart – für Admin-Logins die richtige Wahl.
 * Sessions: zufälliges Token im HttpOnly-Cookie, serverseitig in der DB. Kein
 * JWT, weil ein Logout dann sofort und wirklich wirkt.
 */

import { randomBytes, scryptSync, timingSafeEqual, createHash } from 'node:crypto';
import { all, get, run, insert, update, nowIso } from '../db/index.js';
import config from '../config.js';

const SCRYPT = { N: 16384, r: 8, p: 1, keylen: 64 };

export function hashPassword(password) {
  const salt = randomBytes(16).toString('hex');
  const key = scryptSync(password, salt, SCRYPT.keylen, SCRYPT).toString('hex');
  return `scrypt$${SCRYPT.N}$${SCRYPT.r}$${SCRYPT.p}$${salt}$${key}`;
}

export function verifyPassword(password, stored) {
  if (!stored || !stored.startsWith('scrypt$')) return false;
  const [, N, r, p, salt, key] = stored.split('$');
  try {
    const derived = scryptSync(password, salt, SCRYPT.keylen, {
      N: Number(N),
      r: Number(r),
      p: Number(p),
    });
    const expected = Buffer.from(key, 'hex');
    if (expected.length !== derived.length) return false;
    return timingSafeEqual(derived, expected);
  } catch {
    return false;
  }
}

export const SESSION_COOKIE = 'shop_admin_session';

export function createSession(userId, userAgent = '') {
  const token = randomBytes(32).toString('base64url');
  const expiresAt = new Date(Date.now() + config.sessionTtlHours * 3600_000).toISOString();
  insert('sessions', {
    token,
    user_id: userId,
    expires_at: expiresAt,
    user_agent: String(userAgent).slice(0, 255),
    created_at: nowIso(),
  });
  update('users', userId, { last_login_at: nowIso() });
  return { token, expiresAt };
}

export function findSessionUser(token) {
  if (!token) return null;
  const row = get(
    `SELECT u.id, u.email, u.name, u.role, u.active, s.expires_at
       FROM sessions s JOIN users u ON u.id = s.user_id
      WHERE s.token = ?`,
    [token],
  );
  if (!row) return null;
  if (new Date(row.expires_at).getTime() < Date.now()) {
    destroySession(token);
    return null;
  }
  if (!row.active) return null;
  return { id: row.id, email: row.email, name: row.name, role: row.role };
}

export function destroySession(token) {
  if (!token) return;
  run('DELETE FROM sessions WHERE token = ?', [token]);
}

export function purgeExpiredSessions() {
  return run('DELETE FROM sessions WHERE expires_at < ?', [nowIso()]).changes;
}

// --- Benutzerverwaltung -----------------------------------------------------

export function listUsers() {
  return all(
    `SELECT id, email, name, role, active, last_login_at, created_at
       FROM users ORDER BY created_at ASC`,
  );
}

export function createUser({ email, password, name = '', role = 'staff' }) {
  const now = nowIso();
  return insert('users', {
    email: String(email).trim().toLowerCase(),
    password_hash: hashPassword(password),
    name,
    role,
    active: 1,
    created_at: now,
    updated_at: now,
  });
}

export function authenticate(email, password) {
  const user = get('SELECT * FROM users WHERE email = ?', [
    String(email || '').trim().toLowerCase(),
  ]);
  // Auch ohne Treffer einen Hash prüfen, damit die Antwortzeit nicht verrät,
  // ob die E-Mail existiert.
  if (!user) {
    verifyPassword(password || '', hashPassword('dummy'));
    return null;
  }
  if (!user.active) return null;
  if (!verifyPassword(password || '', user.password_hash)) return null;
  return { id: user.id, email: user.email, name: user.name, role: user.role };
}

/**
 * CSRF-Token, an die Session gebunden. Der Admin schickt es als Header mit;
 * ein fremdes Formular kennt es nicht und kann deshalb keine Schreibaktion
 * im Namen des eingeloggten Betreibers auslösen.
 */
export function csrfToken(sessionToken) {
  return createHash('sha256')
    .update(`${config.sessionSecret}:${sessionToken}`)
    .digest('base64url');
}

export function checkCsrf(sessionToken, provided) {
  if (!sessionToken || !provided) return false;
  const expected = Buffer.from(csrfToken(sessionToken));
  const actual = Buffer.from(String(provided));
  if (expected.length !== actual.length) return false;
  return timingSafeEqual(expected, actual);
}
