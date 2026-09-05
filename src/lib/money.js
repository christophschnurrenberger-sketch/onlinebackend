/*
 * Geld.
 *
 * Alles rechnet in Cent (Integer). Die einzigen Stellen, an denen gerundet
 * wird, sind hier – damit Summen im Warenkorb, in der Bestellung und auf der
 * Rechnung garantiert übereinstimmen.
 */

/** Kaufmännische Rundung auf ganze Cent. */
export const roundCents = (value) => Math.round(value);

/** Prozentwert in Basispunkten anwenden: 1900 bp = 19 %. */
export function applyBp(amountCents, bp) {
  return roundCents((amountCents * bp) / 10000);
}

/**
 * Der im Preis enthaltene Steueranteil (Bruttopreise, wie im EU-B2C-Handel
 * üblich): brutto - brutto / (1 + satz).
 */
export function taxFromGross(grossCents, bp) {
  if (!bp) return 0;
  return roundCents(grossCents - grossCents / (1 + bp / 10000));
}

/**
 * Verteilt einen Gesamtbetrag proportional auf Gewichte, ohne dass durch
 * Rundung Cent verloren gehen: die Summe der Anteile ist exakt `total`.
 * Der Rest wandert an die Position mit dem größten Rundungsverlust.
 */
export function distribute(total, weights) {
  const sum = weights.reduce((a, b) => a + b, 0);
  if (sum <= 0 || total === 0) return weights.map(() => 0);

  const exact = weights.map((w) => (total * w) / sum);
  const floored = exact.map((v) => Math.floor(v));
  let remainder = total - floored.reduce((a, b) => a + b, 0);

  const order = exact
    .map((v, i) => ({ i, frac: v - Math.floor(v) }))
    .sort((a, b) => b.frac - a.frac);

  const out = [...floored];
  let k = 0;
  while (remainder > 0 && order.length > 0) {
    out[order[k % order.length].i] += 1;
    remainder -= 1;
    k += 1;
  }
  return out;
}

const formatters = new Map();

/** Formatiert Cent als Währungstext, z. B. 1990 -> "19,90 €". */
export function formatMoney(cents, currency = 'EUR', locale = 'de-DE') {
  const key = `${locale}:${currency}`;
  if (!formatters.has(key)) {
    formatters.set(
      key,
      new Intl.NumberFormat(locale, { style: 'currency', currency }),
    );
  }
  return formatters.get(key).format((cents || 0) / 100);
}

/** Liest Nutzereingaben wie "19,90", "19.90", "1.990,00" als Cent. */
export function parseMoney(input) {
  if (input === null || input === undefined || input === '') return 0;
  if (typeof input === 'number') return roundCents(input * 100);

  let text = String(input).trim().replace(/[^\d,.-]/g, '');
  const lastComma = text.lastIndexOf(',');
  const lastDot = text.lastIndexOf('.');

  if (lastComma > lastDot) {
    // Deutsches Format: Punkt ist Tausendertrenner, Komma das Dezimalzeichen.
    text = text.replace(/\./g, '').replace(',', '.');
  } else {
    text = text.replace(/,/g, '');
  }

  const value = Number.parseFloat(text);
  return Number.isFinite(value) ? roundCents(value * 100) : 0;
}
