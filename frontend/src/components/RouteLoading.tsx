import { useTranslation } from '../i18n';

/**
 * What a screen shows while its file is on the way (E10-07). It is a status, so a screen reader says
 * it, and it takes the place of the page without moving the frame around it.
 */
export function RouteLoading() {
  const { t } = useTranslation();

  return (
    <p role="status" className="p-margin text-fg-secondary">
      {t('common.loading')}
    </p>
  );
}
