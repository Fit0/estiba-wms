import { QueryCache, QueryClient } from '@tanstack/react-query';
import { ApiError } from './ApiError';

/** Another try cannot change a "no" that depends on the request or on who asks. */
function shouldRetry(failures: number, error: unknown): boolean {
  if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
    return false;
  }

  return failures < 2;
}

/** Which queries are kept on the device: the pick lists and the products (their names show on the stops). */
export const persistedRoots: ReadonlySet<string> = new Set(['picking', 'products']);

/** How long what is kept on the device is good for: a shift, with room to spare. */
export const persistedMaxAge = 24 * 60 * 60 * 1000;

/**
 * The cache of server data. A list is fresh for 30 seconds, so going back to a screen does not ask
 * again, and a 4xx is never retried. The provider empties it when the session ends.
 */
export function createQueryClient({
  persisted = false,
}: { persisted?: boolean } = {}): QueryClient {
  return new QueryClient({
    // While developing, a failed request leaves its error in the console, with its key.
    queryCache: new QueryCache({
      onError: (error, query) => {
        if (import.meta.env.DEV) {
          console.error('Query failed', query.queryKey, error);
        }
      },
    }),
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: shouldRetry,
        refetchOnWindowFocus: false,
        // What is kept on the device must not be thrown away from memory before it can be saved.
        ...(persisted ? { gcTime: persistedMaxAge } : {}),
      },
    },
  });
}
