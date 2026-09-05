/* CLI: statischen Export erzeugen (npm run export). */
import { exportSite } from '../services/export.js';

const baseUrl = process.argv.find((a) => a.startsWith('--base-url='))?.split('=')[1] || '';
const outDir = process.argv.find((a) => a.startsWith('--out='))?.split('=')[1] || '';

try {
  const result = await exportSite({ baseUrl, outDir });
  console.log(`${result.files} Dateien nach ${result.directory} geschrieben (Version ${result.version}).`);
  console.log('Diesen Ordner kannst du auf jeden Webspace, Netlify, Vercel oder GitHub Pages hochladen.');
} catch (error) {
  console.error(`Export fehlgeschlagen: ${error.message}`);
  process.exitCode = 1;
}
