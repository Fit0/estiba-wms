import { useState } from 'react';
import { RouterProvider, type DataRouter } from 'react-router';
import type { AuthClient } from './auth/AuthClient';
import { ApiProvider } from './api/ApiProvider';
import { AuthProvider } from './auth/AuthContext';
import { Toaster } from './components/ui/Toaster';
import { TooltipProvider } from './components/ui/Tooltip';
import { ConfirmationQueue } from './offline/ConfirmationQueue';
import { ConfirmationSync } from './offline/ConfirmationSync';
import type { KeyValueStorage } from './offline/database';
import { OfflineProvider } from './offline/OfflineProvider';

/**
 * The root of the app: the session, the queue of confirmations made without a connection, and the
 * routes that depend on them. `queue` and `cache` come from IndexedDB in the browser; without them
 * (the tests) the queue lives in memory and nothing is kept on the device.
 */
export function App({
  client,
  router,
  queue,
  cache,
}: {
  client: AuthClient;
  router: DataRouter;
  queue?: ConfirmationQueue;
  cache?: KeyValueStorage;
}) {
  const [fallback] = useState(() => new ConfirmationQueue());

  return (
    <AuthProvider client={client}>
      <OfflineProvider queue={queue ?? fallback}>
        <ApiProvider session={client} cache={cache}>
          <TooltipProvider delayDuration={300}>
            <RouterProvider router={router} />
          </TooltipProvider>
          <ConfirmationSync />
          <Toaster />
        </ApiProvider>
      </OfflineProvider>
    </AuthProvider>
  );
}
