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
