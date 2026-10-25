import { createContext, useEffect, useSyncExternalStore, type ReactNode } from 'react';
import type { AuthClient, SessionState } from './AuthClient';

export const AuthClientContext = createContext<AuthClient | null>(null);

/**
 * Gives the session to the whole app, and picks up the one a reload left behind.
 */
export function AuthProvider({ client, children }: { client: AuthClient; children: ReactNode }) {
  useEffect(() => {
    void client.restore();
  }, [client]);

  return <AuthClientContext value={client}>{children}</AuthClientContext>;
}

export function useSessionState(client: AuthClient): SessionState {
  return useSyncExternalStore(client.subscribe, client.getState);
}
