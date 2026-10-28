import type { Language } from './index';

/**
 * Numbers and dates the way the language writes them: `1.240,5` and `27/10/2026` in Spanish,
 * `1,240.5` and `10/27/2026` in English. The API sends numbers and ISO 8601 dates; a screen shows
 * them through these.
 */
export function formatNumber(
  language: Language,
  value: number,
  options?: Intl.NumberFormatOptions,
): string {
  return new Intl.NumberFormat(language, options).format(value);
}

/**
 * A date, or a moment, from a `Date` or an ISO 8601 text. A plain date (`2026-10-27`) is a day and
 * not a moment, so it is read as that day wherever the browser is; a text with a time is shown in the
 * time zone of the browser. Text that is not a date comes back as it is.
 */
export function formatDate(
  language: Language,
  value: Date | string,
  options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return new Intl.DateTimeFormat(language, { ...options, timeZone: 'UTC' }).format(
      new Date(`${value}T00:00:00Z`),
    );
  }

  const date = typeof value === 'string' ? new Date(value) : value;

  return Number.isNaN(date.getTime())
    ? String(value)
    : new Intl.DateTimeFormat(language, options).format(date);
}

export function formatDateTime(language: Language, value: Date | string): string {
  return formatDate(language, value, { dateStyle: 'medium', timeStyle: 'short' });
}
