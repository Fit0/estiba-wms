import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

// Without globals, Testing Library does not clean the page between tests by itself.
afterEach(cleanup);

// The interface is Spanish unless the browser asks for English (ADR 0004), and jsdom asks for English.
beforeEach(() => {
  vi.spyOn(navigator, 'languages', 'get').mockReturnValue(['es-ES', 'es']);
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
