import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import {
  getAuthToken,
  saveAuthToken,
  clearAuthToken,
  takeDestination,
  rememberDestination,
  createGoogleLoginUrl,
  takeLoginVerifier,
  redirectToLogin,
} from '../../resources/js/helpers/authStorage.js';

function prepareBrowser() {
  globalThis.localStorage = new Storage();
  globalThis.sessionStorage = new Storage();
  globalThis.window = {
    location: {
      origin: 'https://app.nuvads.test:8443',
      pathname: '/lalala',
      search: '?tab=details',
      hash: '#section',
      replace(path) { this.replacedWith = path; },
    },
  };
}

class Storage {
  values = new Map();
  getItem(key) { return this.values.get(key) ?? null; }
  setItem(key, value) { this.values.set(key, String(value)); }
  removeItem(key) { this.values.delete(key); }
}

// Conserva ruta, parámetros y fragmento después del login, consumiendo el destino una sola vez.
test('restores the complete protected URL once', () => {
  prepareBrowser();
  rememberDestination('/lalala?tab=details#section');
  assert.equal(takeDestination(), '/lalala?tab=details#section');
  assert.equal(takeDestination(), '/');
});

// Un destino manipulado no debe enviar credenciales ni navegación a otro sitio o a rutas de autenticación.
test('rejects external destinations and authentication loops', () => {
  prepareBrowser();
  for (const path of ['https://evil.test', '//evil.test', '/\\evil.test', '/login', '/auth/google/redirect', '/api/auth/me']) {
    sessionStorage.setItem('login_destination', path);
    assert.equal(takeDestination(), '/');
  }
});

// El challenge enviado a Google no revela el secreto que debe presentar el navegador durante el canje.
test('binds the Google attempt to a one-time browser verifier', async () => {
  prepareBrowser();
  const url = new URL(await createGoogleLoginUrl(), window.location.origin);
  const verifier = takeLoginVerifier();
  assert.match(verifier, /^[a-f0-9]{64}$/u);
  assert.equal(url.searchParams.get('challenge'), createHash('sha256').update(verifier).digest('hex'));
  assert.equal(url.searchParams.has('verifier'), false);
  assert.equal(takeLoginVerifier(), null);
});

// Rechazar el acceso elimina el token y conserva la pantalla completa antes de llevar al login.
test('clears rejected credentials and preserves the return destination', () => {
  prepareBrowser();
  saveAuthToken('current-token');
  assert.equal(getAuthToken(), 'current-token');
  redirectToLogin();
  assert.equal(getAuthToken(), null);
  assert.equal(window.location.replacedWith, '/login');
  assert.equal(takeDestination(), '/lalala?tab=details#section');
  clearAuthToken();
});
