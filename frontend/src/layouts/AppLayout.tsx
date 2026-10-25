import { NavLink, Outlet } from 'react-router';
import { highestRole, hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';

/**
 * The frame of the signed-in app: who is signed in, where to go, and the page itself.
 */
export function AppLayout() {
  const { session, client } = useAuth();
  const { t } = useTranslation();

  if (session.status !== 'signedIn') {
    return null;
  }
  const { user } = session;
  const role = highestRole(user.roles);

  return (
    <>
      <a className="skip-link" href="#content">
        {t('app.skipToContent')}
      </a>
      <header className="app-header">
        <strong>{t('app.name')}</strong>
        <nav aria-label={t('nav.main')}>
          <NavLink to="/" end>
            {t('nav.home')}
          </NavLink>
          {hasRole(user.roles, 'supervisor') && <NavLink to="/audit">{t('nav.audit')}</NavLink>}
        </nav>
        <div className="user">
          <span>
            {t('session.signedInAs', { email: user.email })}
            {role !== null && ` (${t(`role.${role}`)})`}
          </span>
          <button type="button" onClick={() => void client.signOut()}>
            {t('session.signOut')}
          </button>
        </div>
      </header>
      <main id="content" tabIndex={-1}>
        <Outlet />
      </main>
    </>
  );
}
