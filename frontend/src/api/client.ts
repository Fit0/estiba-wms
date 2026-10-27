import createClient, { type Client } from 'openapi-fetch';
import type { AuthClient } from '../auth/AuthClient';
import type { Language } from '../i18n';
import type { components, paths } from './schema';

export type ApiClient = Client<paths>;

/**
 * A schema of the API as a screen reads it. The OpenAPI document marks as required the fields that
 * an answer always has, and leaves optional the ones that may be missing because they are null.
 */
export type Schema<Name extends keyof components['schemas']> = components['schemas'][Name];

/**
 * Sends a request of the typed client through the session: with the access token, renewed once when
 * the API says it has expired. The client hands over a `Request`, and the session takes a path and
 * the options of `fetch`, so the request is taken apart here. The address is a path because the
 * browser talks to one origin only (ADR 0021).
 */
async function throughSession(session: AuthClient, request: Request): Promise<Response> {
  const url = new URL(request.url);
  const body = request.body === null ? undefined : await request.text();

  return session.authorizedFetch(`${url.pathname}${url.search}`, {
    method: request.method,
    headers: request.headers,
    ...(body === undefined ? {} : { body }),
    signal: request.signal,
  });
}

/**
 * The typed client of the API. Paths, parameters and answers come from `schema.d.ts`, which is
 * generated from the OpenAPI document (`npm run api:types`), so a call to a path that does not exist
 * or with a wrong parameter does not compile.
 */
export function createApiClient(session: AuthClient, language: Language): ApiClient {
  return createClient<paths>({
    baseUrl: globalThis.location.origin,
    headers: { 'Accept-Language': language },
    fetch: (request) => throughSession(session, request),
  });
}
