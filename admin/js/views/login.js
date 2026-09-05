/* Anmeldemaske. */

import { api } from '../api.js';
import { esc, field } from '../ui.js';

export function loginView(root, onSuccess) {
  root.innerHTML = `
    <div class="login-screen">
      <form class="login-card">
        <h1>Shop-Backend</h1>
        <p class="sub">Melde dich an, um Artikel, Bestellungen und Inhalte zu pflegen.</p>
        <div data-error></div>
        ${field({ label: 'E-Mail', name: 'email', type: 'email', required: true })}
        ${field({ label: 'Passwort', name: 'password', type: 'password', required: true })}
        <button class="btn primary" type="submit" style="width:100%;margin-top:8px">Anmelden</button>
      </form>
    </div>`;

  const form = root.querySelector('form');
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = form.querySelector('[type=submit]');
    const errorBox = form.querySelector('[data-error]');
    submit.disabled = true;
    submit.textContent = 'Anmelden …';
    errorBox.innerHTML = '';

    try {
      const data = Object.fromEntries(new FormData(form));
      const result = await api.post('/login', data);
      onSuccess(result.user, result.csrf_token);
    } catch (error) {
      errorBox.innerHTML = `<div class="banner critical"><div class="banner-body">${esc(error.message)}</div></div>`;
      submit.disabled = false;
      submit.textContent = 'Anmelden';
      form.querySelector('[name=password]').value = '';
    }
  });

  form.querySelector('[name=email]').focus();
}
