import { useAuth } from '../auth/useAuth';
import { PageHeader } from '../components/PageHeader';
import { useTranslation } from '../i18n';

export function HomePage() {
  const { session } = useAuth();
  const { t } = useTranslation();

  return (
    <PageHeader title={t('home.title')}>
      {session.status === 'signedIn' && t('home.welcome', { tenant: session.user.tenant.name })}
    </PageHeader>
  );
}
