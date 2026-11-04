import type { Kpis } from '../api/kpis';

/** The figures of a week, as the API sends them (E07-04). */
export const kpis: Kpis = {
  id: 'kpis',
  from: '2026-10-27T10:00:00+00:00',
  until: '2026-11-03T10:00:00+00:00',
  orders: { open: 4, registered: 9, shipped: 7 },
  picking: {
    listsCompleted: 3,
    linesPicked: 45,
    unitsPicked: 1290,
    linesPerHour: 30,
    averageListMinutes: 30.5,
    shortPickLines: 2,
    unitsShort: 10,
    shortPickPercent: 0.8,
  },
  stock: { onHand: 5400, available: 4200, quarantine: 300, damaged: 20, onHold: 30 },
  locations: { storage: 4, occupied: 3, occupancyPercent: 75 },
};

/** A week with no pick list completed and no storage location: the API leaves the ratios out. */
export const noPicking: Kpis = {
  ...kpis,
  picking: { listsCompleted: 0, linesPicked: 0, unitsPicked: 0, shortPickLines: 0, unitsShort: 0 },
  locations: { storage: 0, occupied: 0 },
};
