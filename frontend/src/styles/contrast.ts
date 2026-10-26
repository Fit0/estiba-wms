/**
 * Reads the design tokens and measures the contrast between them, as WCAG 2.2 defines it. It is the
 * code behind `tokens.test.ts`, so the numbers of ADR 0023 are checked and not remembered.
 */

export type Rgb = readonly [number, number, number];

export interface Color {
  readonly rgb: Rgb;
  readonly alpha: number;
}

/**
 * The tokens of one theme, by name without the leading dashes.
 */
export function readTheme(css: string, selector: string): Map<string, string> {
  // Whatever the line endings and the indentation of the file are, the selector matches.
  const pattern = selector
    .split(/\s+/)
    .map((part) => part.replace(/[[\]'.]/g, '\\$&'))
    .join('\\s*');
  const block = new RegExp(`${pattern}\\s*\\{([^}]*)\\}`).exec(css);
  if (block?.[1] === undefined) {
    throw new Error(`There is no block for ${selector}.`);
  }

  const tokens = new Map<string, string>();
  for (const [, name, value] of block[1].matchAll(/--([\w-]+):\s*([^;]+);/g)) {
    if (name !== undefined && value !== undefined) {
      tokens.set(name, value.trim());
    }
  }

  return tokens;
}

export function parseColor(value: string): Color {
  const hex = /^#([0-9a-f]{6})$/i.exec(value);
  if (hex?.[1] !== undefined) {
    const n = Number.parseInt(hex[1], 16);

    return { rgb: [(n >> 16) & 255, (n >> 8) & 255, n & 255], alpha: 1 };
  }

  const functional = /^rgb\(\s*(\d+)\s+(\d+)\s+(\d+)\s*(?:\/\s*([\d.]+))?\s*\)$/.exec(value);
  if (functional !== null) {
    const [, r, g, b, a] = functional;

    return { rgb: [Number(r), Number(g), Number(b)], alpha: a === undefined ? 1 : Number(a) };
  }

  throw new Error(`Not a color the tokens use: ${value}`);
}

/**
 * What a color looks like over another one: a tint is a translucent color over a surface.
 */
export function over(top: Color, below: Color): Color {
  const mix = (a: number, b: number): number => Math.round(a * top.alpha + b * (1 - top.alpha));

  return {
    rgb: [
      mix(top.rgb[0], below.rgb[0]),
      mix(top.rgb[1], below.rgb[1]),
      mix(top.rgb[2], below.rgb[2]),
    ],
    alpha: 1,
  };
}

function luminance([r, g, b]: Rgb): number {
  const linear = (channel: number): number => {
    const c = channel / 255;

    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  };

  return 0.2126 * linear(r) + 0.7152 * linear(g) + 0.0722 * linear(b);
}

export function contrast(a: Color, b: Color): number {
  const [light, dark] = [luminance(a.rgb), luminance(b.rgb)].sort((x, y) => y - x);

  return ((light ?? 0) + 0.05) / ((dark ?? 0) + 0.05);
}
