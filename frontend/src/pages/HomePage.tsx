import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';

export function HomePage() {
  const { session } = useAuth();
  const { t } = useTranslation();

  return (
    <section>
      <h1>{t('home.title')}</h1>
      {session.status === 'signedIn' && (
        <p>{t('home.welcome', { tenant: session.user.tenant.name })}</p>
      )}
    </section>
  );
}
