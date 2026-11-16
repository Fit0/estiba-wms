import { z } from 'zod';

/**
 * The types of event the audit trail can hold, as the backend names them (`context.event_name`, see
 * `EventDescriber`). Each has a text in `audit.event.<type>`; one that is not here (an event added
 * after this list) is shown with its own name, so nothing is hidden.
 */
export const eventTypes = [
  'catalog.gtin_assigned',
  'catalog.packaging_level_defined',
  'catalog.product_blocked',
  'catalog.product_classified',
  'catalog.product_discontinued',
  'catalog.product_registered',
  'catalog.product_unblocked',
  'identity.tenant_registered',
  'identity.user_disabled',
  'identity.user_enabled',
  'identity.user_registered',
  'inbound.asn_cancelled',
  'inbound.asn_closed',
  'inbound.asn_registered',
  'inbound.discrepancy_detected',
  'inbound.item_received',
  'inbound.over_receipt_approved',
  'inbound.putaway_confirmed',
  'inbound.putaway_task_planned',
  'inbound.putaway_task_split',
  'inbound.receipt_closed',
  'inbound.receipt_opened',
  'inventory.stock_movement_recorded',
  'outbound.order_allocated',
  'outbound.order_cancelled',
  'outbound.order_picked',
  'outbound.order_registered',
  'outbound.order_shipped',
  'outbound.packing_completed',
  'outbound.packing_started',
  'outbound.parcel_closed',
  'outbound.pick_line_confirmed',
  'outbound.pick_list_completed',
  'outbound.pick_list_generated',
  'outbound.pick_list_started',
  'outbound.shipment_dispatched',
  'outbound.shipment_left',
  'outbound.short_pick_reported',
  'outbound.wave_cancelled',
  'outbound.wave_completed',
  'outbound.wave_created',
  'outbound.wave_released',
  'warehouse.location_blocked',
  'warehouse.location_created',
  'warehouse.location_unblocked',
  'warehouse.warehouse_created',
  'warehouse.zone_defined',
  'warehouse.zone_families_assigned',
] as const;

export type EventType = (typeof eventTypes)[number];

/** The known type a text names, if it names one. */
export function knownEventType(value: string | null | undefined): EventType | undefined {
  return eventTypes.find((type) => type === value);
}

/** What an event carried, written so a person can read it: the JSON of its fields, indented. */
export function describeData(data: unknown): string {
  return JSON.stringify(data, null, 2);
}

/** The start of an identifier: enough to tell two apart on a screen, with the whole one in the data. */
export function shortId(id: string): string {
  return id.slice(0, 8);
}

/**
 * A moment typed in a `datetime-local` field (local time, no offset) as the API wants it: ISO 8601
 * with an offset and no fractions. Text that is not a moment is left out, so a half-typed field does
 * not turn into a request that fails.
 */
export function momentForApi(value: string | null): string | undefined {
  if (value === null || value === '') {
    return undefined;
  }
  const date = new Date(value);

  return Number.isNaN(date.getTime())
    ? undefined
    : date.toISOString().replace(/\.\d{3}Z$/, '+00:00');
}

export const subjectKinds = [
  'order',
  'wave',
  'pick_list',
  'shipment',
  'product',
  'warehouse',
  'location',
  'asn',
  'receipt',
  'putaway_task',
  'movement',
  'user',
  'tenant',
] as const;

export type SubjectKind = (typeof subjectKinds)[number];

/**
 * What kind of object an event is about. The subject of an event is its aggregate, and the name of the
 * event starts with where it lives, so the kind comes from the name and works for an event that is not in
 * {@link eventTypes} yet.
 */
export function subjectKind(eventType: string): SubjectKind | undefined {
  const rules: readonly [string, SubjectKind][] = [
    ['catalog.', 'product'],
    ['identity.tenant_', 'tenant'],
    ['identity.', 'user'],
    ['inbound.asn_', 'asn'],
    ['inbound.putaway_', 'putaway_task'],
    ['inbound.', 'receipt'],
    ['inventory.', 'movement'],
    ['outbound.order_', 'order'],
    ['outbound.wave_', 'wave'],
    ['outbound.pick_', 'pick_list'],
    ['outbound.short_pick_', 'pick_list'],
    ['outbound.', 'shipment'],
    ['warehouse.location_', 'location'],
    ['warehouse.', 'warehouse'],
  ];

  return rules.find(([prefix]) => eventType.startsWith(prefix))?.[1];
}

/** The screen of an object, for the kinds that have one. */
const subjectPaths: Partial<Record<SubjectKind, string>> = {
  order: '/orders',
  wave: '/waves',
  pick_list: '/picking',
  product: '/products',
  warehouse: '/warehouses',
  asn: '/inbound',
  receipt: '/receipts',
};

/** The field of the event that names the object the way people do, by kind of object. */
const subjectLabelFields: Partial<Record<SubjectKind, readonly string[]>> = {
  order: ['reference', 'orderReference'],
  shipment: ['orderReference'],
  product: ['sku'],
  location: ['code'],
  asn: ['reference'],
};

const dataSchema = z.record(z.string(), z.unknown());

function textField(data: unknown, name: string): string | undefined {
  const fields = dataSchema.safeParse(data);
  const value = fields.success ? fields.data[name] : undefined;

  return typeof value === 'string' && value !== '' ? value : undefined;
}

export interface Subject {
  kind: SubjectKind | undefined;
  /** How the object is called: its reference or code when the event carried one, else the start of its id. */
  label: string;
  /** The id to filter the log by, or null when the event is not really about this object. */
  id: string | null;
  /** The screen of the object, if there is one. */
  path: string | null;
}

/**
 * What an entry is about, in words. An event that carries the reference of its object (an order that was
 * registered, a location that was blocked) says it; the rest show the kind and the start of the id. An
 * event that only carries the tenant to say whose it is, such as a shipment that left, is not about an
 * object that can be searched, so it has no id.
 */
export function describeSubject(entry: {
  eventType: string;
  subjectId?: string | null | undefined;
  data: unknown;
}): Subject | undefined {
  const kind = subjectKind(entry.eventType);
  const subjectId = entry.subjectId ?? null;
  const id =
    subjectId === textField(entry.data, 'tenantId') && kind !== 'tenant' ? null : subjectId;
  const fields = kind === undefined ? undefined : subjectLabelFields[kind];
  const named = (fields ?? [])
    .map((field) => textField(entry.data, field))
    .find((value) => value !== undefined);
  const label =
    named ??
    (entry.eventType === 'warehouse.warehouse_created' ? textField(entry.data, 'code') : undefined);
  const text = label ?? (id === null ? undefined : shortId(id));
  if (text === undefined) {
    return undefined;
  }
  const base = kind === undefined ? undefined : subjectPaths[kind];

  return {
    kind,
    label: text,
    id,
    path: base === undefined || id === null ? null : `${base}/${id}`,
  };
}
