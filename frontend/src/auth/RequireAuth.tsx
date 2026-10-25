import { Navigate, Outlet, useLocation } from 'react-router';
import { useTranslation } from '../i18n';
import { useAuth } from './useAuth';

/**
 * The routes below it need a signed-in user. While the session is being picked up after a reload it
 * says so, instead of sending the user to the sign-in page for a moment. Without a session it sends
 * them there, and remembers where they were going unless they left on purpose.
 */
export function RequireAuth() {
  const { session } = useAuth();
  const location = useLocation();
  const { t } = useTranslation();

  if (session.status === 'restoring') {
    return <p role="status">{t('session.restoring')}</p>;
  }

  if (session.status === 'signedOut') {
    // Someone who left on purpose does not come back to the page they left.
    const state =
      session.reason === 'left' ? null : { from: `${location.pathname}${location.search}` };

    return <Navigate to="/login" replace state={state} />;
  }

  return <Outlet />;
}
