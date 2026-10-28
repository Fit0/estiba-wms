/// <reference types="node" />
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import tokensCss from './tokens.css?raw';

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

  it('never sets a text size by hand, so every text follows the scale of the tokens', () => {
    const offenders = components.filter(([, code]) => /\btext-\[\d/.test(code));

    expect(offenders.map(([path]) => path)).toEqual([]);
  });

  it('never gives a control a fixed height between 32 and 44 px, which touch would not enlarge', () => {
    // Pieces that are drawn and cannot be pressed: a mark, an icon tile, a loading skeleton.
    const decorative = [
      '/src/components/BrandMark.tsx',
      '/src/components/KpiCard.tsx',
      '/src/pages/LoginPage.tsx',
    ];
    const offenders = components
      .filter(([path]) => !decorative.includes(path))
      .filter(([, code]) => /(?:^|[\s"'`:])(?:h|min-h|size)-(?:8|9|10|11)(?=[\s"'`])/.test(code));

    expect(offenders.map(([path]) => path)).toEqual([]);
  });
});

describe('the text scale', () => {
  // The Tailwind plugin empties `index.css?raw`, so the file is read as it is on disk.
  const indexCss = readFileSync(`${process.cwd()}/src/index.css`, 'utf8');

  it('has no size under 11 px, which only the uppercase labels use, and a body of 13 px or more', () => {
    const sizes = [...indexCss.matchAll(/--text-([a-z-]+): ([\d.]+)rem;/g)].map((match) => ({
      name: match[1] ?? '',
      px: Number(match[2]) * 16,
    }));

    expect(sizes.length).toBeGreaterThan(5);
    expect(sizes.filter(({ px }) => px < 11)).toEqual([]);
    expect(sizes.find(({ name }) => name === 'body-md')?.px).toBeGreaterThanOrEqual(13);
    expect(sizes.find(({ name }) => name === 'body-lg')?.px).toBeGreaterThanOrEqual(15);
  });

  it('makes a touch control 48 px and keeps the compact ones as they were', () => {
    expect(tokensCss).toMatch(/\[data-density='touch'\] \{\s*--control-height: 3rem;/);
    expect(tokensCss).toMatch(/:root \{\s*--control-height: 2rem;/);
  });
});
