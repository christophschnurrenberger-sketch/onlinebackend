/*
 * HTML-Erzeugung ohne Template-Engine.
 *
 * `html` ist ein Tagged Template, das interpolierte Werte escaped. Bewusst
 * gefährlicher Inhalt (z. B. der Rich-Text aus dem Editor) wird mit `raw()`
 * markiert – so ist im Code jederzeit sichtbar, wo ungeprüftes HTML landet.
 */

const ESCAPES = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
};

export function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value).replace(/[&<>"']/g, (c) => ESCAPES[c]);
}

class RawHtml {
  constructor(value) {
    this.value = String(value ?? '');
  }
  toString() {
    return this.value;
  }
}

export const raw = (value) => new RawHtml(value);
export const isRaw = (value) => value instanceof RawHtml;

function render(value) {
  if (value === null || value === undefined || value === false) return '';
  if (isRaw(value)) return value.value;
  if (Array.isArray(value)) return value.map(render).join('');
  return escapeHtml(value);
}

export function html(strings, ...values) {
  let out = strings[0];
  for (let i = 0; i < values.length; i += 1) {
    out += render(values[i]) + strings[i + 1];
  }
  return raw(out);
}

/**
 * Erlaubt nur eine kleine, für Produkttexte ausreichende Menge an Tags.
 * Ein Editor im Admin ist kein Grund, dem Browser des Kunden beliebiges
 * Markup auszuliefern – ein Staff-Account soll keinen Store-XSS schreiben
 * können.
 */
const ALLOWED_TAGS = new Set([
  'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h1', 'h2',
  'h3', 'h4', 'h5', 'h6', 'blockquote', 'hr', 'img', 'table', 'thead', 'tbody',
  'tr', 'th', 'td', 'span', 'div', 'figure', 'figcaption', 'small', 'code', 'pre',
]);
const ALLOWED_ATTRS = new Set(['href', 'title', 'alt', 'src', 'width', 'height', 'target', 'rel']);

export function sanitizeHtml(input) {
  if (!input) return '';
  let text = String(input);

  // Ganze Elemente, deren Inhalt niemals gerendert werden darf.
  text = text.replace(/<(script|style|iframe|object|embed|form)\b[\s\S]*?<\/\1>/gi, '');
  text = text.replace(/<\/?(script|style|iframe|object|embed|form)\b[^>]*>/gi, '');
  text = text.replace(/<!--[\s\S]*?-->/g, '');

  return text.replace(/<\/?([a-zA-Z0-9-]+)((?:\s[^>]*)?)\/?>/g, (match, tag, attrs) => {
    const name = tag.toLowerCase();
    if (!ALLOWED_TAGS.has(name)) return '';
    if (match.startsWith('</')) return `</${name}>`;

    const kept = [];
    const attrPattern = /([a-zA-Z-]+)\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+))/g;
    let m;
    while ((m = attrPattern.exec(attrs)) !== null) {
      const attr = m[1].toLowerCase();
      if (!ALLOWED_ATTRS.has(attr)) continue;
      const value = m[3] ?? m[4] ?? m[5] ?? '';
      // javascript:/data: in href oder src ist der klassische XSS-Vektor.
      if ((attr === 'href' || attr === 'src') && /^\s*(javascript|data|vbscript):/i.test(value)) {
        continue;
      }
      kept.push(`${attr}="${escapeHtml(value)}"`);
    }
    const selfClosing = ['br', 'hr', 'img'].includes(name);
    return `<${name}${kept.length ? ' ' + kept.join(' ') : ''}${selfClosing ? ' /' : ''}>`;
  });
}

/** Reiner Text aus HTML – für SEO-Beschreibungen und Suchindex. */
export function stripTags(input, maxLength = 0) {
  const text = String(input || '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/\s+/g, ' ')
    .trim();
  if (maxLength > 0 && text.length > maxLength) {
    return `${text.slice(0, maxLength - 1).trimEnd()}…`;
  }
  return text;
}
