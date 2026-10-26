import { LogOut, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { highestRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { ThemeToggle } from '../components/ThemeToggle';
import { useTranslation } from '../i18n';
import { OnlineStatus } from './OnlineStatus';

/**
 * The bar over every page of the app: the switch of the sidebar, the connection, the theme and who is
 * signed in. The warehouse, the search and the notices come with the screens that have their data.
 */
export function TopBar({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void }) {
  const { session, client } = useAuth();
  const { t } = useTranslation();

  if (session.status !== 'signedIn') {
    return null;
  }
  const { user } = session;
  const role = highestRole(user.roles);
  const SidebarIcon = collapsed ? PanelLeftOpen : PanelLeftClose;

  return (
    <header className="sticky top-0 z-10 flex h-14 items-center gap-gutter border-b border-line bg-surface-1 px-margin">
      <Button
        variant="ghost"
        icon
        onClick={onToggle}
        aria-label={collapsed ? t('nav.expand') : t('nav.collapse')}
        aria-expanded={!collapsed}
        aria-controls="sidebar"
      >
        <SidebarIcon aria-hidden="true" className="size-4" />
      </Button>
      <div className="ml-auto flex items-center gap-2">
        <OnlineStatus />
        <ThemeToggle />
        <div
          role="group"
          aria-label={t('session.signedInAs', { email: user.email })}
          className="hidden text-right sm:block"
        >
          <p className="text-body-md font-medium">{user.email}</p>
          {role !== null && (
            <p className="text-label-sm text-fg-secondary uppercase">{t(`role.${role}`)}</p>
          )}
        </div>
        <Button
          variant="ghost"
          icon
          onClick={() => void client.signOut()}
          aria-label={t('session.signOut')}
        >
          <LogOut aria-hidden="true" className="size-4" />
        </Button>
      </div>
    </header>
  );
}
