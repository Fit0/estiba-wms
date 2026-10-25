import type { RouteObject } from 'react-router';
import { RequireAuth } from './auth/RequireAuth';
import { RequireRole } from './auth/RequireRole';
import { AppLayout } from './layouts/AppLayout';
import { AuditPage } from './pages/AuditPage';
import { HomePage } from './pages/HomePage';
import { LoginPage } from './pages/LoginPage';
import { NotFoundPage } from './pages/NotFoundPage';

/**
 * Every route of the app. Signing in is the only public one. The rest sit inside `RequireAuth`, and the
 * ones that need more than any user sit inside `RequireRole` as well.
 */
export const routes: RouteObject[] = [
  { path: '/login', element: <LoginPage /> },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <HomePage /> },
          {
            element: <RequireRole minimum="supervisor" />,
            children: [{ path: 'audit', element: <AuditPage /> }],
          },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
];
