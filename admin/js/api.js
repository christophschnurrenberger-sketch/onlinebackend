/*
 * API-Client des Backends.
 *
 * Hängt das CSRF-Token an jede schreibende Anfrage und übersetzt Fehler-
 * antworten in geworfene Fehler mit lesbarer Meldung – die Views müssen sich
 * deshalb nie mit Statuscodes befassen.
 */

let csrfToken = '';

export const setCsrfToken = (token) => {
  csrfToken = token || '';
};

export class ApiError extends Error {
  constructor(status, message, details) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

async function request(method, path, body) {
  const headers = {};
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (method !== 'GET') headers['X-CSRF-Token'] = csrfToken;

  const response = await fetch(`/api/admin${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (response.status === 204) return null;

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    throw new ApiError(
      response.status,
      payload?.error || `Fehler ${response.status}`,
      payload?.details,
    );
  }
  return payload;
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body ?? {}),
  put: (path, body) => request('PUT', path, body ?? {}),
  patch: (path, body) => request('PATCH', path, body ?? {}),
  delete: (path) => request('DELETE', path),

  /** Datei-Upload läuft als multipart und deshalb an `request` vorbei. */
  async upload(file) {
    const form = new FormData();
    form.append('file', file);
    const response = await fetch('/admin/upload', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken },
      body: form,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, payload?.error || 'Upload fehlgeschlagen');
    return payload;
  },
};

/** Query-String aus einem Objekt, leere Werte fallen weg. */
export function query(params) {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue;
    search.set(key, String(value));
  }
  const text = search.toString();
  return text ? `?${text}` : '';
}
