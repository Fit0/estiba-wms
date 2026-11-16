import { describe, expect, it } from 'vitest';
import {
  describeData,
  describeSubject,
  knownEventType,
  momentForApi,
  shortId,
  subjectKind,
} from './display';

describe('the audit log display', () => {
  it('knows the types of event it has a text for, and no others', () => {
    expect(knownEventType('outbound.order_registered')).toBe('outbound.order_registered');
    expect(knownEventType('billing.invoice_issued')).toBeUndefined();
    expect(knownEventType(null)).toBeUndefined();
  });

  it('writes what an event carried as indented JSON', () => {
    expect(describeData({ reference: 'PED-0001' })).toBe('{\n  "reference": "PED-0001"\n}');
  });

  it('keeps the start of an identifier', () => {
    expect(shortId('0192f2b4-7c3e-7a18-8d5b-3c1f0a8e6b28')).toBe('0192f2b4');
  });

  it('turns a typed moment into ISO 8601 with an offset and no fractions', () => {
    expect(momentForApi('2026-11-10T10:30')).toMatch(/^2026-11-10T\d{2}:\d{2}:00\+00:00$/);
  });

  it('leaves out a moment that is empty or not a date', () => {
    expect(momentForApi('')).toBeUndefined();
    expect(momentForApi(null)).toBeUndefined();
    expect(momentForApi('not a date')).toBeUndefined();
  });

  it('tells what kind of object an event is about from where the event lives', () => {
    expect(subjectKind('outbound.order_shipped')).toBe('order');
    expect(subjectKind('outbound.short_pick_reported')).toBe('pick_list');
    expect(subjectKind('outbound.parcel_closed')).toBe('shipment');
    expect(subjectKind('inbound.putaway_task_split')).toBe('putaway_task');
    expect(subjectKind('inbound.item_received')).toBe('receipt');
    expect(subjectKind('identity.tenant_registered')).toBe('tenant');
    expect(subjectKind('warehouse.zone_defined')).toBe('warehouse');
    expect(subjectKind('billing.invoice_issued')).toBeUndefined();
  });

  it('names an object by the reference its event carries, or else by the start of its id', () => {
    const id = '5d3c1f0a-7c3e-7a14-8d5b-3c1f0a8e6b2b';

    expect(
      describeSubject({
        eventType: 'outbound.order_registered',
        subjectId: id,
        data: { reference: 'PED-1' },
      }),
    ).toEqual({ kind: 'order', label: 'PED-1', id, path: `/orders/${id}` });
    expect(
      describeSubject({ eventType: 'outbound.order_allocated', subjectId: id, data: {} }),
    ).toEqual({ kind: 'order', label: '5d3c1f0a', id, path: `/orders/${id}` });
    expect(
      describeSubject({
        eventType: 'warehouse.location_blocked',
        subjectId: id,
        data: { code: 'A-03-02' },
      }),
    ).toEqual({ kind: 'location', label: 'A-03-02', id, path: null });
  });

  it('does not take the code of a zone for the one of its warehouse', () => {
    const id = '5d3c1f0a-7c3e-7a14-8d5b-3c1f0a8e6b2b';

    expect(
      describeSubject({ eventType: 'warehouse.zone_defined', subjectId: id, data: { code: 'A' } })
        ?.label,
    ).toBe('5d3c1f0a');
    expect(
      describeSubject({
        eventType: 'warehouse.warehouse_created',
        subjectId: id,
        data: { code: 'ZGZ1' },
      })?.label,
    ).toBe('ZGZ1');
  });

  it('has no object to search for when the event only carries the tenant', () => {
    const tenant = '0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2e';

    expect(
      describeSubject({
        eventType: 'outbound.shipment_left',
        subjectId: tenant,
        data: { tenantId: tenant, orderReference: 'PED-1' },
      }),
    ).toEqual({ kind: 'shipment', label: 'PED-1', id: null, path: null });
  });

  it('says nothing about an event with no subject', () => {
    expect(describeSubject({ eventType: 'billing.x', subjectId: null, data: {} })).toBeUndefined();
  });
});
