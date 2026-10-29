/** The deliveries, receipts and products the tests of the receiving screens share. */
export const product = {
  id: 'p-1',
  sku: 'CAM-LIN',
  name: 'Camisa de lino',
  baseUnit: 'each',
  storageClass: 'ambient',
  status: 'active',
  lengthMm: 300,
  widthMm: 220,
  heightMm: 30,
  weightGrams: 250,
  trackedByLot: false,
  trackedByExpiry: false,
  turnoverClass: 'B',
  gtins: ['8412345678905'],
  packagingLevels: [],
};

export const asn = (overrides: Record<string, unknown> = {}) => ({
  id: 'a-1',
  reference: 'ASN-2026-0042',
  supplier: 'Textiles del Ebro',
  expectedDate: '2026-10-28T08:00:00+00:00',
  status: 'expected',
  openReceiptId: null,
  lines: [{ productId: 'p-1', expectedUnits: 240, receivedUnits: 0, lot: null, expiryDate: null }],
  ...overrides,
});

export const receipt = (overrides: Record<string, unknown> = {}) => ({
  id: 'r-1',
  asnId: 'a-1',
  dockLocationId: 'l-D-01',
  status: 'open',
  overReceivedUnits: 0,
  overReceiptApproved: false,
  openedAt: '2026-10-28T08:15:00+00:00',
  closedAt: null,
  stockBooked: false,
  expected: [{ productId: 'p-1', units: 240 }],
  lines: [],
  discrepancies: [],
  ...overrides,
});

export const warehouse = { id: 'w-1', code: 'ZGZ1', name: 'Zaragoza', zones: [] };

export const location = (code: string, type: string) => ({
  id: `l-${code}`,
  warehouseId: 'w-1',
  zoneId: 'z-a',
  code,
  type,
  pickSequence: 10,
  status: 'active',
});

export const stockLine = (
  id: string,
  locationCode: string,
  overrides: Record<string, unknown> = {},
) => ({
  id,
  productId: 'p-1',
  sku: 'CAM-LIN',
  productName: 'Camisa de lino',
  warehouseId: 'w-1',
  warehouseCode: 'ZGZ1',
  locationId: `l-${locationCode}`,
  locationCode,
  lot: null,
  expiryDate: null,
  status: 'available',
  onHand: 100,
  ...overrides,
});

export const movement = (id: string, overrides: Record<string, unknown> = {}) => ({
  id,
  stockItemId: 's-1',
  productId: 'p-1',
  sku: 'CAM-LIN',
  locationId: 'l-A-01-01',
  locationCode: 'A-01-01',
  lot: null,
  expiryDate: null,
  status: 'available',
  type: 'receipt',
  change: 100,
  balanceAfter: 100,
  reference: 'ASN-2026-0042',
  reason: null,
  authorId: 'u-1',
  occurredAt: '2026-10-28T09:00:00+00:00',
  ...overrides,
});
