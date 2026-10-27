import { describe, expect, it } from 'vitest';
import { contrast, over, parseColor, readTheme, type Color } from './contrast';
import tokensCss from './tokens.css?raw';

const themes = {
  dark: ":root,\n:root[data-theme='dark']",
  light: ":root[data-theme='light']",
} as const;

/** WCAG 2.2 AA: 4.5:1 for text, 3:1 for the edge of a control and for the focus ring. */
const TEXT = 4.5;
const NON_TEXT = 3;

const surfaces = ['canvas', 'surface-1', 'surface-2', 'surface-3'] as const;
const statuses = ['ok', 'warn', 'bad', 'info'] as const;

describe.each(Object.entries(themes))('the %s theme', (name, selector) => {
  const tokens = readTheme(tokensCss, selector);
  const color = (token: string): Color => {
    const value = tokens.get(token);
    if (value === undefined) {
      throw new Error(`The ${name} theme has no --${token}.`);
    }

    return parseColor(value);
  };
  const ratio = (foreground: Color, background: Color): number =>
    contrast(over(foreground, background), background);

  it.each(surfaces)('reads the main text on %s', (surface) => {
    expect(ratio(color('fg'), color(surface))).toBeGreaterThanOrEqual(TEXT);
    expect(ratio(color('fg-secondary'), color(surface))).toBeGreaterThanOrEqual(TEXT);
  });

  // Muted text is for what is on the canvas and on tables and cards, never on an overlay.
  it.each(['canvas', 'surface-1', 'surface-2'])('reads the muted text on %s', (surface) => {
    expect(ratio(color('fg-muted'), color(surface))).toBeGreaterThanOrEqual(TEXT);
  });

  it.each(['canvas', 'surface-1', 'surface-2'])('reads a link on %s', (surface) => {
    expect(ratio(color('accent'), color(surface))).toBeGreaterThanOrEqual(TEXT);
  });

  it.each(['primary', 'primary-hover', 'primary-active'])(
    'reads the label of a %s button',
    (fill) => {
      expect(ratio(color('on-primary'), color(fill))).toBeGreaterThanOrEqual(TEXT);
    },
  );

  it.each(statuses)(
    'reads the %s status text on its own tint, over a table and over a card',
    (status) => {
      for (const surface of ['surface-1', 'surface-2']) {
        const tinted = over(color(`${status}-fill`), color(surface));

        expect(ratio(color(status), tinted)).toBeGreaterThanOrEqual(TEXT);
      }
    },
  );

  // The trend of a KPI card is status text straight on the card, with no tint under it.
  it.each(statuses)('reads the %s status text straight on a card', (status) => {
    expect(ratio(color(status), color('surface-1'))).toBeGreaterThanOrEqual(TEXT);
  });

  // A badge in a selected row sits on its tint, on the selection, on the card.
  it.each(statuses)('reads the %s status badge in a selected row', (status) => {
    const row = over(color('selected'), color('surface-1'));
    const tinted = over(color(`${status}-fill`), row);

    expect(ratio(color(status), tinted)).toBeGreaterThanOrEqual(TEXT);
  });

  it.each(statuses)('reads the %s status text straight on the canvas', (status) => {
    expect(ratio(color(status), color('canvas'))).toBeGreaterThanOrEqual(TEXT);
  });

  it.each(['canvas', 'surface-1', 'surface-2'])('shows the focus ring on %s', (surface) => {
    expect(ratio(color('ring'), color(surface))).toBeGreaterThanOrEqual(NON_TEXT);
  });

  it.each(['canvas', 'surface-1', 'surface-2'])('shows the edge of a control on %s', (surface) => {
    expect(ratio(color('input-border'), color(surface))).toBeGreaterThanOrEqual(NON_TEXT);
  });

  it('keeps a selected row readable', () => {
    const row = over(color('selected'), color('surface-1'));

    expect(ratio(color('fg'), row)).toBeGreaterThanOrEqual(TEXT);
    expect(ratio(color('fg-secondary'), row)).toBeGreaterThanOrEqual(TEXT);
  });
});

describe('the two themes', () => {
  const dark = readTheme(tokensCss, ":root,\n:root[data-theme='dark']");
  const light = readTheme(tokensCss, ":root[data-theme='light']");

  it('say the same names', () => {
    expect([...light.keys()].sort()).toEqual([...dark.keys()].sort());
  });
});

describe('the contrast measure', () => {
  it('agrees with the WCAG examples', () => {
    const black = parseColor('#000000');
    const white = parseColor('#ffffff');

    expect(contrast(black, white)).toBeCloseTo(21, 5);
    expect(contrast(white, white)).toBeCloseTo(1, 5);
  });
});
