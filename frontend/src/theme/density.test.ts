import { describe, expect, it } from 'vitest';
import {
  applyDensity,
  chooseDensity,
  currentDensity,
  DENSITY_KEY,
  resolveDensity,
} from './density';

describe('the density', () => {
  it('is compact, unless the screen is used with a finger or the visitor chose', () => {
    expect(resolveDensity(null, false)).toBe('compact');
    expect(resolveDensity(null, true)).toBe('touch');
    expect(resolveDensity('comfortable', true)).toBe('comfortable');
    expect(resolveDensity('huge', false)).toBe('compact');
  });

  it('goes on the page and is remembered', () => {
    expect(currentDensity()).toBe('compact');

    chooseDensity('touch');

    expect(document.documentElement.dataset.density).toBe('touch');
    expect(window.localStorage.getItem(DENSITY_KEY)).toBe('touch');
    applyDensity('compact');
    expect(currentDensity()).toBe('compact');
  });
});
