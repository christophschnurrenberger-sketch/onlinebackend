/*
 * Bild-Upload für das Backend.
 *
 * Ein eigener multipart-Parser statt einer Bibliothek: wir brauchen genau
 * einen Fall (ein Feld, eine Datei) und wollen weiterhin ohne Dependencies
 * auskommen. Der Parser ist deshalb bewusst streng – was er nicht erkennt,
 * wird abgelehnt statt geraten.
 */

import { writeFile, mkdir } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import { extname, join } from 'node:path';
import { Router, readBody, sendJson, badRequest, forbidden, unauthorized } from '../lib/http.js';
import { createMedia } from '../models/content.js';
import { checkCsrf } from '../lib/auth.js';
import config from '../config.js';

const MAX_SIZE = 8 * 1024 * 1024;
const ALLOWED = {
  'image/jpeg': '.jpg',
  'image/png': '.png',
  'image/webp': '.webp',
  'image/avif': '.avif',
  'image/gif': '.gif',
  'image/svg+xml': '.svg',
};

export function uploadRouter() {
  const router = new Router();

  router.post('', async (ctx) => {
    if (!ctx.user) throw unauthorized();
    if (!checkCsrf(ctx.sessionToken, ctx.req.headers['x-csrf-token'])) {
      throw forbidden('CSRF-Token fehlt oder ist ungültig');
    }

    const contentType = ctx.req.headers['content-type'] || '';
    const boundaryMatch = /boundary=(?:"([^"]+)"|([^;]+))/i.exec(contentType);
    if (!contentType.startsWith('multipart/form-data') || !boundaryMatch) {
      throw badRequest('Erwartet wird multipart/form-data');
    }

    const body = await readBody(ctx.req, MAX_SIZE);
    const file = parseSinglePart(body, boundaryMatch[1] || boundaryMatch[2]);
    if (!file) throw badRequest('Keine Datei gefunden');

    const extension = ALLOWED[file.mime];
    if (!extension) {
      throw badRequest(`Dateityp ${file.mime || 'unbekannt'} ist nicht erlaubt (JPEG, PNG, WebP, AVIF, GIF, SVG)`);
    }

    // Eigener Dateiname: der vom Client gelieferte könnte Pfadanteile enthalten
    // oder eine bestehende Datei überschreiben.
    const filename = `${Date.now()}-${randomBytes(6).toString('hex')}${extension}`;
    await mkdir(config.uploadDir, { recursive: true });
    await writeFile(join(config.uploadDir, filename), file.data);

    const url = `/uploads/${filename}`;
    const id = createMedia({
      filename: file.filename || filename,
      url,
      mime: file.mime,
      size: file.data.length,
      alt: '',
    });

    sendJson(ctx.res, 201, { id, url, filename, size: file.data.length, mime: file.mime });
  });

  return router;
}

/** Findet den ersten Part mit filename= und gibt Name, Typ und Rohdaten zurück. */
function parseSinglePart(buffer, boundary) {
  const delimiter = Buffer.from(`--${boundary}`);
  const parts = [];
  let index = buffer.indexOf(delimiter);

  while (index !== -1) {
    const start = index + delimiter.length;
    const next = buffer.indexOf(delimiter, start);
    if (next === -1) break;
    parts.push(buffer.subarray(start, next));
    index = next;
  }

  for (const part of parts) {
    const headerEnd = part.indexOf('\r\n\r\n');
    if (headerEnd === -1) continue;

    const headers = part.subarray(0, headerEnd).toString('utf8');
    const filename = /filename="([^"]*)"/i.exec(headers)?.[1];
    if (!filename) continue;

    const mime = /Content-Type:\s*([^\r\n;]+)/i.exec(headers)?.[1]?.trim().toLowerCase() || '';
    // Der Part endet mit \r\n vor dem nächsten Delimiter.
    const data = part.subarray(headerEnd + 4, part.length - 2);
    return { filename: filename.split(/[\\/]/).pop(), mime, data };
  }
  return null;
}
