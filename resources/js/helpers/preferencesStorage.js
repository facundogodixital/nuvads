const THEME_KEY = 'theme';
const BRAND_KEY = 'current_brand_id';
const SIDEBAR_KEY = 'sidebar_collapsed';

// La marca de esta pestaña se lee una sola vez al cargar la página, así otra pestaña que cambie de marca no la
// afecta. Sin marca guardada, el backend usa la primera del cliente.
const storedBrandId = localStorage.getItem(BRAND_KEY);

export function getStoredTheme() {
  const theme = localStorage.getItem(THEME_KEY);
  const themeIsValid = theme === 'dark' || theme === 'light';
  return themeIsValid ? theme : null;
}

export function storeTheme(theme) {
  localStorage.setItem(THEME_KEY, theme);
}

export function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
}

export function getSystemTheme() {
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function getStoredSidebarIsCollapsed() {
  return localStorage.getItem(SIDEBAR_KEY) === '1';
}

export function storeSidebarIsCollapsed(sidebarIsCollapsed) {
  localStorage.setItem(SIDEBAR_KEY, sidebarIsCollapsed ? '1' : '0');
}

export function getStoredBrandId() {
  return storedBrandId;
}

export function storeBrandId(brandId) {
  localStorage.setItem(BRAND_KEY, String(brandId));
}
