/*
 * Testhilfen.
 *
 * Jeder Test bekommt eine eigene In-Memory-Datenbank. Die Tests laufen dadurch
 * unabhängig voneinander und fassen die Entwicklungsdatenbank nie an.
 */

import { openMemoryDb, setDb } from '../src/db/index.js';
import * as settings from '../src/models/settings.js';
import * as shipping from '../src/models/shipping.js';
import * as products from '../src/models/products.js';

/** Frische Datenbank mit Steuersatz und Versandzone – der Minimalshop. */
export function freshShop({ freeOver = null } = {}) {
  setDb(openMemoryDb());

  shipping.createTaxRate({ name: 'Standard', rate: '19', is_default: true });
  shipping.createZone({
    name: 'Deutschland',
    countries: ['DE'],
    rates: [{ name: 'Standard', price: '4,90', free_over: freeOver }],
  });
  settings.setGroup('store', { name: 'Testshop', currency: 'EUR' });
  return { settings, shipping };
}

/** Legt einen aktiven Artikel mit einer Variante an. */
export function makeProduct({ title = 'Testartikel', price = '10,00', stock = 10, tags = [] } = {}) {
  return products.createProduct({
    title,
    status: 'active',
    tags,
    variants: [{ title: 'Standard', price, inventory_quantity: stock, sku: 'TEST-1' }],
  });
}

export const variantOf = (product) => products.getVariantWithProduct(product.variants[0].id);
