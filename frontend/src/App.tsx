import { RouterProvider, type DataRouter } from 'react-router';
import type { AuthClient } from './auth/AuthClient';
import { ApiProvider } from './api/ApiProvider';
import { AuthProvider } from './auth/AuthContext';
import { Toaster } from './components/ui/Toaster';
import { TooltipProvider } from './components/ui/Tooltip';

/**
 * The root of the app: the session, and the routes that depend on it.
 */
export function App({ client, router }: { client: AuthClient; router: DataRouter }) {
  return (
    <AuthProvider client={client}>
      <ApiProvider session={client}>
        <TooltipProvider delayDuration={300}>
          <RouterProvider router={router} />
        </TooltipProvider>
        <Toaster />
      </ApiProvider>
    </AuthProvider>
  );
}
