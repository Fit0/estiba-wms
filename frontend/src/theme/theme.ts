export type Theme = 'dark' | 'light';

export const THEME_KEY = 'estiba-theme';

export function isTheme(value: unknown): value is Theme {
  return value === 'dark' || value === 'light';
}

/**
 * The theme of a visitor: the one they chose, or else the one their system asks for. The design is
 * dark (DESIGN.md), so a system with no preference gets dark too (ADR 0023).
 */
export function resolveTheme(stored: string | null, systemPrefersLight: boolean): Theme {
  if (isTheme(stored)) {
    return stored;
  }

  return systemPrefersLight ? 'light' : 'dark';
}

/**
 * What the visitor chose last time. A browser that refuses storage (a private window, blocked site
 * data) has no choice to remember, and that must not break the page.
 */
export function readStoredTheme(): string | null {
  try {
    return window.localStorage.getItem(THEME_KEY);
  } catch {
    return null;
  }
}

export function saveTheme(theme: Theme): void {
  try {
    window.localStorage.setItem(THEME_KEY, theme);
  } catch {
    // The theme still applies to this visit.
  }
}

/**
 * Puts the theme on the page. The tokens of `styles/tokens.css` change with `data-theme`.
 */
export function applyTheme(theme: Theme, root: HTMLElement = document.documentElement): void {
  root.dataset.theme = theme;
  for (const listener of listeners) {
    listener();
  }
}

const listeners = new Set<() => void>();

/**
 * The theme that is on the page now. A page that nobody has themed yet is dark, as the CSS is.
 */
export function currentTheme(root: HTMLElement = document.documentElement): Theme {
  const theme = root.dataset.theme;

  return isTheme(theme) ? theme : 'dark';
}

/**
 * For React's `useSyncExternalStore`: the screens that show the theme follow it wherever it changes.
 */
export function subscribeTheme(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

/**
 * The visitor's choice: it goes on the page now and is kept for the next visit.
 */
export function chooseTheme(theme: Theme): void {
  applyTheme(theme);
  saveTheme(theme);
}
