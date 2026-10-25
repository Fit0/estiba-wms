import { useTranslation } from '../i18n';

export function ForbiddenPage() {
  const { t } = useTranslation();

  return (
    <section>
      <h1>{t('forbidden.title')}</h1>
      <p>{t('forbidden.body')}</p>
    </section>
  );
}
