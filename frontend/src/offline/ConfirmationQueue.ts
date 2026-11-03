import type { PickConfirmation } from '../api/picking';

/** What the API said when it refused a confirmation that had waited in the queue. */
export interface Refusal {
  readonly code: string;
  readonly detail: string;
}

/**
 * A stop of a pick list that the operator confirmed and that has not reached the API yet. It keeps the
 * user, because the confirmation belongs to whoever made it and is sent only when they are signed in.
 */
export interface QueuedConfirmation {
  readonly id: string;
  readonly userId: string;
  readonly listId: string;
  readonly position: number;
  readonly confirmation: PickConfirmation;
  readonly queuedAt: string;
  /** Pending until it is sent. Rejected when the API refused it: it stays to be seen, then discarded. */
  readonly status: 'pending' | 'rejected';
  readonly refusal: Refusal | null;
}

/** Where the queue keeps its entries between visits. */
export interface QueueStorage {
  load(): Promise<QueuedConfirmation[]>;
  save(entry: QueuedConfirmation): Promise<void>;
  remove(id: string): Promise<void>;
}

/** A queue that forgets everything when the page closes: for tests and for a browser without IndexedDB. */
export function memoryStorage(initial: readonly QueuedConfirmation[] = []): QueueStorage {
  const saved = new Map(initial.map((entry) => [entry.id, entry]));

  return {
    load: () => Promise.resolve([...saved.values()]),
    save: (entry) => {
      saved.set(entry.id, entry);

      return Promise.resolve();
    },
    remove: (id) => {
      saved.delete(id);

      return Promise.resolve();
    },
  };
}

let counter = 0;

function newId(): string {
  counter += 1;

  return typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : `${String(Date.now())}-${String(counter)}`;
}

/**
 * The confirmations made without a connection, waiting to be sent in the order they were made. It is an
 * external store: React reads it with `useSyncExternalStore`, and every change is written to the storage
 * so that closing the app does not lose it.
 */
export class ConfirmationQueue {
  private entries: readonly QueuedConfirmation[] = [];

  private readonly listeners = new Set<() => void>();

  /** Resolves when what was saved in an earlier visit is loaded. */
  readonly ready: Promise<void>;

  constructor(private readonly storage: QueueStorage = memoryStorage()) {
    this.ready = storage.load().then((saved) => {
      // What was queued before the load finished stays, after what was saved.
      const known = new Set(this.entries.map((entry) => entry.id));
      this.entries = [...saved.filter((entry) => !known.has(entry.id)), ...this.entries].sort(
        (a, b) => a.queuedAt.localeCompare(b.queuedAt),
      );
      this.notify();
    });
  }

  subscribe = (listener: () => void): (() => void) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  getSnapshot = (): readonly QueuedConfirmation[] => this.entries;

  /** Adds a confirmation at the end. Confirming the same stop twice keeps the first. */
  async enqueue(input: {
    userId: string;
    listId: string;
    position: number;
    confirmation: PickConfirmation;
  }): Promise<QueuedConfirmation> {
    await this.ready;
    const existing = this.entries.find(
      (entry) =>
        entry.status === 'pending' &&
        entry.listId === input.listId &&
        entry.position === input.position,
    );
    if (existing !== undefined) {
      return existing;
    }
    const entry: QueuedConfirmation = {
      id: newId(),
      ...input,
      queuedAt: new Date().toISOString(),
      status: 'pending',
      refusal: null,
    };
    await this.storage.save(entry);
    this.entries = [...this.entries, entry];
    this.notify();

    return entry;
  }

  /** The API refused it: it stays, marked, so that the operator sees what happened. */
  async reject(id: string, refusal: Refusal): Promise<void> {
    const entry = this.entries.find((item) => item.id === id);
    if (entry === undefined) {
      return;
    }
    const rejected: QueuedConfirmation = { ...entry, status: 'rejected', refusal };
    await this.storage.save(rejected);
    this.entries = this.entries.map((item) => (item.id === id ? rejected : item));
    this.notify();
  }

  /** Removes it: it was sent, or the operator gave it up. */
  async discard(id: string): Promise<void> {
    await this.storage.remove(id);
    this.entries = this.entries.filter((entry) => entry.id !== id);
    this.notify();
  }

  private notify(): void {
    for (const listener of this.listeners) {
      listener();
    }
  }
}
