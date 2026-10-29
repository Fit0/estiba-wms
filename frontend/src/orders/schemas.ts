import { z } from 'zod';
import type { MessageKey } from '../i18n';

/**
 * What the API says inside an order (its lines are `unknown[]` in the OpenAPI document), read at run
 * time, and what the form to register an order accepts. The rules repeat the cheap ones of the domain
 * (`OrderReference`, `CustomerName`, `ShippingAddress`); the API has the last word.
 */
const message = (key: MessageKey): string => key;

const orderLineSchema = z.object({
  lineNumber: z.number().int(),
  productId: z.string(),
  orderedUnits: z.number().int(),
  allocatedUnits: z.number().int(),
  shortageUnits: z.number().int(),
  pickedUnits: z.number().int(),
  shippedUnits: z.number().int(),
  reservationId: z.string().nullish(),
});

export type OrderLine = z.infer<typeof orderLineSchema>;

export function parseOrderLines(lines: readonly unknown[]): OrderLine[] {
  return lines.map((line) => orderLineSchema.parse(line));
}

export const priorities = ['low', 'normal', 'high', 'urgent'] as const;

const text = (max: number) =>
  z.string().trim().min(1, message('form.required')).max(max, message('form.tooLong'));

const units = z
  .string()
  .trim()
  .regex(/^\d+$/, message('form.wholeNumber'))
  .transform(Number)
  .pipe(z.number().int().min(1, message('form.positive')));

export const orderFormSchema = z.object({
  reference: text(40),
  customer: text(100),
  street: text(120),
  postalCode: text(12),
  city: text(60),
  countryCode: z
    .string()
    .trim()
    .toUpperCase()
    .regex(/^[A-Z]{2}$/, message('order.error.country')),
  priority: z.enum(priorities),
  cutOffAt: z
    .string()
    .min(1, message('form.required'))
    .transform((value) => new Date(value))
    .pipe(z.date({ error: message('form.required') }))
    .transform((date) => date.toISOString()),
  lines: z
    .array(
      z.object({
        productId: z.string().min(1, message('order.error.product')),
        units,
      }),
    )
    .min(1, message('order.error.lines')),
});

export type OrderFormInput = z.input<typeof orderFormSchema>;
export type OrderFormOutput = z.output<typeof orderFormSchema>;
