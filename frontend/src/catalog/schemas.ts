import { z } from 'zod';

/**
 * What the API says inside a product and inside its stock. The OpenAPI document types these lists as
 * `unknown[]` (the resource is plain JSON), so the screen checks them at run time and fails loudly if
 * the shape ever changes.
 */
export const packagingTypes = ['inner_pack', 'case', 'pallet'] as const;

const packagingLevelSchema = z.object({
  type: z.enum(packagingTypes),
  baseUnits: z.number().int().positive(),
});

export type PackagingLevel = z.infer<typeof packagingLevelSchema>;

export function parseGtins(gtins: readonly unknown[]): string[] {
  return gtins.map((gtin) => z.string().parse(gtin));
}

export function parsePackagingLevels(levels: readonly unknown[]): PackagingLevel[] {
  return levels.map((level) => packagingLevelSchema.parse(level));
}

const warehouseStockSchema = z.object({
  warehouseId: z.string(),
  warehouseCode: z.string(),
  onHand: z.number().int(),
  available: z.number().int(),
  quarantine: z.number().int(),
  damaged: z.number().int(),
  onHold: z.number().int(),
  locations: z.number().int(),
});

export type WarehouseStock = z.infer<typeof warehouseStockSchema>;

export function parseWarehouseStock(warehouses: readonly unknown[]): WarehouseStock[] {
  return warehouses.map((warehouse) => warehouseStockSchema.parse(warehouse));
}
