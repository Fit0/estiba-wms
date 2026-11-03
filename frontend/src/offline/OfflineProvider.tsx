import { createContext, use, useMemo, useSyncExternalStore, type ReactNode } from 'react';
import { useAuth } from '../auth/useAuth';
import type { ConfirmationQueue, QueuedConfirmation } from './ConfirmationQueue';

const QueueContext = createContext<ConfirmationQueue | null>(null);

/** Gives the app the queue of confirmations that wait for a connection. */
export function OfflineProvider({
  queue,
  children,
}: {
  queue: ConfirmationQueue;
  children: ReactNode;
}) {
  return <QueueContext value={queue}>{children}</QueueContext>;
}

export function useQueue(): ConfirmationQueue {
  const queue = use(QueueContext);
  if (queue === null) {
    throw new Error('useQueue needs an OfflineProvider above it.');
  }

  return queue;
}

/** The confirmations of the signed-in user that have not been sent, pending or refused. */
export function useQueued(): readonly QueuedConfirmation[] {
  const queue = useQueue();
  const { session } = useAuth();
  const all = useSyncExternalStore(queue.subscribe, queue.getSnapshot);

  const userId = session.status === 'signedIn' ? session.user.id : null;

  return useMemo(
    () => (userId === null ? [] : all.filter((entry) => entry.userId === userId)),
    [all, userId],
  );
}
