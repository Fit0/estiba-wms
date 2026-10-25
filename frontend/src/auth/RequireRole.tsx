import { Outlet } from 'react-router';
import { ForbiddenPage } from '../pages/ForbiddenPage';
import { hasRole, type Role } from './roles';
import { useAuth } from './useAuth';

/**
 * The routes below it need this role at least. It goes inside `RequireAuth`, so there is always a user. This only
 * decides what to show: the API refuses what the role may not do, whatever the page does.
 */
export function RequireRole({ minimum }: { minimum: Role }) {
  const { session } = useAuth();

  if (session.status !== 'signedIn') {
    return null;
  }

  return hasRole(session.user.roles, minimum) ? <Outlet /> : <ForbiddenPage />;
}
