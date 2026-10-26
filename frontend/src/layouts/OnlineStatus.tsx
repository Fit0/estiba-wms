import { Wifi, WifiOff } from 'lucide-react';
import { useTranslation } from '../i18n';
import { useOnlineStatus } from './useOnlineStatus';

/**
 * Whether there is a connection. It says it with an icon and a word, and not only with a color.
 */
export function OnlineStatus() {
  const online = useOnlineStatus();
  const { t } = useTranslation();
  const Icon = online ? Wifi : WifiOff;
  const tone = online
    ? 'border-ok-line bg-ok-fill text-ok'
    : 'border-warn-line bg-warn-fill text-warn';

  return (
    <span
      role="status"
      className={`flex items-center gap-1.5 rounded-control border px-2 py-1 text-label-sm ${tone}`}
    >
      <Icon aria-hidden="true" className="size-3.5" />
      <span className="sr-only sm:not-sr-only">
        {t(online ? 'status.online' : 'status.offline')}
      </span>
    </span>
  );
}
