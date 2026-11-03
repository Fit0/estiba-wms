import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { QueryClientProvider } from '@tanstack/react-query';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { createContext, use, useEffect, useState, type ReactNode } from 'react';
import type { AuthClient } from '../auth/AuthClient';
import { i18n, isLanguage } from '../i18n';
import type { KeyValueStorage } from '../offline/database';
import { createApiClient, type ApiClient } from './client';
import { createQueryClient, persistedRoots, persistedMaxAge } from './queryClient';

const ApiContext = createContext<ApiClient | null>(null);

const CACHE_KEY = 'estiba-query-cache';

/**
 * Gives the app the typed client and the cache of server data. When the session ends, the cache is
 * emptied: what one user loaded must not be on the screen of the next one who signs in.
 *
 * With a `cache` storage the pick lists and the products they mention are also kept on the device, so
 * that the picking screens open without a connection (ADR 0042).
 */
export function ApiProvider({
  session,
  cache,
  children,
}: {
  session: AuthClient;
  cache?: KeyValueStorage | undefined;
  children: ReactNode;
}) {
  const [api] = useState(() =>
    createApiClient(session, () => (isLanguage(i18n.language) ? i18n.language : 'es')),
  );
  const [queries] = useState(() => createQueryClient({ persisted: cache !== undefined }));
  const [persister] = useState(() =>
    cache === undefined
      ? undefined
      : createAsyncStoragePersister({ storage: cache, key: CACHE_KEY, throttleTime: 1000 }),
  );

  useEffect(
    () =>
      session.subscribe(() => {
        if (session.getState().status === 'signedOut') {
          queries.clear();
          void cache?.removeItem(CACHE_KEY);
        }
      }),
    [session, queries, cache],
  );

  return (
    <ApiContext value={api}>
      {persister === undefined ? (
        <QueryClientProvider client={queries}>{children}</QueryClientProvider>
      ) : (
        <PersistQueryClientProvider
          client={queries}
          persistOptions={{
            persister,
            maxAge: persistedMaxAge,
            // A new shape of the cached data bumps this and the old cache is dropped.
            buster: 'v1',
            dehydrateOptions: {
              shouldDehydrateQuery: (query) =>
                query.state.status === 'success' && persistedRoots.has(String(query.queryKey[0])),
            },
          }}
        >
          {children}
        </PersistQueryClientProvider>
      )}
    </ApiContext>
  );
}

export function useApi(): ApiClient {
  const api = use(ApiContext);
  if (api === null) {
    throw new Error('useApi needs an ApiProvider above it.');
  }

  return api;
}
