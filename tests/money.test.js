/* Geldarithmetik – die Stelle, an der Rundungsfehler am teuersten sind. */

import test from 'node:test';
import assert from 'node:assert/strict';
import { distribute, parseMoney, formatMoney, taxFromGross, applyBp } from '../src/lib/money.js';

test('distribute verliert keinen Cent', () => {
  for (const [total, weights] of [
    [100, [1, 1, 1]],
    [999, [500, 300, 199]],
    [1, [1, 1, 1, 1]],
    [4497, [29980, 14990]],
  ]) {
    const shares = distribute(total, weights);
    assert.equal(shares.reduce((a, b) => a + b, 0), total, `Summe stimmt für ${total}`);
    assert.ok(shares.every((s) => s >= 0), 'keine negativen Anteile');
  }
});

test('distribute verteilt proportional', () => {
  assert.deepEqual(distribute(300, [100, 200]), [100, 200]);
  assert.deepEqual(distribute(100, [1, 1, 1]), [34, 33, 33]);
});

test('distribute bei Nullgewichten', () => {
  assert.deepEqual(distribute(100, [0, 0]), [0, 0]);
  assert.deepEqual(distribute(0, [1, 2]), [0, 0]);
});

test('parseMoney liest deutsche und englische Schreibweise', () => {
  assert.equal(parseMoney('19,90'), 1990);
  assert.equal(parseMoney('19.90'), 1990);
  assert.equal(parseMoney('1.990,50'), 199050);
  assert.equal(parseMoney('1,990.50'), 199050);
  assert.equal(parseMoney('19,90 €'), 1990);
  assert.equal(parseMoney(''), 0);
  assert.equal(parseMoney('unsinn'), 0);
  assert.equal(parseMoney(19.9), 1990);
});

test('taxFromGross rechnet den enthaltenen Anteil', () => {
  // 119,00 € brutto bei 19 % enthalten 19,00 € Steuer.
  assert.equal(taxFromGross(11900, 1900), 1900);
  assert.equal(taxFromGross(10000, 0), 0);
  assert.equal(taxFromGross(10700, 700), 700);
});

test('applyBp rechnet Prozentsätze', () => {
  assert.equal(applyBp(10000, 1000), 1000);
  assert.equal(applyBp(4990, 1250), 624);
});

test('formatMoney liefert deutsche Währungsschreibweise', () => {
  assert.match(formatMoney(1990), /19,90/);
  assert.match(formatMoney(0), /0,00/);
});
