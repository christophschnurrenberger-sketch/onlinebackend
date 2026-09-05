/* URL-Handles ("mein-produkt") aus beliebigen Titeln. */

const UMLAUTS = { ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss', æ: 'ae', ø: 'oe', å: 'aa' };

export function slugify(input, fallback = 'eintrag') {
  const text = String(input || '')
    .toLowerCase()
    .replace(/[äöüßæøå]/g, (c) => UMLAUTS[c] || c)
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80);
  return text || fallback;
}

/**
 * Macht einen Handle eindeutig, indem -2, -3 … angehängt wird.
 * `exists` beantwortet "ist dieser Handle schon vergeben?".
 */
export function uniqueSlug(base, exists, fallback = 'eintrag') {
  const slug = slugify(base, fallback);
  if (!exists(slug)) return slug;
  for (let n = 2; n < 1000; n += 1) {
    const candidate = `${slug}-${n}`;
    if (!exists(candidate)) return candidate;
  }
  return `${slug}-${Date.now()}`;
}
