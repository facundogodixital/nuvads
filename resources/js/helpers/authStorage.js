const TOKEN_KEY = 'auth_token';
const DESTINATION_KEY = 'login_destination';
const VERIFIER_KEY = 'login_verifier';

export function getAuthToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function saveAuthToken(token) {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearAuthToken() {
  localStorage.removeItem(TOKEN_KEY);
}

export function rememberDestination(path) {
  if (isInternalDestination(path)) {
    sessionStorage.setItem(DESTINATION_KEY, path);
  }
}

export function takeDestination() {
  const path = sessionStorage.getItem(DESTINATION_KEY);
  sessionStorage.removeItem(DESTINATION_KEY);
  return isInternalDestination(path) ? path : '/';
}

export function redirectToLogin() {
  clearAuthToken();
  const path = window.location.pathname;
  const isLoginPage = path === '/login' || path === '/login/callback';
  if (!isLoginPage) {
    rememberDestination(`${path}${window.location.search}${window.location.hash}`);
    window.location.replace('/login');
  }
}

export async function createGoogleLoginUrl() {
  const bytes = crypto.getRandomValues(new Uint8Array(32));
  const verifier = toHex(bytes);
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier));
  sessionStorage.setItem(VERIFIER_KEY, verifier);

  return `/auth/google/redirect?challenge=${toHex(new Uint8Array(digest))}`;
}

export function takeLoginVerifier() {
  const verifier = sessionStorage.getItem(VERIFIER_KEY);
  sessionStorage.removeItem(VERIFIER_KEY);
  return verifier;
}

function toHex(bytes) {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function isInternalDestination(path) {
  const isRelativePath = typeof path === 'string' && path.startsWith('/') && !path.startsWith('//');
  if (!isRelativePath) {
    return false;
  }

  for (const character of path) {
    const isUnsafeCharacter = character <= ' ' || character === '\\';
    if (isUnsafeCharacter) {
      return false;
    }
  }

  const url = new URL(path, window.location.origin);
  const isSameOrigin = url.origin === window.location.origin;
  const isAuthPath = /^\/(?:login|auth|api)(?:\/|$)/u.test(url.pathname);
  return isSameOrigin && !isAuthPath;
}
