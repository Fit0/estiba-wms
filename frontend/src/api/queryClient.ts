import { QueryClient } from '@tanstack/react-query';
import { ApiError } from './ApiError';

/** Another try cannot change a "no" that depends on the request or on who asks. */
function shouldRetry(failures: number, error: unknown): boolean {
  if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
    return false;
  }

  return failures < 2;
}

/**
 * The cache of server data. A list is fresh for 30 seconds, so going back to a screen does not ask
 * again, and a 4xx is never retried. The provider empties it when the session ends.
 */
export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { staleTime: 30_000, retry: shouldRetry, refetchOnWindowFocus: false },
    },
  });
}
