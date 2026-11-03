import { useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useRef } from 'react';
import { confirmPickLine, refreshAfterPick } from '../api/picking';
import { useApi } from '../api/ApiProvider';
import { useAuth } from '../auth/useAuth';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { useOnlineStatus } from '../layouts/useOnlineStatus';
import { useQueue, useQueued } from './OfflineProvider';
import { synchronize } from './synchronize';

const retryEvery = 15_000;

/**
 * Sends the confirmations that wait in the queue. It tries when the app opens, when the browser says
 * the connection is back and, while something is waiting, every few seconds, because the browser's
 * word that it is online is not a promise that the API can be reached. It draws nothing; it speaks
 * through notices.
 */
export function ConfirmationSync() {
  const { session } = useAuth();
  const { t } = useTranslation();
  const api = useApi();
  const queue = useQueue();
  const queries = useQueryClient();
  const queued = useQueued();
  const online = useOnlineStatus();
  const running = useRef(false);
  const userId = session.status === 'signedIn' ? session.user.id : null;
  const pending = queued.filter((entry) => entry.status === 'pending').length;

  const run = useCallback(async () => {
    if (userId === null || running.current || !navigator.onLine) {
      return;
    }
    running.current = true;
    try {
      const result = await synchronize(queue, userId, async (entry) => {
        const list = await confirmPickLine(api, entry.listId, entry.position, entry.confirmation);
        await refreshAfterPick(queries, list);

        return list;
      });
      if (result.sent > 0) {
        toast.success(t('offline.sent', { count: String(result.sent) }));
      }
      if (result.refused > 0) {
        toast.error(t('offline.refused', { count: String(result.refused) }));
      }
    } finally {
      running.current = false;
    }
  }, [api, queries, queue, t, userId]);

  useEffect(() => {
    void run();
  }, [run, online, pending]);

  useEffect(() => {
    if (pending === 0) {
      return;
    }
    const timer = setInterval(() => {
      void run();
    }, retryEvery);

    return () => {
      clearInterval(timer);
    };
  }, [pending, run]);

  return null;
}
