import { act, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { json } from '../testing/fakeApi';
import { kpis } from '../testing/kpiFixtures';
import { toast } from '../components/ui/Toaster';
import { http, server } from '../testing/msw';
import { apiOf, open, withSession } from '../testing/renderApp';
import { parseNotification, renewDelay, retryDelay } from './messages';

/** The EventSource of the browser, which jsdom does not have: it remembers who opened it. */
class FakeEventSource {
  static readonly CONNECTING = 0;
  static readonly OPEN = 1;
  static readonly CLOSED = 2;
  static instances: FakeEventSource[] = [];

  readyState: number = FakeEventSource.CONNECTING;
  onopen: (() => void) | null = null;
  onmessage: ((event: MessageEvent<string>) => void) | null = null;
  onerror: (() => void) | null = null;
  closed = false;

  constructor(
    readonly url: string,
    readonly init?: EventSourceInit,
  ) {
    FakeEventSource.instances.push(this);
  }

  close(): void {
    this.closed = true;
    this.readyState = FakeEventSource.CLOSED;
  }

  emit(data: unknown, id = ''): void {
    this.onmessage?.(new MessageEvent('message', { data: JSON.stringify(data), lastEventId: id }));
  }
}

const origin = () => globalThis.location.origin;
const access = {
  topic: '/tenants/t-1/notifications',
  hub: '/.well-known/mercure',
  expiresAt: new Date(Date.now() + 3_600_000).toISOString(),
};
const completed = {
  type: 'outbound.pick_list_completed',
  data: { pickListId: 'pl-1', waveId: 'w-1' },
};
const short = {
  type: 'outbound.short_pick_reported',
  data: { pickListId: 'pl-1', position: 2, orderId: 'o-1', missingUnits: 3 },
};

let asked: string[] = [];
let kpiRequests = 0;

function serve() {
  asked = [];
  kpiRequests = 0;
  server.use(
    http.get(`${origin()}/api/notifications/access`, () => {
      asked.push('access');

      return json(access);
    }),
    http.get(`${origin()}/api/kpis`, () => {
      kpiRequests += 1;

      return json(kpis);
    }),
  );
}

async function connected(): Promise<FakeEventSource> {
  await waitFor(() => {
    expect(FakeEventSource.instances).toHaveLength(1);
  });
  const [source] = FakeEventSource.instances;
  if (source === undefined) {
    throw new Error('No stream was opened.');
  }

  return source;
}

beforeEach(() => {
  FakeEventSource.instances = [];
  vi.stubGlobal('EventSource', FakeEventSource);
});

afterEach(() => {
  vi.unstubAllGlobals();
  // The notices live in a store of their own that outlasts a test.
  toast.dismiss();
});

const shown = (): number => document.querySelectorAll('[data-sonner-toast]').length;

describe('the live notifications', () => {
  it('opens the stream of the tenant with the cookie of the hub', async () => {
    open('/', apiOf(['supervisor'], withSession(['supervisor'])));
    serve();

    const source = await connected();

    expect(source.url).toBe(
      `/.well-known/mercure?topic=${encodeURIComponent('/tenants/t-1/notifications')}`,
    );
    expect(source.init).toEqual({ withCredentials: true });
  });

  it('shows a pick list completed, and refreshes the figures', async () => {
    open('/', apiOf(['supervisor'], withSession(['supervisor'])));
    serve();
    const source = await connected();
    await screen.findByText('Pedidos abiertos');
    await waitFor(() => {
      expect(kpiRequests).toBeGreaterThan(0);
    });
    const before = kpiRequests;

    act(() => {
      source.emit(completed, 'pick-list-completed:pl-1');
    });

    expect((await screen.findAllByText('Hoja de picking completada')).length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: 'Ver hoja' })).toBeInTheDocument();
    await waitFor(() => {
      expect(kpiRequests).toBeGreaterThan(before);
    });
  });

  it('tells how many units were not found', async () => {
    open('/', apiOf(['supervisor'], withSession(['supervisor'])));
    serve();
    const source = await connected();

    act(() => {
      source.emit(short, 'short-pick:pl-1:2');
    });

    expect(
      (await screen.findAllByText('Faltan 3 unidades en una línea de picking')).length,
    ).toBeGreaterThan(0);
  });

  it('shows once a notification that arrives twice', async () => {
    open('/', apiOf(['supervisor'], withSession(['supervisor'])));
    serve();
    const source = await connected();

    act(() => {
      source.emit(completed, 'pick-list-completed:pl-1');
      source.emit(completed, 'pick-list-completed:pl-1');
    });

    await waitFor(() => {
      expect(shown()).toBe(1);
    });
    act(() => {
      source.emit(
        { ...completed, data: { pickListId: 'pl-2', waveId: 'w-1' } },
        'pick-list-completed:pl-2',
      );
    });
    await waitFor(() => {
      expect(shown()).toBe(2);
    });
  });

  it('ignores what it does not understand', async () => {
    open('/', apiOf(['supervisor'], withSession(['supervisor'])));
    serve();
    const source = await connected();

    act(() => {
      source.emit({ type: 'inbound.something_new', data: { pickListId: 'x' } }, 'n-1');
      source.onmessage?.(new MessageEvent('message', { data: 'not json', lastEventId: 'n-2' }));
    });
    act(() => {
      source.emit(completed, 'n-3');
    });

    await waitFor(() => {
      expect(shown()).toBe(1);
    });
    expect(screen.getAllByText('Hoja de picking completada').length).toBeGreaterThan(0);
  });

  it('closes the stream when the person leaves', async () => {
    const { client } = open('/', apiOf(['supervisor'], withSession(['supervisor'])));
    serve();
    const source = await connected();

    await act(async () => {
      await client.signOut();
    });

    expect(source.closed).toBe(true);
  });

  it('does not listen for an operator, who is not allowed to', async () => {
    open('/', apiOf(['operator'], withSession(['operator'])));
    serve();

    expect(await screen.findByText('Bienvenido a Marea Moda.')).toBeInTheDocument();

    expect(asked).toHaveLength(0);
    expect(FakeEventSource.instances).toHaveLength(0);
  });
});

