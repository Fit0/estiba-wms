import { useEffect } from 'react';
import { Link, Outlet } from 'react-router';
import { BrandMark } from '../components/BrandMark';
import { ThemeToggle } from '../components/ThemeToggle';
import { useTranslation } from '../i18n';
import { applyDensity, currentDensity } from '../theme/density';
import { OnlineStatus } from './OnlineStatus';

/**
 * The frame of the picking screens, for a phone in the operator's hand: no side navigation, a thin bar
 * with the connection, and the touch density (48 px controls) while it is on screen, whatever the
 * density the person chose for the back office. The choice is not changed, only suspended.
 */
export function PickingLayout() {
  const { t } = useTranslation();

  useEffect(() => {
    const before = currentDensity();
    applyDensity('touch');

    return () => {
      applyDensity(before);
    };
  }, []);

  return (
    <div className="flex min-h-dvh flex-col">
      <a
        href="#content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-20 focus:rounded-control focus:bg-surface-3 focus:p-2"
      >
        {t('app.skipToContent')}
      </a>
      <header className="sticky top-0 z-10 flex h-16 items-center gap-gutter border-b border-line bg-surface-1 px-margin">
        <BrandMark />
        <div className="ml-auto flex items-center gap-2">
          <OnlineStatus />
          <ThemeToggle />
        </div>
      </header>
      <main id="content" tabIndex={-1} className="flex-1 p-margin">
        <Outlet />
      </main>
      <footer className="border-t border-line bg-surface-1 p-margin">
        <Link
          to="/"
          className="flex h-control items-center justify-center rounded-control border border-line-strong bg-surface-2 text-body-md font-semibold text-fg hover:bg-surface-3"
        >
          {t('picking.backOffice')}
        </Link>
      </footer>
    </div>
  );
}
