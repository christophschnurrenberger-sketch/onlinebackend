/*
 * CLI: Datenbank zurücksetzen und neu befüllen (npm run reset).
 * Löscht alle Daten – deshalb die ausdrückliche Bestätigung per --yes.
 */
import { rmSync, existsSync } from 'node:fs';
import config from '../config.js';

if (!process.argv.includes('--yes')) {
  console.error('Das löscht ALLE Daten. Zum Bestätigen: npm run reset -- --yes');
  process.exit(1);
}

for (const suffix of ['', '-wal', '-shm', '-journal']) {
  const file = `${config.databaseFile}${suffix}`;
  if (existsSync(file)) rmSync(file);
}
console.log('Datenbank gelöscht. Jetzt: npm run seed');
