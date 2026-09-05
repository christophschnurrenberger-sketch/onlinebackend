/*
 * Storefront-JavaScript.
 *
 * Reine Verbesserung: Variantenauswahl, Mengenknöpfe und "In den Warenkorb"
 * ohne Seitenwechsel. Jede dieser Aktionen existiert auch als normales
 * Formular, deshalb bleibt der Shop ohne JavaScript vollständig benutzbar.
 */

(function () {
  'use strict';

  const euro = (cents, currency) =>
    new Intl.NumberFormat('de-DE', { style: 'currency', currency: currency || 'EUR' }).format(
      (cents || 0) / 100,
    );

  // --- Mobile Navigation ---------------------------------------------------

  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('main-nav');
  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', String(open));
    });
  }

  // --- Mengenknöpfe --------------------------------------------------------

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-qty]');
    if (!button) return;
    const input = button.parentElement.querySelector('input');
    if (!input) return;
    const next = Math.max(1, (Number(input.value) || 1) + Number(button.dataset.qty));
    input.value = String(next);
  });

  // --- Produktseite: Varianten ---------------------------------------------

  const productEl = document.querySelector('[data-product]');
  if (productEl) {
    let variants = [];
    try {
      variants = JSON.parse(productEl.dataset.variants || '[]');
    } catch (error) {
      variants = [];
    }

    const selection = [];
    productEl.querySelectorAll('[data-option-value][aria-pressed="true"]').forEach((button) => {
      selection[Number(button.dataset.optionIndex) - 1] = button.dataset.optionValue;
    });

    const findVariant = () =>
      variants.find((variant) =>
        variant.options.every((value, index) => selection[index] === undefined || selection[index] === value),
      );

    const update = () => {
      const variant = findVariant();
      const input = productEl.querySelector('[data-variant-input]');
      const priceEl = productEl.querySelector('[data-price]');
      const noteEl = productEl.querySelector('[data-stock-note]');
      const addButton = productEl.querySelector('[data-add-button]');
      const skuEl = productEl.querySelector('[data-sku]');
      const mainImage = productEl.querySelector('[data-main-image]');

      if (!variant) {
        if (addButton) {
          addButton.disabled = true;
          addButton.textContent = 'Nicht verfügbar';
        }
        return;
      }

      if (input) input.value = variant.id;

      if (priceEl) {
        const onSale = variant.compare_at_price && variant.compare_at_price > variant.price;
        const currency = document.documentElement.dataset.currency || 'EUR';
        priceEl.innerHTML =
          '<span class="current' + (onSale ? ' on-sale' : '') + '">' + euro(variant.price, currency) + '</span>' +
          (onSale ? '<span class="compare">' + euro(variant.compare_at_price, currency) + '</span>' : '');
      }

      const available = variant.available;
      if (noteEl) {
        noteEl.className = 'stock-note ' + (available === 0 ? 'out' : available !== null && available <= 5 ? 'low' : 'in');
        noteEl.textContent =
          available === null ? 'Sofort lieferbar'
          : available <= 0 ? 'Ausverkauft'
          : available <= 5 ? 'Nur noch ' + available + ' auf Lager'
          : 'Auf Lager – sofort lieferbar';
      }
      if (addButton) {
        addButton.disabled = available === 0;
        addButton.textContent = available === 0 ? 'Ausverkauft' : 'In den Warenkorb';
      }
      if (skuEl && variant.sku) skuEl.textContent = 'Art.-Nr.: ' + variant.sku;
      if (mainImage && variant.image) mainImage.src = variant.image;
    };

    productEl.querySelectorAll('[data-option-value]').forEach((button) => {
      button.addEventListener('click', () => {
        const index = Number(button.dataset.optionIndex) - 1;
        selection[index] = button.dataset.optionValue;
        productEl
          .querySelectorAll('[data-option-index="' + button.dataset.optionIndex + '"]')
          .forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        update();
      });
    });

    productEl.querySelectorAll('[data-thumb]').forEach((thumb) => {
      thumb.addEventListener('click', () => {
        const main = productEl.querySelector('[data-main-image]');
        if (main) main.src = thumb.dataset.thumb;
        productEl.querySelectorAll('[data-thumb]').forEach((other) =>
          other.setAttribute('aria-current', String(other === thumb)),
        );
      });
    });

    // --- In den Warenkorb ohne Seitenwechsel -------------------------------
    const addForm = productEl.querySelector('[data-add-form]');
    if (addForm) {
      addForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const feedback = productEl.querySelector('[data-add-feedback]');
        const button = productEl.querySelector('[data-add-button]');
        const original = button ? button.textContent : '';
        if (button) {
          button.disabled = true;
          button.textContent = 'Wird hinzugefügt…';
        }

        try {
          const body = new FormData(addForm);
          const response = await fetch('/api/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              variant_id: Number(body.get('variant_id')),
              quantity: Number(body.get('quantity') || 1),
            }),
          });
          const json = await response.json();

          if (!response.ok) throw new Error(json.error || 'Fehler beim Hinzufügen');

          document.querySelectorAll('[data-cart-count]').forEach((el) => {
            el.textContent = String(json.item_count);
          });
          if (feedback) {
            feedback.innerHTML =
              '<div class="notice success" style="margin-top:12px">Zum Warenkorb hinzugefügt. ' +
              '<a href="/cart">Warenkorb ansehen</a></div>';
          }
        } catch (error) {
          if (feedback) {
            feedback.innerHTML =
              '<div class="notice error" style="margin-top:12px">' + error.message + '</div>';
          }
        } finally {
          if (button) {
            button.disabled = false;
            button.textContent = original;
          }
        }
      });
    }

    update();
  }

  // --- Kasse: Land wechseln lädt die Versandarten neu -----------------------

  const countrySelect = document.querySelector('[data-country]');
  if (countrySelect) {
    countrySelect.addEventListener('change', async () => {
      await fetch('/api/cart', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ country: countrySelect.value }),
      });
      window.location.reload();
    });
  }

  document.querySelectorAll('[data-shipping-rate]').forEach((radio) => {
    radio.addEventListener('change', async () => {
      await fetch('/api/cart', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ shipping_rate_id: Number(radio.value) }),
      });
      window.location.reload();
    });
  });
})();
