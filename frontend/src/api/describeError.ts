import { ApiError } from './ApiError';

/**
 * A short line that says what went wrong with a request, for the person who has to report it: the
 * status and the stable code of an answer of the API, or the name and message of anything else (a
 * network failure, a bug). It is not shown instead of the friendly message but after it.
 */
export function describeError(error: unknown): string {
  if (error instanceof ApiError) {
    return error.detail === '' || error.detail === error.code
      ? `${String(error.status)} ${error.code}`
      : `${String(error.status)} ${error.code}: ${error.detail}`;
  }

  if (error instanceof Error) {
    return `${error.name}: ${error.message}`;
  }

  return String(error);
}
