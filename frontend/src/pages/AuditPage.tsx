import { PageHeader } from '../components/PageHeader';
import { useTranslation } from '../i18n';

/**
 * The audit log is for supervisors and admins (`security.yaml`). The list itself comes with the
 * screens of the back office; this page is where the route and its guard are proved.
 */
export function AuditPage() {
  const { t } = useTranslation();

  return <PageHeader title={t('audit.title')}>{t('audit.intro')}</PageHeader>;
}
