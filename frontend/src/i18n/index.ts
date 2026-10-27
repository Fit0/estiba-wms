import { en } from './en';
import { es } from './es';

export type Language = 'es' | 'en';

export type MessageKey = keyof typeof es;

const catalogs: Record<Language, Record<MessageKey, string>> = { es, en };

/**
 * Spanish unless the browser asks for English (ADR 0004).
 */
export function detectLanguage(preferred: readonly string[]): Language {
  const first = preferred[0]?.toLowerCase() ?? '';

  return first.startsWith('en') ? 'en' : 'es';
}

/**
 * The text of a key, with `{{name}}` filled in. It has the shape of `t` in react-i18next, which
 * replaces this file with E05-06, so the components do not change.
 */
export function translate(
  language: Language,
  key: MessageKey,
  values: Readonly<Record<string, string>> = {},
): string {
  return catalogs[language][key].replace(/\{\{(\w+)\}\}/g, (placeholder, name: string) => {
    return values[name] ?? placeholder;
  });
}

export function useTranslation(): {
  language: Language;
  t: (key: MessageKey, values?: Readonly<Record<string, string>>) => string;
} {
  const language = detectLanguage(navigator.languages);

  return { language, t: (key, values) => translate(language, key, values) };
}
