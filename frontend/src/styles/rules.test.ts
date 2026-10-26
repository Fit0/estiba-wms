import { describe, expect, it } from 'vitest';

const sources = import.meta.glob<string>(['/src/**/*.{ts,tsx}', '!/src/**/*.test.{ts,tsx}'], {
  query: '?raw',
  import: 'default',
  eager: true,
});

/**
 * The rules of the design that a linter does not know (ADR 0023). A component that breaks one is
 * not wrong in one theme: it is wrong in the other.
 */
describe('the components follow the design system', () => {
  // `contrast.ts` reads and measures colors: it is the one place that is about them.
  const components = Object.entries(sources).filter(
    ([path]) => !path.endsWith('/styles/contrast.ts'),
  );

  it('has sources to look at', () => {
    expect(components.length).toBeGreaterThan(10);
  });

  it('never writes a color of its own', () => {
    const offenders = components.filter(([, code]) =>
      /#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i.test(code),
    );

    expect(offenders.map(([path]) => path)).toEqual([]);
  });

  it('never styles a theme by itself with a dark: variant', () => {
    const offenders = components.filter(([, code]) => /\bdark:/.test(code));

    expect(offenders.map(([path]) => path)).toEqual([]);
  });
});
