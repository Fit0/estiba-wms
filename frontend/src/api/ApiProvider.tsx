import { QueryClientProvider } from '@tanstack/react-query';
import { createContext, use, useEffect, useState, type ReactNode } from 'react';
import type { AuthClient } from '../auth/AuthClient';
import { detectLanguage } from '../i18n';
import { createApiClient, type ApiClient } from './client';
import { createQueryClient } from './queryClient';

const ApiContext = createContext<ApiClient | null>(null);

/**
 * Gives the app the typed client and the cache of server data. When the session ends, the cache is
 * emptied: what one user loaded must not be on the screen of the next one who signs in.
 */
export function ApiProvider({ session, children }: { session: AuthClient; children: ReactNode }) {
  const [api] = useState(() => createApiClient(session, detectLanguage(navigator.languages)));
  const [queries] = useState(createQueryClient);

  useEffect(
    () =>
      session.subscribe(() => {
        if (session.getState().status === 'signedOut') {
          queries.clear();
        }
      }),
    [session, queries],
  );

  return (
    <ApiContext value={api}>
      <QueryClientProvider client={queries}>{children}</QueryClientProvider>
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