describe('the messages of the hub', () => {
  it('reads the notifications the API announces', () => {
    expect(parseNotification(JSON.stringify(completed))).toEqual({
      type: 'pick_list_completed',
      pickListId: 'pl-1',
    });
    expect(parseNotification(JSON.stringify(short))).toEqual({
      type: 'short_pick_reported',
      pickListId: 'pl-1',
      missingUnits: 3,
    });
  });

  it('ignores the rest', () => {
    expect(parseNotification('not json')).toBeNull();
    expect(parseNotification('[]')).toBeNull();
    expect(parseNotification(JSON.stringify({ type: 'outbound.pick_list_completed' }))).toBeNull();
    expect(
      parseNotification(
        JSON.stringify({ type: 'outbound.short_pick_reported', data: { pickListId: 'p' } }),
      ),
    ).toBeNull();
    expect(
      parseNotification(JSON.stringify({ type: 'other', data: { pickListId: 'p' } })),
    ).toBeNull();
  });

  it('waits longer after every failure, up to a minute', () => {
    expect([1, 2, 3, 4, 5, 6].map(retryDelay)).toEqual([4000, 8000, 16000, 32000, 60000, 60000]);
  });

  it('renews access five minutes before it ends, and never in a loop', () => {
    const now = Date.parse('2026-11-04T10:00:00Z');

    expect(renewDelay('2026-11-04T11:00:00Z', now)).toBe(55 * 60_000);
    expect(renewDelay('2026-11-04T10:03:00Z', now)).toBe(60_000);
    expect(renewDelay('2020-01-01T00:00:00Z', now)).toBe(60_000);
  });
});
