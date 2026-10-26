import { PageHeader } from '../components/PageHeader';
import { useTranslation } from '../i18n';

export function ForbiddenPage() {
  const { t } = useTranslation();

  return <PageHeader title={t('forbidden.title')}>{t('forbidden.body')}</PageHeader>;
}
