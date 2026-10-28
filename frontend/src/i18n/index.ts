import i18next, { type i18n as I18nInstance } from 'i18next';
import { initReactI18next, useTranslation as useI18nextTranslation } from 'react-i18next';
import { en } from './en';
import { es } from './es';

export type Language = 'es' | 'en';

export type MessageKey = keyof typeof es;

export const LANGUAGES: readonly Language[] = ['es', 'en'];

export const LANGUAGE_KEY = 'estiba-language';

export function isLanguage(value: unknown): value is Language {
  return value === 'es' || value === 'en';
}

/**
 * Spanish unless the browser asks for English (ADR 0004).
 */
export function detectLanguage(preferred: readonly string[]): Language {
  const first = preferred[0]?.toLowerCase() ?? '';

  return first.startsWith('en') ? 'en' : 'es';
}

/**
 * The language of a visitor: the one they chose, or else the one their browser asks for.
 */
export function resolveLanguage(stored: string | null, preferred: readonly string[]): Language {
  return isLanguage(stored) ? stored : detectLanguage(preferred);
}

/**
 * What the visitor chose last time. A browser that refuses storage has no choice to remember, and
 * that must not break the page.
 */
export function readStoredLanguage(): string | null {
  try {
    return window.localStorage.getItem(LANGUAGE_KEY);
  } catch {
    return null;
  }
}

function saveLanguage(language: Language): void {
  try {
    window.localStorage.setItem(LANGUAGE_KEY, language);
  } catch {
    // The language still applies to this visit.
  }
}

/**
 * The translations of the app (ADR 0028). The keys are the ones of the catalogs, with their dots,
 * so the separators of i18next are off; `{{name}}` is how a value is filled in. React already
 * escapes what it draws, so i18next must not do it twice.
 */
export const i18n: I18nInstance = i18next.createInstance();

i18n.on('languageChanged', (language) => {
  document.documentElement.lang = language;
});

void i18n.use(initReactI18next).init({
  resources: { es: { translation: es }, en: { translation: en } },
  lng: resolveLanguage(readStoredLanguage(), navigator.languages),
  fallbackLng: 'es',
  supportedLngs: [...LANGUAGES],
  keySeparator: false,
  nsSeparator: false,
  interpolation: { escapeValue: false },
  initAsync: false,
  returnNull: false,
});

/**
 * The language a visitor picks: it changes at once, everywhere, and it is remembered for the next
 * visit.
 */
export async function chooseLanguage(language: Language): Promise<void> {
  saveLanguage(language);
  await i18n.changeLanguage(language);
}

type Values = Readonly<Record<string, string>>;

/**
 * The text of a key in a language, with `{{name}}` filled in. For the places where there is no React
 * to ask for it.
 */
export function translate(language: Language, key: MessageKey, values: Values = {}): string {
  return i18n.t(key, { lng: language, ...values });
}

/**
 * The language that is on now, and `t` to write in it. It has the shape the components always used,
 * so a key that does not exist does not compile. The component draws again when the language changes.
 */
export function useTranslation(): {
  language: Language;
  t: (key: MessageKey, values?: Values) => string;
} {
  const { t, i18n: instance } = useI18nextTranslation();
  const language = isLanguage(instance.resolvedLanguage) ? instance.resolvedLanguage : 'es';

  return { language, t: (key, values) => t(key, values ?? {}) };
}
