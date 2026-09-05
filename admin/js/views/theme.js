/*
 * Design.
 *
 * Alles hier landet als CSS-Custom-Property im <head> der Storefront. Das ist
 * die Stelle, an der das „generische“ Aussehen zum eigenen wird – und der
 * Grund, warum ein späterer Theme-Umbau die Templates nicht anfassen muss.
 */

import { api } from '../api.js';
import {
  esc, attr, toast, field, textarea, select, checkbox, colorField, formValues,
} from '../ui.js';
import { markChanged } from '../app.js';

const PRESETS = {
  basis: {
    label: 'Basis (hell, neutral)',
    values: {
      color_bg: '#ffffff', color_surface: '#f7f7f8', color_text: '#16181d',
      color_muted: '#6b7280', color_border: '#e5e7eb', color_primary: '#16181d',
      color_primary_text: '#ffffff', color_accent: '#2f6f4f', color_sale: '#c0392b',
      radius: '10px',
    },
  },
  kontrast: {
    label: 'Kontrast (schwarz-weiß, kantig)',
    values: {
      color_bg: '#ffffff', color_surface: '#f2f2f2', color_text: '#000000',
      color_muted: '#666666', color_border: '#000000', color_primary: '#000000',
      color_primary_text: '#ffffff', color_accent: '#000000', color_sale: '#d40000',
      radius: '0px',
    },
  },
  warm: {
    label: 'Warm (Sand und Terrakotta)',
    values: {
      color_bg: '#fdfaf5', color_surface: '#f4ede3', color_text: '#2c231b',
      color_muted: '#7d6c5b', color_border: '#e2d6c6', color_primary: '#8c4a2f',
      color_primary_text: '#ffffff', color_accent: '#6b7f4f', color_sale: '#b4432a',
      radius: '14px',
    },
  },
  dunkel: {
    label: 'Dunkel',
    values: {
      color_bg: '#12141a', color_surface: '#1b1f28', color_text: '#f0f2f5',
      color_muted: '#9aa3b2', color_border: '#2a303c', color_primary: '#f0f2f5',
      color_primary_text: '#12141a', color_accent: '#6bd6a4', color_sale: '#ff7a6b',
      radius: '10px',
    },
  },
};

