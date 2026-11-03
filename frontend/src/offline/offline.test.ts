import 'fake-indexeddb/auto';
import { describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/ApiError';
import { isNetworkFailure } from '../api/networkFailure';
import type { PickList } from '../api/waves';
import { ConfirmationQueue, memoryStorage, type QueuedConfirmation } from './ConfirmationQueue';
import { openOfflineDatabase } from './database';
import { applyQueued } from './overlay';
import { ALREADY_CONFIRMED, synchronize } from './synchronize';

const confirmation = { location: 'A-01-01', gtin: '8412345678905', units: 4, lot: 'L-9' };

function list(overrides: Partial<PickList> = {}): PickList {
  return {
    id: 'pl-1',
    waveId: 'wave-1',
    status: 'in_progress',
    operatorId: 'u-1',
    unitsToPick: 6,
    unitsPicked: 0,
    createdAt: '2026-11-03T09:00:00+00:00',
    lines: [
      { position: 1, quantityToPick: 4, quantityPicked: 0, confirmed: false, shortReason: null },
      { position: 2, quantityToPick: 2, quantityPicked: 0, confirmed: false, shortReason: null },
    ],
    ...overrides,
  };
}

function entry(position: number, overrides: Partial<QueuedConfirmation> = {}): QueuedConfirmation {
  return {
    id: `e-${String(position)}`,
    userId: 'u-1',
    listId: 'pl-1',
    position,
    confirmation,
    queuedAt: `2026-11-03T09:0${String(position)}:00.000Z`,
    status: 'pending',
    refusal: null,
    ...overrides,
  };
}

describe('ConfirmationQueue', () => {
  it('keeps what was queued, in order, and tells its listeners', async () => {
    const queue = new ConfirmationQueue();
    await queue.ready;
    const listener = vi.fn();
    queue.subscribe(listener);

    await queue.enqueue({ userId: 'u-1', listId: 'pl-1', position: 1, confirmation });
    await queue.enqueue({ userId: 'u-1', listId: 'pl-1', position: 2, confirmation });

    expect(queue.getSnapshot().map((item) => item.position)).toEqual([1, 2]);
    expect(queue.getSnapshot()[0]).toMatchObject({ status: 'pending', refusal: null });
    expect(listener).toHaveBeenCalledTimes(2);
  });

  it('keeps the first when the same stop is confirmed twice', async () => {
    const queue = new ConfirmationQueue();

    const first = await queue.enqueue({ userId: 'u-1', listId: 'pl-1', position: 1, confirmation });
    const second = await queue.enqueue({
      userId: 'u-1',
      listId: 'pl-1',
      position: 1,
      confirmation: { ...confirmation, units: 1 },
    });

    expect(second).toBe(first);
    expect(queue.getSnapshot()).toHaveLength(1);
  });

  it('marks a refusal and removes what is given up', async () => {
    const queue = new ConfirmationQueue(memoryStorage([entry(1), entry(2)]));
    await queue.ready;

    await queue.reject('e-1', { code: 'outbound.pick_scan_mismatch', detail: 'No coincide' });
    expect(queue.getSnapshot()[0]).toMatchObject({
      status: 'rejected',
      refusal: { code: 'outbound.pick_scan_mismatch' },
    });

    await queue.discard('e-1');
    expect(queue.getSnapshot().map((item) => item.id)).toEqual(['e-2']);
  });

  it('is loaded again from the storage in the next visit', async () => {
    const storage = memoryStorage();
    const before = new ConfirmationQueue(storage);
    await before.enqueue({ userId: 'u-1', listId: 'pl-1', position: 1, confirmation });
    await before.enqueue({ userId: 'u-1', listId: 'pl-1', position: 2, confirmation });
    await before.discard(before.getSnapshot()[0]?.id ?? '');

    const after = new ConfirmationQueue(storage);
    await after.ready;

    expect(after.getSnapshot().map((item) => item.position)).toEqual([2]);
  });
});

describe('the database in IndexedDB', () => {
  it('keeps the queue and the cache between two openings', async () => {
    const first = openOfflineDatabase('test-db');
    await first.queue.save(entry(1));
    await first.queue.save(entry(2));
    await first.queue.remove('e-1');
    await first.cache.setItem('cache', '{"a":1}');

    const second = openOfflineDatabase('test-db');

    expect((await second.queue.load()).map((item) => item.id)).toEqual(['e-2']);
    expect(await second.cache.getItem('cache')).toBe('{"a":1}');
    await second.cache.removeItem('cache');
    expect(await second.cache.getItem('cache')).toBeNull();
  });
});

describe('applyQueued', () => {
  it('shows a stop as confirmed with what was counted, and does not touch the original', () => {
    const original = list();

    const shown = applyQueued(original, [entry(1)]);

    expect(shown.lines[0]).toMatchObject({ confirmed: true, quantityPicked: 4, shortReason: null });
    expect(shown.lines[1]).toMatchObject({ confirmed: false });
    expect(shown.unitsPicked).toBe(4);
    expect(shown.status).toBe('in_progress');
    expect(original.lines[0]).toMatchObject({ confirmed: false });
  });

  it('keeps the reason of a short pick', () => {
    const shown = applyQueued(list(), [
      entry(1, { confirmation: { ...confirmation, units: 3, reason: 'Hueco vacío' } }),
    ]);

    expect(shown.lines[0]).toMatchObject({ quantityPicked: 3, shortReason: 'Hueco vacío' });
  });

  it('completes the list when the last stop is queued', () => {
    const shown = applyQueued(list(), [
      entry(1),
      entry(2, { confirmation: { ...confirmation, units: 2 } }),
    ]);

    expect(shown.status).toBe('completed');
    expect(shown.unitsPicked).toBe(6);
  });

  it('adds nothing for other lists, for refused entries, or for a stop the API already has', () => {
    const original = list();
    const confirmedByApi = list({
      lines: [
        { position: 1, quantityToPick: 4, quantityPicked: 4, confirmed: true, shortReason: null },
        { position: 2, quantityToPick: 2, quantityPicked: 0, confirmed: false, shortReason: null },
      ],
      unitsPicked: 4,
    });

    expect(applyQueued(original, [entry(1, { listId: 'other' })])).toBe(original);
    expect(applyQueued(original, [entry(1, { status: 'rejected' })])).toBe(original);
    expect(applyQueued(confirmedByApi, [entry(1)]).unitsPicked).toBe(4);
  });
});

describe('isNetworkFailure', () => {
  it('is true for a dropped connection and for a proxy that has no API behind it', () => {
    expect(isNetworkFailure(new TypeError('Failed to fetch'))).toBe(true);
    expect(isNetworkFailure(new ApiError(503, 'shared.internal_error', ''))).toBe(true);
  });

  it('is false for an answer of the API that says no', () => {
    expect(isNetworkFailure(new ApiError(422, 'outbound.pick_scan_mismatch', ''))).toBe(false);
    expect(isNetworkFailure(new ApiError(500, 'shared.internal_error', ''))).toBe(false);
    expect(isNetworkFailure(new Error('bug'))).toBe(false);
  });
});

describe('synchronize', () => {
  async function queueOf(...entries: QueuedConfirmation[]) {
    const queue = new ConfirmationQueue(memoryStorage(entries));
    await queue.ready;

    return queue;
  }

  it('sends what is pending in the order it was made and empties the queue', async () => {
    const queue = await queueOf(entry(2), entry(1));
    const sent: number[] = [];

    const result = await synchronize(queue, 'u-1', (item) => {
      sent.push(item.position);

      return Promise.resolve(list());
    });

    expect(sent).toEqual([1, 2]);
    expect(result).toEqual({ sent: 2, refused: 0, stopped: false });
    expect(queue.getSnapshot()).toHaveLength(0);
  });

  it('stops at the first failure of the connection and keeps the rest', async () => {
    const queue = await queueOf(entry(1), entry(2));
    const send = vi.fn(() => Promise.reject(new TypeError('Failed to fetch')));

    const result = await synchronize(queue, 'u-1', send);

    expect(send).toHaveBeenCalledTimes(1);
    expect(result).toEqual({ sent: 0, refused: 0, stopped: true });
    expect(queue.getSnapshot()).toHaveLength(2);
  });

  it('keeps what the API refuses, marked, and goes on with the next stop', async () => {
    const queue = await queueOf(entry(1), entry(2));
    const send = vi.fn((item: QueuedConfirmation) =>
      item.position === 1
        ? Promise.reject(
            new ApiError(422, 'outbound.pick_scan_mismatch', 'El producto no coincide'),
          )
        : Promise.resolve(list()),
    );

    const result = await synchronize(queue, 'u-1', send);

    expect(result).toEqual({ sent: 1, refused: 1, stopped: false });
    expect(queue.getSnapshot()).toHaveLength(1);
    expect(queue.getSnapshot()[0]).toMatchObject({
      id: 'e-1',
      status: 'rejected',
      refusal: { code: 'outbound.pick_scan_mismatch', detail: 'El producto no coincide' },
    });
  });

  it('counts as sent a stop that the API already has, because only the answer was lost', async () => {
    const queue = await queueOf(entry(1));

    const result = await synchronize(queue, 'u-1', () =>
      Promise.reject(new ApiError(409, ALREADY_CONFIRMED, '')),
    );

    expect(result).toEqual({ sent: 1, refused: 0, stopped: false });
    expect(queue.getSnapshot()).toHaveLength(0);
  });

  it('stops when the session has ended, keeping everything for the next sign-in', async () => {
    const queue = await queueOf(entry(1));

    const result = await synchronize(queue, 'u-1', () =>
      Promise.reject(new ApiError(401, 'shared.unauthenticated', '')),
    );

    expect(result.stopped).toBe(true);
    expect(queue.getSnapshot()).toHaveLength(1);
  });

  it('sends only what belongs to the user, and nothing that was already refused', async () => {
    const queue = await queueOf(
      entry(1, { userId: 'someone-else' }),
      entry(2, { status: 'rejected' }),
    );
    const send = vi.fn(() => Promise.resolve(list()));

    await synchronize(queue, 'u-1', send);

    expect(send).not.toHaveBeenCalled();
  });
});
