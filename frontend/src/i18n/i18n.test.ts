import { describe, expect, it } from 'vitest';
import { formatDate, formatNumber } from './format';
import { en } from './en';
import { es } from './es';
import {
  chooseLanguage,
  detectLanguage,
  i18n,
  LANGUAGE_KEY,
  resolveLanguage,
  translate,
} from './index';

describe('translations', () => {
  it('has the same keys in Spanish and English', () => {
    expect(Object.keys(en).sort()).toEqual(Object.keys(es).sort());
  });

  it('has a text for every key in both languages', () => {
    for (const catalog of [es, en]) {
      for (const text of Object.values(catalog)) {
        expect(text.trim()).not.toBe('');
      }
    }
  });

  it('uses the same placeholders in both languages', () => {
    const placeholders = (text: string) => text.match(/\{\{\w+\}\}/g)?.sort() ?? [];

    for (const key of Object.keys(es) as (keyof typeof es)[]) {
      expect(placeholders(en[key]), key).toEqual(placeholders(es[key]));
    }
  });

  it('fills in the values', () => {
    expect(translate('en', 'home.welcome', { tenant: 'Acme' })).toBe('Welcome to Acme.');
    expect(translate('es', 'home.welcome', { tenant: 'Acme' })).toBe('Bienvenido a Acme.');
  });

  it('answers in Spanish unless the browser prefers English', () => {
    expect(detectLanguage([])).toBe('es');
    expect(detectLanguage(['fr-FR', 'en'])).toBe('es');
    expect(detectLanguage(['en-GB', 'es'])).toBe('en');
  });

  it('prefers the stored language over the browser', () => {
    expect(resolveLanguage('en', ['es'])).toBe('en');
    expect(resolveLanguage('xx', ['en'])).toBe('en');
    expect(resolveLanguage(null, [])).toBe('es');
  });

  it('remembers the chosen language and tells the document', async () => {
    await chooseLanguage('en');

    expect(i18n.language).toBe('en');
    expect(window.localStorage.getItem(LANGUAGE_KEY)).toBe('en');
    expect(document.documentElement.lang).toBe('en');
  });
});

describe('formats', () => {
  it('writes numbers and dates the way each language does', () => {
    expect(formatNumber('es', 1234.5)).toBe('1234,5');
    expect(formatNumber('en', 1234567.5)).toBe('1,234,567.5');
    expect(formatDate('en', '2026-03-05', { dateStyle: 'long' })).toBe('March 5, 2026');
    expect(formatDate('es', '2026-03-05', { dateStyle: 'long' })).toBe('5 de marzo de 2026');
    expect(formatDate('es', 'no es fecha')).toBe('no es fecha');
  });
});
