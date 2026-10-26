import { NavLink } from 'react-router';
import type { Role } from '../auth/roles';
import { BrandMark } from '../components/BrandMark';
import { useTranslation } from '../i18n';
import { visibleNavigation } from './navigation';

const link =
  'flex h-control items-center gap-3 rounded-control border-l-2 px-2.5 text-body-md font-medium';
const active = 'border-primary bg-selected text-accent';
const idle = 'border-transparent text-fg-secondary hover:bg-surface-2 hover:text-fg';

/**
 * The side navigation: open at 240 px, or a rail of icons at 64 px. When it is a rail the names stay
 * for screen readers and show as a tooltip.
 */
export function Sidebar({ collapsed, roles }: { collapsed: boolean; roles: readonly Role[] }) {
  const { t } = useTranslation();

  return (
    <aside
      id="sidebar"
      className={`sticky top-0 flex h-screen shrink-0 flex-col border-r border-line bg-surface-1 transition-[width] ${collapsed ? 'w-16' : 'w-60'}`}
    >
      <div className="flex h-14 items-center border-b border-line px-4">
        <BrandMark compact={collapsed} />
      </div>
      <nav aria-label={t('nav.main')} className="flex-1 overflow-y-auto p-2">
        {visibleNavigation(roles).map((group) => (
          <div key={group.label} className="mb-gutter">
            <p
              className={
                collapsed ? 'sr-only' : 'px-2.5 pt-3 pb-1 text-label-sm text-fg-muted uppercase'
              }
            >
              {t(group.label)}
            </p>
            <ul className="grid gap-0.5">
              {group.items.map((item) => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.end ?? false}
                    title={collapsed ? t(item.label) : undefined}
                    className={({ isActive }) => `${link} ${isActive ? active : idle}`}
                  >
                    <item.icon aria-hidden="true" className="size-4 shrink-0" />
                    <span className={collapsed ? 'sr-only' : ''}>{t(item.label)}</span>
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
    </aside>
  );
}
