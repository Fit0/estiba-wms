export type Density = 'compact' | 'comfortable' | 'touch';

/** The densities that a person can pick for the tables. `touch` is for the picking PWA (UI-04). */
export type TableDensity = Exclude<Density, 'touch'>;

export const DENSITY_KEY = 'estiba-density';

const listeners = new Set<() => void>();

export function isTableDensity(value: unknown): value is TableDensity {
  return value === 'compact' || value === 'comfortable';
}

/**
 * What the person chose last time. A browser that refuses storage has no choice to remember, and
 * that must not break the page.
 */
export function readStoredDensity(): TableDensity | null {
  try {
    const stored = window.localStorage.getItem(DENSITY_KEY);

    return isTableDensity(stored) ? stored : null;
  } catch {
    return null;
  }
}

/**
 * Puts the density on the page. The row and control heights of `styles/tokens.css` change with
 * `data-density`.
 */
export function applyDensity(density: Density, root: HTMLElement = document.documentElement): void {
  root.dataset.density = density;
  for (const listener of listeners) {
    listener();
  }
}

export function currentDensity(root: HTMLElement = document.documentElement): Density {
  const density = root.dataset.density;

  return density === 'comfortable' || density === 'touch' ? density : 'compact';
}

/**
 * For React's `useSyncExternalStore`: whatever shows the density follows it wherever it changes.
 */
export function subscribeDensity(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

/**
 * The choice of a person: it goes on the page now and is kept for the next visit.
 */
export function chooseDensity(density: TableDensity): void {
  applyDensity(density);
  try {
    window.localStorage.setItem(DENSITY_KEY, density);
  } catch {
    // The density still applies to this visit.
  }
}
