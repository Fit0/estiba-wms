import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';
import { i18n, LANGUAGE_KEY } from './i18n';
import { DENSITY_KEY } from './theme/density';

// Without globals, Testing Library does not clean the page between tests by itself.
afterEach(cleanup);

// The interface is Spanish unless the browser asks for English (ADR 0004), and jsdom asks for English.
// The language lives in one instance for the whole run, so each test starts from Spanish again.
beforeEach(async () => {
  vi.spyOn(navigator, 'languages', 'get').mockReturnValue(['es-ES', 'es']);
  window.localStorage.removeItem(LANGUAGE_KEY);
  window.localStorage.removeItem(DENSITY_KEY);
  delete document.documentElement.dataset.density;
  await i18n.changeLanguage('es');
});

// A busy machine (CI, a laptop running Docker) can take longer than a second to settle a page.
configure({ asyncUtilTimeout: 4000 });

// jsdom has no matchMedia. A narrow screen is the default, so the sidebar starts as a rail.
beforeEach(() => {
  vi.stubGlobal('matchMedia', (query: string) => ({
    matches: false,
    media: query,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
  }));
});

// jsdom lacks what Radix and cmdk ask of a browser: observing sizes, scrolling an item into view and
// the pointer capture of a select.
beforeEach(() => {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      observe = () => undefined;
      unobserve = () => undefined;
      disconnect = () => undefined;
    },
  );
  Element.prototype.scrollIntoView = () => undefined;
  Element.prototype.hasPointerCapture = () => false;
  Element.prototype.releasePointerCapture = () => undefined;
});
