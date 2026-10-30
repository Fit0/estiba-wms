import { useEffect } from 'react';
import { useRegisterSW } from 'virtual:pwa-register/react';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';

/**
 * Registers the service worker and tells the person what it finds out: that the app now opens without
 * a connection, or that a new version is waiting. The new version is taken only when they say so, so
 * the page never reloads in the middle of a pick list. It shows nothing itself; it speaks through the
 * notices.
 */
export function UpdatePrompt() {
  const { t } = useTranslation();
  const {
    offlineReady: [offlineReady, setOfflineReady],
    needRefresh: [needRefresh, setNeedRefresh],
    updateServiceWorker,
  } = useRegisterSW();

  useEffect(() => {
    if (!offlineReady) {
      return;
    }
    toast.success(t('pwa.offlineReady'));
    setOfflineReady(false);
  }, [offlineReady, setOfflineReady, t]);

  useEffect(() => {
    if (!needRefresh) {
      return;
    }
    toast(t('pwa.update.available'), {
      duration: Infinity,
      action: {
        label: t('pwa.update.action'),
        onClick: () => {
          void updateServiceWorker(true);
        },
      },
      onDismiss: () => {
        setNeedRefresh(false);
      },
    });
  }, [needRefresh, setNeedRefresh, t, updateServiceWorker]);

  return null;
}
