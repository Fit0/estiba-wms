import { useAuth } from '../auth/useAuth';
import { hasRole } from '../auth/roles';
import { PageHeader } from '../components/PageHeader';
import { Dashboard } from '../dashboard/Dashboard';
import { useTranslation } from '../i18n';

/**
 * The start page. A supervisor or an admin also gets the figures of the warehouse (E07-05); the API
 * refuses them to an operator, so they are not asked for.
 */
export function HomePage() {
  const { session } = useAuth();
  const { t } = useTranslation();

  return (
    <>
      <PageHeader title={t('home.title')}>
        {session.status === 'signedIn' && t('home.welcome', { tenant: session.user.tenant.name })}
      </PageHeader>
      {session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor') && <Dashboard />}
    </>
  );
}
