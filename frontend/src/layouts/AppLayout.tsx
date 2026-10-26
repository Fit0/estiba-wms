import { Outlet } from 'react-router';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';
import { Sidebar } from './Sidebar';
import { TopBar } from './TopBar';
import { useSidebar } from './useSidebar';

/**
 * The frame of the signed-in app: the side navigation, the top bar and the page itself.
 */
export function AppLayout() {
  const { session } = useAuth();
  const { t } = useTranslation();
  const sidebar = useSidebar();

  if (session.status !== 'signedIn') {
    return null;
  }

  return (
    <div className="flex min-h-screen">
      <a
        href="#content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-20 focus:rounded-control focus:bg-surface-3 focus:p-2"
      >
        {t('app.skipToContent')}
      </a>
      <Sidebar collapsed={sidebar.collapsed} roles={session.user.roles} />
      <div className="flex min-w-0 flex-1 flex-col">
        <TopBar collapsed={sidebar.collapsed} onToggle={sidebar.toggle} />
        <main id="content" tabIndex={-1} className="flex-1 p-margin lg:p-margin-lg">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
