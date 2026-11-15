import { z } from 'zod';
import type { MessageKey } from '../i18n';

const message = (key: MessageKey): string => key;

/**
 * Where a task goes. A location other than the suggested one needs a reason (INB-13); a task with no
 * suggestion needs none. The API repeats the rule and has the last word.
 */
export function confirmPutawaySchema(suggestedLocationId: string | null) {
  return z
    .object({
      locationId: z.string().min(1, message('form.required')),
      reason: z.string().trim().max(200, message('form.tooLong')),
    })
    .superRefine((values, context) => {
      const override = suggestedLocationId !== null && values.locationId !== suggestedLocationId;
      if (override && values.reason === '') {
        context.addIssue({ code: 'custom', path: ['reason'], message: message('form.required') });
      }
    });
}

/** Both tasks keep at least one unit (INB-12, INB-14), so the units to give are 1 up to one less. */
export function splitPutawaySchema(taskUnits: number) {
  return z.object({
    units: z
      .string()
      .trim()
      .regex(/^\d+$/, message('form.wholeNumber'))
      .transform(Number)
      .pipe(
        z
          .number()
          .min(1, message('putaway.error.split'))
          .max(taskUnits - 1, message('putaway.error.split')),
      ),
  });
}

export type ConfirmPutawayInput = z.input<ReturnType<typeof confirmPutawaySchema>>;
export type SplitPutawayInput = z.input<ReturnType<typeof splitPutawaySchema>>;
export type SplitPutawayOutput = z.output<ReturnType<typeof splitPutawaySchema>>;
