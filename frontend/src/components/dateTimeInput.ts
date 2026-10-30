/**
 * Tomorrow at the same hour, rounded down to the hour, written as a `datetime-local` field takes it
 * (local time, no offset): a sensible date to start a form from.
 */
export function tomorrowForInput(): string {
  const date = new Date(Date.now() + 24 * 3600 * 1000);
  date.setMinutes(0, 0, 0);
  const pad = (n: number): string => String(n).padStart(2, '0');

  return `${String(date.getFullYear())}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:00`;
}
