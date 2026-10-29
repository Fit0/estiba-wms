import { z } from 'zod';
import type { MessageKey } from '../i18n';
import { packagingTypes } from './schemas';

/**
 * What the forms of the catalogue accept. The messages are keys of the catalog, so a form shows them
 * in the language that is on. The rules repeat the ones of the domain (`Sku`, `ProductName`,
 * `ProductFamily`, `Dimensions`, `TrackingPolicy`, `Gtin`); the API has the last word.
 */
const message = (key: MessageKey): string => key;

const upper = (value: string) => value.trim().toUpperCase();

const positive = z
  .string()
  .trim()
  .regex(/^\d+$/, message('form.wholeNumber'))
  .transform(Number)
  .pipe(z.number().int().min(1, message('form.positive')));

/** A number of days may be left empty (none); if it is written it is a whole number of 0 or more. */
const optionalDays = z
  .string()
  .trim()
  .transform((value, context) => {
    if (value === '') {
      return null;
    }
    if (!/^\d+$/.test(value)) {
      context.addIssue({ code: 'custom', message: message('form.zeroOrMore') });
      return z.NEVER;
    }

    return Number(value);
  });

/** The family is optional and, when written, in capitals with single hyphens between groups. */
const optionalFamily = z
  .string()
  .transform(upper)
  .pipe(
    z
      .string()
      .max(20, message('product.error.family'))
      .regex(/^(?:[A-Z0-9]+(?:-[A-Z0-9]+)*)?$/, message('product.error.family')),
  )
  .transform((value) => (value === '' ? null : value));

const name = z.string().trim().min(1, message('form.required')).max(150, message('form.tooLong'));

export const baseUnits = ['each', 'pair', 'set', 'kilogram', 'litre', 'metre'] as const;
export const storageClasses = ['ambient', 'chilled', 'frozen', 'hazmat'] as const;
export const turnoverClasses = ['A', 'B', 'C'] as const;

export const productFormSchema = z
  .object({
    sku: z
      .string()
      .transform(upper)
      .pipe(
        z.string().regex(/^[A-Z0-9](?:[A-Z0-9._/-]{0,38}[A-Z0-9])?$/, message('product.error.sku')),
      ),
    name,
    baseUnit: z.enum(baseUnits),
    storageClass: z.enum(storageClasses),
    lengthMm: positive,
    widthMm: positive,
    heightMm: positive,
    weightGrams: positive,
    trackedByLot: z.boolean(),
    trackedByExpiry: z.boolean(),
    minShelfLifeAtReceiptDays: optionalDays,
    minShelfLifeAtShipmentDays: optionalDays,
    family: optionalFamily,
    turnoverClass: z.enum(turnoverClasses),
  })
  .superRefine((values, context) => {
    if (values.trackedByExpiry && !values.trackedByLot) {
      context.addIssue({
        code: 'custom',
        path: ['trackedByExpiry'],
        message: message('product.error.expiryNeedsLot'),
      });
    }
    for (const field of ['minShelfLifeAtReceiptDays', 'minShelfLifeAtShipmentDays'] as const) {
      if (values[field] !== null && !values.trackedByExpiry) {
        context.addIssue({
          code: 'custom',
          path: [field],
          message: message('product.error.shelfLifeNeedsExpiry'),
        });
      }
    }
  });

export const editFormSchema = z.object({
  name,
  lengthMm: positive,
  widthMm: positive,
  heightMm: positive,
  weightGrams: positive,
});

export const gtinFormSchema = z.object({
  gtin: z
    .string()
    .trim()
    .regex(/^\d{8,14}$/, message('receive.error.gtin')),
});

export const packagingFormSchema = z.object({
  type: z.enum(packagingTypes),
  baseUnits: positive,
});

export const classifyFormSchema = z.object({
  turnoverClass: z.enum(turnoverClasses),
  family: optionalFamily,
});

export type ProductFormInput = z.input<typeof productFormSchema>;
export type ProductFormOutput = z.output<typeof productFormSchema>;
export type EditFormInput = z.input<typeof editFormSchema>;
export type EditFormOutput = z.output<typeof editFormSchema>;
export type GtinFormInput = z.input<typeof gtinFormSchema>;
export type PackagingFormInput = z.input<typeof packagingFormSchema>;
export type PackagingFormOutput = z.output<typeof packagingFormSchema>;
export type ClassifyFormInput = z.input<typeof classifyFormSchema>;
export type ClassifyFormOutput = z.output<typeof classifyFormSchema>;
