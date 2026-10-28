import { useSyncExternalStore } from 'react';

/**
 * How tall controls and table rows are (ADR 0030). The back office is compact; the picking PWA is
 * used with gloves and asks for `touch`, whose controls are 48 px.
 */
export const DENSITIES = ['compact', 'comfortable', 'touch'] as const;

export type Density = (typeof DENSITIES)[number];

export const DENSITY_KEY = 'estiba-density';

export function isDensity(value: unknown): value is Density {
  return DENSITIES.some((density) => density === value);
}

/**
 * The density of a visitor: the one they chose, or else `touch` on a screen that is used with a
 * finger (a coarse pointer), or else compact.
 */
export function resolveDensity(stored: string | null, coarsePointer: boolean): Density {
  if (isDensity(stored)) {
    return stored;
  }

  return coarsePointer ? 'touch' : 'compact';
}

export function readStoredDensity(): string | null {
  try {
    return window.localStorage.getItem(DENSITY_KEY);
  } catch {
    return null;
  }
}

export function saveDensity(density: Density): void {
  try {
    window.localStorage.setItem(DENSITY_KEY, density);
  } catch {
    // The density still applies to this visit.
  }
}

const listeners = new Set<() => void>();

/** Puts the density on the page. The sizes of `styles/tokens.css` change with `data-density`. */
export function applyDensity(density: Density, root: HTMLElement = document.documentElement): void {
  root.dataset.density = density;
  for (const listener of listeners) {
    listener();
  }
}

export function currentDensity(root: HTMLElement = document.documentElement): Density {
  const density = root.dataset.density;

  return isDensity(density) ? density : 'compact';
}

export function chooseDensity(density: Density): void {
  saveDensity(density);
  applyDensity(density);
}

function subscribeDensity(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

/** The density on the page now, and a way to move to the next one. */
export function useDensity(): { density: Density; next: Density; cycle: () => void } {
  const density = useSyncExternalStore(subscribeDensity, () => currentDensity());
  const next = DENSITIES[(DENSITIES.indexOf(density) + 1) % DENSITIES.length] ?? 'compact';

  return {
    density,
    next,
    cycle: () => {
      chooseDensity(next);
    },
  };
}
