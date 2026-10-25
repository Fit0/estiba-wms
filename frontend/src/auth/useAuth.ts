import { use } from 'react';
import type { AuthClient, SessionState } from './AuthClient';
import { AuthClientContext, useSessionState } from './AuthContext';

export interface Auth {
  readonly session: SessionState;
  readonly client: AuthClient;
}

export function useAuth(): Auth {
  const client = use(AuthClientContext);
  if (client === null) {
    throw new Error('useAuth needs an AuthProvider above it.');
  }

  return { session: useSessionState(client), client };
}
