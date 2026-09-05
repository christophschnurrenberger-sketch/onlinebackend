/*
 * Bestandsführung.
 *
 * Jede Änderung geht durch `adjust` und hinterlässt eine Bewegung. Der Bestand
 * ist damit jederzeit erklärbar ("warum stehen hier 3?") – ohne Journal ist
 * Lagerhaltung nicht auditierbar.
 */

import { all, get, run, insert, transaction, nowIso } from '../db/index.js';
import { conflict } from '../lib/http.js';

/**
 * Verfügbare Menge einer Variante.
 * `null` heißt "unbegrenzt" – entweder ohne Bestandsführung oder mit
 * ausdrücklich erlaubtem Überverkauf.
 */
export function availableStock(variant) {
  if (!variant) return 0;
  if (!variant.track_inventory) return null;
  if (variant.inventory_policy === 'continue') return null;
  return variant.inventory_quantity;
}

export function adjust(variantId, delta, { reason = 'correction', orderId = null, userId = null } = {}) {
  return transaction(() => {
    const variant = get('SELECT * FROM variants WHERE id = ?', [variantId]);
    if (!variant) return null;

    if (variant.track_inventory) {
      run('UPDATE variants SET inventory_quantity = inventory_quantity + ?, updated_at = ? WHERE id = ?', [
        delta,
        nowIso(),
        variantId,
      ]);
    }
    insert('inventory_moves', {
      variant_id: variantId,
      delta,
      reason,
      order_id: orderId,
      user_id: userId,
      created_at: nowIso(),
    });
    return get('SELECT inventory_quantity FROM variants WHERE id = ?', [variantId])?.inventory_quantity ?? 0;
  });
}

export function setQuantity(variantId, quantity, { userId = null, reason = 'correction' } = {}) {
  const variant = get('SELECT inventory_quantity FROM variants WHERE id = ?', [variantId]);
  if (!variant) return null;
  return adjust(variantId, quantity - variant.inventory_quantity, { reason, userId });
}

/**
 * Bucht die Positionen einer Bestellung aus. Vorher wird der gesamte Korb
 * geprüft: entweder alles ist lieferbar oder gar nichts wird gebucht – ein
 * halb ausgebuchter Warenkorb wäre nicht reparierbar.
 */
export function reserveForOrder(lines, { orderId = null, userId = null } = {}) {
  return transaction(() => {
    const problems = [];
    for (const line of lines) {
      const variant = get('SELECT * FROM variants WHERE id = ?', [line.variant_id]);
      if (!variant) {
        problems.push({ variant_id: line.variant_id, reason: 'Artikel existiert nicht mehr' });
        continue;
      }
      const stock = availableStock(variant);
      if (stock !== null && line.quantity > stock) {
        problems.push({
          variant_id: line.variant_id,
          title: line.title,
          available: stock,
          reason: stock <= 0 ? 'ausverkauft' : `nur noch ${stock} verfügbar`,
        });
      }
    }
    if (problems.length > 0) {
      throw conflict('Nicht alle Artikel sind in der gewünschten Menge verfügbar', { problems });
    }

    for (const line of lines) {
      adjust(line.variant_id, -line.quantity, { reason: 'sale', orderId, userId });
    }
    return true;
  });
}

/** Gegenbuchung bei Storno oder Retoure. */
export function releaseForOrder(lines, { orderId = null, userId = null, reason = 'refund' } = {}) {
  return transaction(() => {
    for (const line of lines) {
      if (!line.variant_id) continue;
      adjust(line.variant_id, line.quantity, { reason, orderId, userId });
    }
    return true;
  });
}

export const movesForVariant = (variantId, limit = 100) =>
  all(
    `SELECT m.*, o.number AS order_number, u.name AS user_name
       FROM inventory_moves m
       LEFT JOIN orders o ON o.id = m.order_id
       LEFT JOIN users u ON u.id = m.user_id
      WHERE m.variant_id = ? ORDER BY m.created_at DESC LIMIT ?`,
    [variantId, limit],
  );

/** Varianten unter der Meldeschwelle – die Startseite des Backends zeigt sie an. */
export const lowStock = (threshold = 5, limit = 20) =>
  all(
    `SELECT v.id, v.title AS variant_title, v.sku, v.inventory_quantity,
            p.id AS product_id, p.title AS product_title, p.handle
       FROM variants v JOIN products p ON p.id = v.product_id
      WHERE v.track_inventory = 1 AND v.inventory_quantity <= ?
        AND p.status != 'archived'
      ORDER BY v.inventory_quantity ASC, p.title
      LIMIT ?`,
    [threshold, limit],
  );
