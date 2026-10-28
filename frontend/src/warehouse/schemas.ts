import { z } from 'zod';
import type { MessageKey } from '../i18n';

/**
 * What the forms of the warehouse accept, and what the API says a zone looks like. The texts of the
 * errors are keys of the catalog, so a form shows them in the language that is on. The rules are the
 * ones of the domain (`WarehouseCode`, `ZoneCode`, `LocationCode`, `DisplayName`); the API repeats
 * them and has the last word.
 */
const message = (key: MessageKey): string => key;

const text = (maxLength: number) =>
  z.string().trim().min(1, message('form.required')).max(maxLength, message('form.tooLong'));

/** The code is written in capitals by the API, so the form accepts either and sends capitals. */
const upper = (value: string) => value.trim().toUpperCase();

export const warehouseFormSchema = z.object({
  code: z
    .string()
    .transform(upper)
    .pipe(
      z
        .string()
        .min(2, message('warehouse.error.code'))
        .max(12, message('warehouse.error.code'))
        .regex(/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/, message('warehouse.error.code')),
    ),
  name: text(100),
});

export const storageClasses = ['ambient', 'chilled', 'frozen', 'hazmat'] as const;

const wholeNumber = z.string().trim().regex(/^\d+$/, message('form.wholeNumber')).transform(Number);

export const zoneFormSchema = z.object({
  code: z
    .string()
    .transform(upper)
    .pipe(z.string().regex(/^[A-Z0-9]{1,10}$/, message('zone.error.code'))),
  name: text(100),
  storageClass: z.enum(storageClasses),
  pickSequence: wholeNumber,
});

export const locationTypes = [
  'reserve',
  'pick_face',
  'dock',
  'staging',
  'packing',
  'quarantine',
] as const;

/** A limit may be left empty (none); if it is written it is a whole number of one or more. */
const limit = z
  .string()
  .trim()
  .transform((value, context) => {
    if (value === '') {
      return null;
    }
    if (!/^\d+$/.test(value) || Number(value) < 1) {
      context.addIssue({ code: 'custom', message: message('location.error.limit') });
      return z.NEVER;
    }

    return Number(value);
  });

export const locationFormSchema = z.object({
  zoneId: z.string().min(1, message('form.required')),
  code: z
    .string()
    .transform(upper)
    .pipe(z.string().regex(/^[A-Z]{1,2}-\d{2,3}-\d{2}$/, message('location.error.code'))),
  type: z.enum(locationTypes),
  pickSequence: wholeNumber,
  maxWeightKg: limit,
  maxVolumeLitres: limit,
  maxPalletPositions: limit,
});

export const blockFormSchema = z.object({
  reason: z.string().trim().min(1, message('form.required')).max(200, message('form.tooLong')),
});

/** What the form holds while it is being typed (strings), and what it sends once it is valid. */
export type WarehouseFormInput = z.input<typeof warehouseFormSchema>;
export type WarehouseFormOutput = z.output<typeof warehouseFormSchema>;
export type ZoneFormInput = z.input<typeof zoneFormSchema>;
export type ZoneFormOutput = z.output<typeof zoneFormSchema>;
export type LocationFormInput = z.input<typeof locationFormSchema>;
export type LocationFormOutput = z.output<typeof locationFormSchema>;
export type BlockFormInput = z.input<typeof blockFormSchema>;

/**
 * The OpenAPI document types the zones of a warehouse as a list of anything. This is what they are,
 * read at run time, so that a change of the API shows up as an error and not as a blank screen.
 */
export const zoneSchema = z.object({
  id: z.string(),
  warehouseId: z.string(),
  code: z.string(),
  name: z.string(),
  storageClass: z.enum(storageClasses),
  pickSequence: z.number(),
  families: z.array(z.string()),
});

export type Zone = z.infer<typeof zoneSchema>;

export function parseZones(zones: readonly unknown[]): Zone[] {
  return zones.map((zone) => zoneSchema.parse(zone));
}
