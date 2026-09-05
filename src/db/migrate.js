/* CLI: legt das Schema an (npm run migrate). */
import { getDb, migrate } from './index.js';
import config from '../config.js';

migrate(getDb());
console.log(`Schema angelegt/aktualisiert: ${config.databaseFile}`);
