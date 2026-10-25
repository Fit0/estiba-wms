import { RouterProvider, type DataRouter } from 'react-router';
import type { AuthClient } from './auth/AuthClient';
import { AuthProvider } from './auth/AuthContext';

/**
 * The root of the app: the session, and the routes that depend on it.
 */
export function App({ client, router }: { client: AuthClient; router: DataRouter }) {
  return (
    <AuthProvider client={client}>
      <RouterProvider router={router} />
    </AuthProvider>
  );
}
