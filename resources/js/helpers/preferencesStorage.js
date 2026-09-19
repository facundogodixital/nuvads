const THEME_KEY = 'theme';
const SIDEBAR_KEY = 'sidebar_collapsed';

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
