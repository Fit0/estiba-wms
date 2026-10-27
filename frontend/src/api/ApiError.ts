/**
 * An answer from the API that says no. It carries what the screen needs and nothing it should parse:
 * `code` is the stable identifier of the problem (ADR 0007), which the screen turns into a text of its
 * own language, and `detail` is the sentence the API wrote in the language it was asked for.
 */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    readonly detail: string,
    readonly parameters: Readonly<Record<string, unknown>> = {},
  ) {
    super(detail === '' ? code : detail);
    this.name = 'ApiError';
  }
}

/** The code of an answer that is not a problem document: a proxy page, an empty body, a timeout. */
export const UNEXPECTED_CODE = 'shared.internal_error';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

/** Turns the body of a failed answer (RFC 9457) into an `ApiError`, whatever the body is. */
export function toApiError(status: number, body: unknown): ApiError {
  if (!isRecord(body)) {
    return new ApiError(status, UNEXPECTED_CODE, '');
  }

  return new ApiError(
    status,
    typeof body.code === 'string' ? body.code : UNEXPECTED_CODE,
    typeof body.detail === 'string' ? body.detail : '',
    isRecord(body.parameters) ? body.parameters : {},
  );
}

/**
 * The data of a call of the typed client, or an `ApiError`. TanStack Query needs a function that
 * throws to know that a request failed, and the client returns `{ data, error }` instead.
 */
export function unwrap<Data>(result: { data?: Data; error?: unknown; response: Response }): Data {
  if (result.error !== undefined || !result.response.ok) {
    throw toApiError(result.response.status, result.error);
  }

  if (result.data === undefined) {
    throw new ApiError(result.response.status, UNEXPECTED_CODE, '');
  }

  return result.data;
}

export interface Page<Item> {
  readonly items: readonly Item[];
  /** How many items match in all the pages, from the `X-Total-Count` header. */
  readonly total: number;
}

/**
 * The items of a paged list and how many there are in all. An answer without the header (a list
 * that is not paged) has as many as it shows.
 */
export function unwrapPage<Item>(result: {
  data?: Item[];
  error?: unknown;
  response: Response;
}): Page<Item> {
  const items = unwrap(result);
  const header = result.response.headers.get('X-Total-Count');
  const total = header === null ? Number.NaN : Number(header);

  return { items, total: Number.isInteger(total) && total >= 0 ? total : items.length };
}
