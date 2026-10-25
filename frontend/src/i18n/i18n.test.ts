import { describe, expect, it } from 'vitest';
import { en } from './en';
import { es } from './es';
import { detectLanguage, translate } from './index';

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
});
