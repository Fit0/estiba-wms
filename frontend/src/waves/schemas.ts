import { z } from 'zod';
import type { MessageKey } from '../i18n';

/** What the API says inside a wave (its lists of ids are `unknown[]`), and what the release form takes. */
const message = (key: MessageKey): string => key;

export function parseIds(ids: readonly unknown[]): string[] {
  return ids.map((id) => z.string().parse(id));
}

export const releaseFormSchema = z.object({
  pickLists: z
    .string()
    .trim()
    .regex(/^\d+$/, message('form.wholeNumber'))
    .transform(Number)
    .pipe(
      z.number().int().min(1, message('form.positive')).max(20, message('wave.error.pickLists')),
    ),
});

export type ReleaseFormInput = z.input<typeof releaseFormSchema>;
export type ReleaseFormOutput = z.output<typeof releaseFormSchema>;
