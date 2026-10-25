import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

// Without globals, Testing Library does not clean the page between tests by itself.
afterEach(cleanup);

// The interface is Spanish unless the browser asks for English (ADR 0004), and jsdom asks for English.
beforeEach(() => {
  vi.spyOn(navigator, 'languages', 'get').mockReturnValue(['es-ES', 'es']);
});
