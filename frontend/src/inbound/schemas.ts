import { z } from 'zod';
import type { MessageKey } from '../i18n';

/**
 * What the API says inside an ASN and inside a receipt (the OpenAPI document types these lists as
 * `unknown[]`), read at run time, and what the form to count an item accepts.
 */
const message = (key: MessageKey): string => key;

const asnLineSchema = z.object({
  productId: z.string(),
  expectedUnits: z.number().int(),
  receivedUnits: z.number().int(),
  lot: z.string().nullish(),
  expiryDate: z.string().nullish(),
});

export type AsnLine = z.infer<typeof asnLineSchema>;

export function parseAsnLines(lines: readonly unknown[]): AsnLine[] {
  return lines.map((line) => asnLineSchema.parse(line));
}

export const conditions = ['good', 'damaged'] as const;
export type Condition = (typeof conditions)[number];

const expectedItemSchema = z.object({ productId: z.string(), units: z.number().int() });

const receivedLineSchema = z.object({
  lineNumber: z.number().int(),
  productId: z.string(),
  units: z.number().int(),
  lot: z.string().nullish(),
  expiryDate: z.string().nullish(),
  condition: z.enum(conditions),
  receivedAt: z.string(),
});

export const discrepancyTypes = ['shortage', 'excess', 'unexpected_product', 'damaged'] as const;

const discrepancySchema = z.object({
  type: z.enum(discrepancyTypes),
  productId: z.string(),
  units: z.number().int(),
});

export type ExpectedItem = z.infer<typeof expectedItemSchema>;
export type ReceivedLine = z.infer<typeof receivedLineSchema>;
export type Discrepancy = z.infer<typeof discrepancySchema>;

export function parseExpected(items: readonly unknown[]): ExpectedItem[] {
  return items.map((item) => expectedItemSchema.parse(item));
}

export function parseReceivedLines(lines: readonly unknown[]): ReceivedLine[] {
  return lines.map((line) => receivedLineSchema.parse(line));
}

export function parseDiscrepancies(discrepancies: readonly unknown[]): Discrepancy[] {
  return discrepancies.map((discrepancy) => discrepancySchema.parse(discrepancy));
}

/** A barcode is 8 to 14 digits (GTIN-8, 12, 13 and 14); the API checks the digit and has the last word. */
const gtin = z
  .string()
  .trim()
  .regex(/^\d{8,14}$/, message('receive.error.gtin'));

const units = z
  .string()
  .trim()
  .regex(/^\d+$/, message('form.wholeNumber'))
  .transform(Number)
  .pipe(z.number().int().min(1, message('receive.error.units')));

/** The lot and the expiry date may be left empty; an empty one is sent as nothing. */
const optionalText = z
  .string()
  .trim()
  .max(40, message('form.tooLong'))
  .transform((value) => (value === '' ? null : value));

const expiryDate = z
  .string()
  .trim()
  .transform((value, context) => {
    if (value === '') {
      return null;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(Date.parse(value))) {
      context.addIssue({ code: 'custom', message: message('receive.error.expiry') });
      return z.NEVER;
    }

    return value;
  });

export const receiveFormSchema = z.object({
  gtin,
  units,
  lot: optionalText,
  expiryDate,
  condition: z.enum(conditions),
});

export type ReceiveFormInput = z.input<typeof receiveFormSchema>;
export type ReceiveFormOutput = z.output<typeof receiveFormSchema>;

const text = (max: number) =>
  z.string().trim().min(1, message('form.required')).max(max, message('form.tooLong'));

/** The form to register an expected delivery. The rules repeat the cheap ones of the domain. */
export const asnFormSchema = z.object({
  reference: text(40),
  supplier: text(100),
  expectedDate: z
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
        lot: optionalText,
        expiryDate,
      }),
    )
    .min(1, message('asn.error.lines')),
});

export type AsnFormInput = z.input<typeof asnFormSchema>;
export type AsnFormOutput = z.output<typeof asnFormSchema>;
