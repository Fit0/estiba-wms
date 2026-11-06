import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router';
import { unwrap } from '../api/ApiError';
import { useApi } from '../api/ApiProvider';
import { kpiKeys } from '../api/kpis';
import { pickingKeys } from '../api/picking';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { parseNotification, renewDelay, retryDelay } from './messages';

/** The ids of the last notifications shown, so one that arrives twice is shown once. */
const REMEMBERED = 50;

/**
 * Listens to the live notifications of the tenant (E07-06, ADR 0045) and shows them as notices, while a
 * supervisor or an admin is signed in. It draws nothing.
 *
 * The API gives access with a cookie that only the hub receives, and the page opens an `EventSource` on
 * the hub. The cookie lasts an hour, so access is asked again before it ends, and again, with a growing
 * wait, when the stream closes. A notification is a hint: it refreshes the figures and the pick lists
 * that the screens ask the API for, which keep the truth.
 */
export function NotificationStream() {
  const { session } = useAuth();
  const { t } = useTranslation();
  const api = useApi();
  const queries = useQueryClient();
  const navigate = useNavigate();
  const allowed = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');
  // The effect must not start over when the words or the router change, only when who is here does.
  const latest = useRef({ t, navigate });
  useEffect(() => {
    latest.current = { t, navigate };
  });

  useEffect(() => {
    if (!allowed || typeof EventSource === 'undefined') {
      return undefined;
    }

    let source: EventSource | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let stopped = false;
    let failures = 0;
    const seen: string[] = [];

    const show = (event: MessageEvent<string>): void => {
      if (event.lastEventId !== '') {
        if (seen.includes(event.lastEventId)) {
          return;
        }
        seen.push(event.lastEventId);
        seen.splice(0, Math.max(0, seen.length - REMEMBERED));
      }
      const notification = parseNotification(event.data);
      if (notification === null) {
        return;
      }
      const { t: say, navigate: go } = latest.current;
      const open = {
        label: say('notifications.open'),
        onClick: () => {
          void go(`/picking/${notification.pickListId}`);
        },
      };
      if (notification.type === 'pick_list_completed') {
        toast.info(say('notifications.pickListCompleted'), { action: open });
      } else {
        toast.warning(
          say('notifications.shortPick', { units: String(notification.missingUnits) }),
          {
            action: open,
          },
        );
      }
      void queries.invalidateQueries({ queryKey: kpiKeys.all });
      void queries.invalidateQueries({ queryKey: pickingKeys.all });
    };

    const retry = (): void => {
      failures += 1;
      timer = setTimeout(() => void connect(), retryDelay(failures));
    };

    const connect = async (): Promise<void> => {
      source?.close();
      source = undefined;
      try {
        const access = unwrap(await api.GET('/api/notifications/access'));
        if (stopped) {
          return;
        }
        const next = new EventSource(
          `${access.hub}?${new URLSearchParams({ topic: access.topic })}`,
          {
            withCredentials: true,
          },
        );
        source = next;
        next.onopen = () => {
          failures = 0;
        };
        next.onmessage = show;
        next.onerror = () => {
          // The browser reconnects by itself while it is connecting; a closed stream (the hub refused
          // the cookie, or the network went) needs new access.
          if (next.readyState === EventSource.CLOSED) {
            next.close();
            retry();
          }
        };
        timer = setTimeout(() => void connect(), renewDelay(access.expiresAt));
      } catch {
        if (!stopped) {
          retry();
        }
      }
    };

    void connect();

    return () => {
      stopped = true;
      source?.close();
      clearTimeout(timer);
    };
  }, [allowed, api, queries]);

  return null;
}
