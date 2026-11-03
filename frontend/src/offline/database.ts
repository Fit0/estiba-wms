import { openDB } from 'idb';
import type { QueueStorage, QueuedConfirmation } from './ConfirmationQueue';

const QUEUE = 'confirmations';
const CACHE = 'cache';

/** Text by key, as the query persister asks for it. */
export interface KeyValueStorage {
  getItem(key: string): Promise<string | null>;
  setItem(key: string, value: string): Promise<void>;
  removeItem(key: string): Promise<void>;
}

/** What the app keeps in IndexedDB: the confirmations waiting to be sent, and the cache of server data. */
export interface OfflineDatabase {
  readonly queue: QueueStorage;
  readonly cache: KeyValueStorage;
}

/**
 * Opens the database of the app. Two stores: the queue, one entry per confirmation, and a key-value
 * store for the cache of server data, which the query persister writes as one text.
 */
export function openOfflineDatabase(name = 'estiba-offline'): OfflineDatabase {
  const database = openDB(name, 1, {
    upgrade(db) {
      db.createObjectStore(QUEUE, { keyPath: 'id' });
      db.createObjectStore(CACHE);
    },
  });

  return {
    queue: {
      load: async () => (await database).getAll(QUEUE) as Promise<QueuedConfirmation[]>,
      save: async (entry) => {
        await (await database).put(QUEUE, entry);
      },
      remove: async (id) => {
        await (await database).delete(QUEUE, id);
      },
    },
    cache: {
      getItem: async (key) =>
        ((await (await database).get(CACHE, key)) as string | undefined) ?? null,
      setItem: async (key, value) => {
        await (await database).put(CACHE, value, key);
      },
      removeItem: async (key) => {
        await (await database).delete(CACHE, key);
      },
    },
  };
}

/** Whether this browser has IndexedDB; a private window of some browsers does not. */
export function canUseDatabase(): boolean {
  return typeof indexedDB === 'object';
}
