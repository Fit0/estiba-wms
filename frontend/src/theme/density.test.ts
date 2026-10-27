import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  DENSITY_KEY,
  applyDensity,
  chooseDensity,
  currentDensity,
  isTableDensity,
  readStoredDensity,
  subscribeDensity,
} from './density';

describe('density', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
    delete document.documentElement.dataset.density;
  });

  it('is compact when nobody has asked for anything', () => {
    expect(currentDensity()).toBe('compact');
  });

  it('puts the density on the page', () => {
    applyDensity('touch');

    expect(document.documentElement.dataset.density).toBe('touch');
    expect(currentDensity()).toBe('touch');
  });

  it('remembers the choice of a person and tells whoever shows it', () => {
    const listener = vi.fn();
    const unsubscribe = subscribeDensity(listener);

    chooseDensity('comfortable');
    unsubscribe();
    chooseDensity('compact');

    expect(listener).toHaveBeenCalledTimes(1);
    expect(localStorage.getItem(DENSITY_KEY)).toBe('compact');
  });

  it('never keeps the touch density, which belongs to the PWA and not to a person', () => {
    localStorage.setItem(DENSITY_KEY, 'touch');

    expect(readStoredDensity()).toBeNull();
    expect(isTableDensity('touch')).toBe(false);
  });

  it('reads what was chosen last time', () => {
    localStorage.setItem(DENSITY_KEY, 'comfortable');

    expect(readStoredDensity()).toBe('comfortable');
  });

  it('works without storage', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });

    expect(readStoredDensity()).toBeNull();
    expect(() => {
      chooseDensity('comfortable');
    }).not.toThrow();
  });
});