export async function themeView(root) {
  const data = await api.get('/settings');
  const theme = data.settings.theme;

  root.innerHTML = `
    <div class="page-header">
      <div class="titles"><h1>Design</h1>
        <div class="subtitle">Farben, Schriften und Startseite des Shops</div></div>
      <div class="actions">
        <a class="btn" href="/?preview=1" target="_blank" rel="noopener">Vorschau ↗</a>
        <button class="btn primary" data-save>Speichern</button>
      </div>
    </div>

    <div class="banner info"><div class="banner-body">
      <strong>So funktioniert das Design</strong>
      Diese Werte werden als CSS-Variablen in jede Shopseite geschrieben. Ein späteres eigenes
      Theme überschreibt entweder diese Variablen oder ersetzt <code>public/theme/base.css</code>
      – die Templates bleiben dabei unverändert.
    </div></div>

    <form class="grid-2" data-form>
      <div>
        <section class="card">
          <div class="card-head"><h2>Farbschema</h2>
            <select data-preset>
              <option value="">Vorlage wählen …</option>
              ${Object.entries(PRESETS).map(([key, preset]) =>
                `<option value="${key}" ${theme.preset === key ? 'selected' : ''}>${esc(preset.label)}</option>`).join('')}
            </select>
          </div>
          <div class="card-body">
            <div class="field-row">
              ${colorField({ label: 'Hintergrund', name: 'color_bg', value: theme.color_bg })}
              ${colorField({ label: 'Flächen', name: 'color_surface', value: theme.color_surface })}
            </div>
            <div class="field-row">
              ${colorField({ label: 'Text', name: 'color_text', value: theme.color_text })}
              ${colorField({ label: 'Nebentext', name: 'color_muted', value: theme.color_muted })}
            </div>
            <div class="field-row">
              ${colorField({ label: 'Rahmen', name: 'color_border', value: theme.color_border })}
              ${colorField({ label: 'Akzent', name: 'color_accent', value: theme.color_accent })}
            </div>
            <div class="field-row">
              ${colorField({ label: 'Buttons', name: 'color_primary', value: theme.color_primary })}
              ${colorField({ label: 'Button-Schrift', name: 'color_primary_text', value: theme.color_primary_text })}
            </div>
            ${colorField({ label: 'Sale-Preis', name: 'color_sale', value: theme.color_sale })}
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>Startseite</h2></div>
          <div class="card-body">
            ${field({ label: 'Überschrift', name: 'hero_title', value: theme.hero_title })}
            ${field({ label: 'Unterzeile', name: 'hero_subtitle', value: theme.hero_subtitle })}
            ${field({ label: 'Hintergrundbild (URL)', name: 'hero_image_url', value: theme.hero_image_url,
              hint: 'Leer lassen für eine einfarbige Fläche.' })}
            <div class="field-row">
              ${field({ label: 'Buttontext', name: 'hero_cta_label', value: theme.hero_cta_label })}
              ${field({ label: 'Buttonziel', name: 'hero_cta_url', value: theme.hero_cta_url })}
            </div>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>Ankündigungsleiste</h2></div>
          <div class="card-body">
            ${checkbox({ label: 'Leiste über dem Kopf anzeigen', name: 'announcement_active',
              checked: Boolean(theme.announcement_active) })}
            ${field({ label: 'Text', name: 'announcement', value: theme.announcement,
              placeholder: 'Versandkostenfrei ab 75 €' })}
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>Eigenes CSS</h2></div>
          <div class="card-body">
            ${textarea({ label: 'Zusätzliche Regeln', name: 'custom_css', value: theme.custom_css,
              rows: 8, className: 'code',
              hint: 'Wird nach dem Basis-Stylesheet eingebunden und überschreibt es damit.' })}
          </div>
        </section>
      </div>

      <div>
        <section class="card">
          <div class="card-head"><h2>Typografie &amp; Raster</h2></div>
          <div class="card-body">
            ${select({ label: 'Überschriften', name: 'font_heading', value: theme.font_heading, options: FONT_OPTIONS })}
            ${select({ label: 'Fließtext', name: 'font_body', value: theme.font_body, options: FONT_OPTIONS })}
            ${select({ label: 'Ecken', name: 'radius', value: theme.radius, options: [
              ['0px', 'Kantig'], ['6px', 'Leicht gerundet'], ['10px', 'Gerundet'], ['18px', 'Stark gerundet'],
            ] })}
            ${select({ label: 'Inhaltsbreite', name: 'container_width', value: theme.container_width, options: [
              ['1000px', 'Schmal (1000 px)'], ['1200px', 'Standard (1200 px)'],
              ['1400px', 'Breit (1400 px)'], ['100%', 'Volle Breite'],
            ] })}
            ${select({ label: 'Artikel pro Reihe', name: 'product_grid_columns',
              value: String(theme.product_grid_columns), options: [['2', '2'], ['3', '3'], ['4', '4'], ['5', '5']] })}
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>Anzeigeoptionen</h2></div>
          <div class="card-body">
            ${checkbox({ label: 'Hersteller in der Artikelliste zeigen', name: 'show_vendor',
              checked: Boolean(theme.show_vendor) })}
            ${checkbox({ label: 'Streichpreise und Sale-Kennzeichnung zeigen', name: 'show_compare_at_price',
              checked: theme.show_compare_at_price !== false })}
            ${field({ label: 'Fußzeilentext', name: 'footer_text', value: theme.footer_text })}
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>Vorschau</h2></div>
          <div class="card-body">
            <div data-preview-box style="border:1px solid var(--border);border-radius:8px;overflow:hidden">
              ${previewBox(theme)}
            </div>
            <p class="hint" style="margin-top:8px">Grobe Vorschau. Die vollständige Ansicht öffnet der Knopf oben rechts.</p>
          </div>
        </section>
      </div>
    </form>`;

  const form = root.querySelector('[data-form]');
  form.addEventListener('submit', (event) => event.preventDefault());

  const updatePreview = () => {
    const values = formValues(form);
    root.querySelector('[data-preview-box]').innerHTML = previewBox({ ...theme, ...values });
  };
  form.addEventListener('input', updatePreview);

  root.querySelector('[data-preset]')?.addEventListener('change', (event) => {
    const preset = PRESETS[event.target.value];
    if (!preset) return;
    for (const [key, value] of Object.entries(preset.values)) {
      const input = form.querySelector(`[name=${key}]`);
      if (!input) continue;
      input.value = value;
      // Der Farbwähler links vom Textfeld muss mitziehen.
      const picker = input.parentElement.querySelector('input[type=color]');
      if (picker && /^#[0-9a-f]{6}$/i.test(value)) picker.value = value;
    }
    updatePreview();
  });

  root.querySelector('[data-save]').addEventListener('click', async () => {
    try {
      const values = formValues(form);
      values.preset = root.querySelector('[data-preset]').value || theme.preset;
      values.product_grid_columns = Number(values.product_grid_columns);
      await api.put('/settings/theme', values);
      toast('Design gespeichert');
      await markChanged();
    } catch (error) {
      toast(error.message, 'critical');
    }
  });
}

const FONT_OPTIONS = [
  ["-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif", 'System (serifenlos)'],
  ["'Helvetica Neue', Helvetica, Arial, sans-serif", 'Helvetica'],
  ['Georgia, "Times New Roman", serif', 'Georgia (Serif)'],
  ['"Iowan Old Style", "Palatino Linotype", Palatino, serif', 'Palatino (Serif)'],
  ['ui-monospace, "SF Mono", Menlo, Consolas, monospace', 'Monospace'],
];

function previewBox(theme) {
  const style = (value) => attr(String(value ?? '').replace(/[;{}<>"]/g, ''));
  return `
    <div style="background:${style(theme.color_bg)};color:${style(theme.color_text)};
                font-family:${style(theme.font_body)};padding:0">
      <div style="background:${style(theme.color_primary)};color:${style(theme.color_primary_text)};
                  padding:6px;text-align:center;font-size:11px">
        ${esc(theme.announcement || 'Ankündigung')}
      </div>
      <div style="padding:12px;border-bottom:1px solid ${style(theme.color_border)};
                  font-family:${style(theme.font_heading)};font-weight:700">Shop</div>
      <div style="background:${style(theme.color_surface)};padding:18px 12px">
        <div style="font-family:${style(theme.font_heading)};font-size:16px;font-weight:600;margin-bottom:4px">
          ${esc(theme.hero_title || 'Überschrift')}</div>
        <div style="font-size:12px;color:${style(theme.color_muted)};margin-bottom:10px">
          ${esc(theme.hero_subtitle || 'Unterzeile')}</div>
        <span style="display:inline-block;background:${style(theme.color_primary)};
                     color:${style(theme.color_primary_text)};padding:6px 14px;
                     border-radius:${style(theme.radius)};font-size:12px">
          ${esc(theme.hero_cta_label || 'Button')}</span>
      </div>
      <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
        ${[1, 2].map(() => `
          <div>
            <div style="aspect-ratio:1;background:${style(theme.color_surface)};
                        border-radius:${style(theme.radius)}"></div>
            <div style="font-size:11px;margin-top:4px">Artikelname</div>
            <div style="font-size:11px">
              <span style="color:${style(theme.color_sale)};font-weight:600">39,00 €</span>
              <span style="color:${style(theme.color_muted)};text-decoration:line-through">49,00 €</span>
            </div>
          </div>`).join('')}
      </div>
    </div>`;
}
