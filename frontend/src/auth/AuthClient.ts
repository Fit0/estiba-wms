import { isRole, type Role } from './roles';

export interface Tenant {
  readonly id: string;
  readonly name: string;
  readonly defaultLanguage: string;
}

export interface Me {
  readonly id: string;
  readonly email: string;
  readonly roles: readonly Role[];
  readonly tenant: Tenant;
}

/**
 * Why there is no session: the user left on purpose, the session could not be renewed, or there was
 * none to begin with.
 */
export type SignedOutReason = 'left' | 'expired' | 'none';

export type SessionState =
  | { readonly status: 'restoring' }
  | { readonly status: 'signedOut'; readonly reason: SignedOutReason }
  | { readonly status: 'signedIn'; readonly user: Me };

/**
 * A sign-in that the API refused. `code` is the stable error code of the problem (ADR 0007), which
 * the screen turns into a text of its own language.
 */
export class SignInError extends Error {
  constructor(readonly code: string) {
    super(code);
    this.name = 'SignInError';
  }
}

const UNEXPECTED = 'shared.internal_error';

/** The wait before the second try of a renewal, long enough for the cookie of the other tab to arrive. */
const RENEWAL_RETRY_MS = 300;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function text(record: Record<string, unknown>, field: string): string {
  const value = record[field];
  if (typeof value !== 'string') {
    throw new TypeError(`The answer has no text "${field}".`);
  }

  return value;
}

function parseToken(body: unknown): string {
  if (!isRecord(body)) {
    throw new TypeError('The answer is not an object.');
  }

  return text(body, 'token');
}

function parseMe(body: unknown): Me {
  if (!isRecord(body) || !isRecord(body.tenant) || !Array.isArray(body.roles)) {
    throw new TypeError('The answer is not a user.');
  }
  const tenant = body.tenant;

  return {
    id: text(body, 'id'),
    email: text(body, 'email'),
    roles: body.roles.filter(isRole),
    tenant: {
      id: text(tenant, 'id'),
      name: text(tenant, 'name'),
      defaultLanguage: text(tenant, 'defaultLanguage'),
    },
  };
}

async function problemCode(response: Response): Promise<string> {
  try {
    const body: unknown = await response.json();

    return isRecord(body) && typeof body.code === 'string' ? body.code : UNEXPECTED;
  } catch {
    return UNEXPECTED;
  }
}

function delay(milliseconds: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, milliseconds);
  });
}

/**
 * The session of the signed-in user (ADR 0022).
 *
 * The access token lives only here, in memory: a script injected in the page finds nothing to take
 * from storage. What keeps the session across a reload is the refresh token, in a cookie that
 * JavaScript cannot read, and that the browser sends by itself to `/api/auth`.
 *
 * It is an external store: React reads it with `useSyncExternalStore`.
 */
export class AuthClient {
  private accessToken: string | null = null;

  private state: SessionState = { status: 'restoring' };

  private readonly listeners = new Set<() => void>();

  private renewal: Promise<string | null> | null = null;

  private restoration: Promise<void> | null = null;

  constructor(private readonly send: typeof fetch = (input, init) => fetch(input, init)) {}

  subscribe = (listener: () => void): (() => void) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  getState = (): SessionState => this.state;

  /**
   * Picks the session up after a reload, if the cookie still holds a valid one. Asking twice (React
   * does in development) shares one request, since a refresh token works only once.
   */
  restore(): Promise<void> {
    this.restoration ??= this.restoreOnce();

    return this.restoration;
  }

  async signIn(email: string, password: string): Promise<void> {
    const response = await this.send('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    });
    if (!response.ok) {
      throw new SignInError(await problemCode(response));
    }

    this.accessToken = parseToken(await response.json());
    this.set({ status: 'signedIn', user: await this.loadMe() });
  }

  async signOut(): Promise<void> {
    this.accessToken = null;
    this.set({ status: 'signedOut', reason: 'left' });
    try {
      await this.send('/api/auth/logout', { method: 'POST' });
    } catch {
      // The cookie stays until it expires, but the browser has already forgotten the session.
    }
  }

  /**
   * A request with the access token. When the API says the token is no good it renews the session
   * once and tries again; when it cannot renew, the user is signed out.
   */
  authorizedFetch = async (input: string, init: RequestInit = {}): Promise<Response> => {
    const response = await this.withToken(input, init, this.accessToken);
    if (response.status !== 401) {
      return response;
    }

    const renewed = await this.renew();
    if (renewed === null) {
      this.accessToken = null;
      this.set({ status: 'signedOut', reason: 'expired' });

      return response;
    }

    return this.withToken(input, init, renewed);
  };

  private async restoreOnce(): Promise<void> {
    try {
      const token = await this.renew();
      this.set(
        token === null
          ? { status: 'signedOut', reason: 'none' }
          : { status: 'signedIn', user: await this.loadMe() },
      );
    } catch {
      this.set({ status: 'signedOut', reason: 'none' });
    }
  }

  private withToken(input: string, init: RequestInit, token: string | null): Promise<Response> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    if (token !== null) {
      headers.set('Authorization', `Bearer ${token}`);
    }

    return this.send(input, { ...init, headers });
  }

  private async loadMe(): Promise<Me> {
    const response = await this.authorizedFetch('/api/me');
    if (!response.ok) {
      throw new SignInError(await problemCode(response));
    }

    return parseMe(await response.json());
  }

  /**
   * Exchanges the refresh cookie for a new access token. Everyone who asks while a renewal is on its
   * way shares it, because each refresh token works once.
   */
  private renew(): Promise<string | null> {
    this.renewal ??= this.renewOnce().finally(() => {
      this.renewal = null;
    });

    return this.renewal;
  }

  private async renewOnce(): Promise<string | null> {
    // A second tab that asks at the same moment is refused for a few seconds. By the time we ask
    // again, the cookie that the first tab received is here.
    for (const attempt of [1, 2]) {
      const response = await this.send('/api/auth/refresh', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      });
      if (response.ok) {
        this.accessToken = parseToken(await response.json());

        return this.accessToken;
      }
      if (attempt === 1) {
        await delay(RENEWAL_RETRY_MS);
      }
    }

    return null;
  }

  private set(state: SessionState): void {
    this.state = state;
    for (const listener of this.listeners) {
      listener();
    }
  }
}
