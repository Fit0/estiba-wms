import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  THEME_KEY,
  applyTheme,
  chooseTheme,
  currentTheme,
  isTheme,
  readStoredTheme,
  resolveTheme,
  saveTheme,
  subscribeTheme,
} from './theme';

describe('theme', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
    delete document.documentElement.dataset.theme;
  });

  it('is dark when nobody has asked for anything', () => {
    expect(resolveTheme(null, false)).toBe('dark');
  });

  it('follows a system that asks for light', () => {
    expect(resolveTheme(null, true)).toBe('light');
  });

  it('prefers what the visitor chose to what the system asks for', () => {
    expect(resolveTheme('dark', true)).toBe('dark');
    expect(resolveTheme('light', false)).toBe('light');
  });

  it('ignores a stored value that is not a theme', () => {
    expect(resolveTheme('sepia', false)).toBe('dark');
    expect(isTheme('sepia')).toBe(false);
  });

  it('remembers the choice between visits', () => {
    saveTheme('light');

    expect(localStorage.getItem(THEME_KEY)).toBe('light');
    expect(readStoredTheme()).toBe('light');
  });

  it('works without storage', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });

    expect(readStoredTheme()).toBeNull();
    expect(() => {
      saveTheme('light');
    }).not.toThrow();
  });

  it('puts the theme on the page', () => {
    applyTheme('light');

    expect(document.documentElement.dataset.theme).toBe('light');
  });

  it('says which theme is on the page, dark when none is', () => {
    expect(currentTheme()).toBe('dark');

    applyTheme('light');

    expect(currentTheme()).toBe('light');
  });

  it('keeps the choice and tells whoever shows the theme', () => {
    const listener = vi.fn();
    const unsubscribe = subscribeTheme(listener);

    chooseTheme('light');
    unsubscribe();
    chooseTheme('dark');

    expect(listener).toHaveBeenCalledTimes(1);
    expect(localStorage.getItem(THEME_KEY)).toBe('dark');
  });
});
