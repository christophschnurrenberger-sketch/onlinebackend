/*
 * Minimaler HTTP-Layer über node:http.
 *
 * Ein Router mit Pfadparametern, JSON-/Form-Parsing, Cookies und statischer
 * Auslieferung – mehr braucht dieser Shop nicht, und so bleibt das Projekt
 * ohne Framework-Abhängigkeit installierbar.
 */

import { createReadStream, statSync, existsSync } from 'node:fs';
import { extname, join, normalize, sep } from 'node:path';

export class HttpError extends Error {
  constructor(status, message, details = null) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

export const badRequest = (msg, details) => new HttpError(400, msg, details);
export const unauthorized = (msg = 'Nicht angemeldet') => new HttpError(401, msg);
export const forbidden = (msg = 'Keine Berechtigung') => new HttpError(403, msg);
export const notFound = (msg = 'Nicht gefunden') => new HttpError(404, msg);
export const conflict = (msg, details) => new HttpError(409, msg, details);

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.avif': 'image/avif',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.txt': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.pdf': 'application/pdf',
};

export const mimeFor = (path) => MIME[extname(path).toLowerCase()] || 'application/octet-stream';

// --- Router -----------------------------------------------------------------

export class Router {
  constructor() {
    this.routes = [];
  }

  add(method, pattern, handler) {
    this.routes.push({ method, ...compile(pattern), handler });
    return this;
  }

  get(pattern, handler) { return this.add('GET', pattern, handler); }
  post(pattern, handler) { return this.add('POST', pattern, handler); }
  put(pattern, handler) { return this.add('PUT', pattern, handler); }
  patch(pattern, handler) { return this.add('PATCH', pattern, handler); }
  delete(pattern, handler) { return this.add('DELETE', pattern, handler); }

  /** Hängt die Routen eines anderen Routers unter einem Präfix ein. */
  mount(prefix, router) {
    for (const route of router.routes) {
      this.routes.push({
        ...route,
        ...compile(prefix + route.pattern),
      });
    }
    return this;
  }

  match(method, pathname) {
    // HEAD wird wie GET behandelt; der Body wird später verworfen.
    const wanted = method === 'HEAD' ? 'GET' : method;
    for (const route of this.routes) {
      if (route.method !== wanted && route.method !== 'ALL') continue;
      const m = route.regex.exec(pathname);
      if (!m) continue;
      const params = {};
      route.keys.forEach((key, i) => {
        params[key] = decodeURIComponent(m[i + 1] ?? '');
      });
      return { handler: route.handler, params };
    }
    return null;
  }
}

/** "/products/:id" -> Regex + Parameternamen. "*" am Ende matcht den Rest. */
function compile(pattern) {
  const keys = [];
  let source = pattern
    .replace(/[.+^${}()|[\]\\]/g, '\\$&')
    .replace(/\/:([A-Za-z0-9_]+)/g, (_, key) => {
      keys.push(key);
      return '/([^/]+)';
    });
  if (source.endsWith('*')) {
    keys.push('wildcard');
    source = `${source.slice(0, -1)}(.*)`;
  }
  return { pattern, regex: new RegExp(`^${source}/?$`), keys };
}

// --- Request-Helfer ---------------------------------------------------------

const MAX_BODY = 5 * 1024 * 1024; // 5 MB reicht für JSON und kleine Uploads.

export function readBody(req, limit = MAX_BODY) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > limit) {
        reject(new HttpError(413, 'Anfrage zu groß'));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

export async function readJson(req) {
  const buffer = await readBody(req);
  if (buffer.length === 0) return {};
  try {
    return JSON.parse(buffer.toString('utf8'));
  } catch {
    throw badRequest('Ungültiges JSON');
  }
}

export async function readForm(req) {
  const buffer = await readBody(req);
  const params = new URLSearchParams(buffer.toString('utf8'));
  const out = {};
  for (const [key, value] of params) out[key] = value;
  return out;
}

export function parseCookies(req) {
  const header = req.headers.cookie;
  if (!header) return {};
  const out = {};
  for (const part of header.split(';')) {
    const eq = part.indexOf('=');
    if (eq === -1) continue;
    const key = part.slice(0, eq).trim();
    const value = part.slice(eq + 1).trim();
    try {
      out[key] = decodeURIComponent(value);
    } catch {
      out[key] = value;
    }
  }
  return out;
}

export function serializeCookie(name, value, options = {}) {
  const parts = [`${name}=${encodeURIComponent(value)}`];
  if (options.maxAge !== undefined) parts.push(`Max-Age=${Math.floor(options.maxAge)}`);
  if (options.expires) parts.push(`Expires=${new Date(options.expires).toUTCString()}`);
  parts.push(`Path=${options.path || '/'}`);
  if (options.domain) parts.push(`Domain=${options.domain}`);
  if (options.httpOnly !== false) parts.push('HttpOnly');
  if (options.secure) parts.push('Secure');
  parts.push(`SameSite=${options.sameSite || 'Lax'}`);
  return parts.join('; ');
}

// --- Response-Helfer --------------------------------------------------------

export function setCookie(res, cookie) {
  const existing = res.getHeader('Set-Cookie');
  const list = existing ? (Array.isArray(existing) ? existing : [existing]) : [];
  list.push(cookie);
  res.setHeader('Set-Cookie', list);
}

export function sendJson(res, status, data) {
  const body = JSON.stringify(data);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
    'Cache-Control': 'no-store',
  });
  res.end(body);
}

export function sendHtml(res, status, markup, headers = {}) {
  const body = String(markup);
  res.writeHead(status, {
    'Content-Type': 'text/html; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
    ...headers,
  });
  res.end(body);
}

export function sendText(res, status, text, contentType = 'text/plain; charset=utf-8') {
  const body = String(text);
  res.writeHead(status, {
    'Content-Type': contentType,
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

export function redirect(res, location, status = 302) {
  res.writeHead(status, { Location: location });
  res.end();
}

/**
 * Liefert eine Datei aus `rootDir`. Der normalisierte Pfad muss innerhalb von
 * rootDir bleiben – sonst wäre "../../.env" abrufbar.
 */
export function sendFile(res, rootDir, relativePath, { immutable = false } = {}) {
  const clean = normalize(decodeURIComponent(relativePath)).replace(/^(\.\.[/\\])+/, '');
  const full = join(rootDir, clean);
  if (!full.startsWith(rootDir + sep) && full !== rootDir) return false;
  if (!existsSync(full)) return false;

  const stat = statSync(full);
  if (!stat.isFile()) return false;

  res.writeHead(200, {
    'Content-Type': mimeFor(full),
    'Content-Length': stat.size,
    'Cache-Control': immutable
      ? 'public, max-age=31536000, immutable'
      : 'public, max-age=300',
    'Last-Modified': stat.mtime.toUTCString(),
  });
  createReadStream(full).pipe(res);
  return true;
}
