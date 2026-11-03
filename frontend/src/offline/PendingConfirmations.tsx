import { CloudOff } from 'lucide-react';
import { Button } from '../components/Button';
import { useTranslation } from '../i18n';
import { waveName } from '../waves/display';
import { useQueue, useQueued } from './OfflineProvider';

/**
 * What is waiting to be sent, on every picking screen: how many confirmations are in the queue, and
 * the ones the API refused with its reason, each with a way to give it up. Nothing shows when the
 * queue is empty.
 */
export function PendingConfirmations() {
  const { t } = useTranslation();
  const queue = useQueue();
  const queued = useQueued();
  const pending = queued.filter((entry) => entry.status === 'pending');
  const refused = queued.filter((entry) => entry.status === 'rejected');

  if (queued.length === 0) {
    return null;
  }

  return (
    <section aria-label={t('offline.title')} className="grid gap-gutter">
      {pending.length > 0 && (
        <p
          role="status"
          className="flex items-center gap-2 rounded-panel border border-warn-line bg-warn-fill p-gutter text-body-md text-warn"
        >
          <CloudOff aria-hidden="true" className="size-4 shrink-0" />
          {t('offline.pending', { count: String(pending.length) })}
        </p>
      )}
      {refused.length > 0 && (
        <div className="grid gap-2 rounded-panel border border-bad bg-bad-fill p-gutter">
          <p className="text-body-md font-semibold text-bad">{t('offline.refused.title')}</p>
          <ul className="grid gap-2">
            {refused.map((entry) => (
              <li key={entry.id} className="grid gap-1 text-body-md">
                <span>
                  {t('offline.refused.item', {
                    list: waveName(entry.listId),
                    position: String(entry.position),
                  })}
                </span>
                <span className="text-fg-secondary">
                  {entry.refusal?.detail === '' || entry.refusal === null
                    ? t('form.failedUnknown')
                    : entry.refusal.detail}
                </span>
                <Button
                  onClick={() => {
                    void queue.discard(entry.id);
                  }}
                >
                  {t('offline.discard')}
                </Button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
