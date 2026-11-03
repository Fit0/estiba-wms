import { ApiError } from './ApiError';

/**
 * Whether a request failed because the answer could not arrive, and not because the API said no: the
 * browser threw (no connection, a dropped one) or a proxy in front of the API answered that it is
 * down. Trying again later can work; the same request refused with a 4xx never will.
 */
export function isNetworkFailure(error: unknown): boolean {
  if (error instanceof ApiError) {
    return error.status === 502 || error.status === 503 || error.status === 504;
  }

  return error instanceof TypeError;
}
