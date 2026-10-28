import { Command } from 'cmdk';
import { LogOut, Moon, Sun } from 'lucide-react';
import { Dialog as DialogPrimitive, VisuallyHidden } from 'radix-ui';
import { useEffect } from 'react';
import { useNavigate } from 'react-router';
import type { Role } from '../../auth/roles';
import { useAuth } from '../../auth/useAuth';
import { useTranslation } from '../../i18n';
import { visibleNavigation } from '../../layouts/navigation';
import { useTheme } from '../../theme/useTheme';

/** Ctrl+K (Cmd+K on a Mac) opens the palette from anywhere in the app. */
export function useCommandPaletteShortcut(onToggle: () => void): void {
  useEffect(() => {
    function onKeyDown(event: KeyboardEvent): void {
      if (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        onToggle();
      }
    }

    document.addEventListener('keydown', onKeyDown);

    return () => {
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [onToggle]);
}

const itemClass =
  'flex min-h-9 cursor-default items-center gap-2 rounded-control px-2 text-body-md select-none data-[selected=true]:bg-surface-3';
const groupClass =
  '[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1 [&_[cmdk-group-heading]]:text-label-sm [&_[cmdk-group-heading]]:text-fg-secondary [&_[cmdk-group-heading]]:uppercase';

/**
 * The command palette: type to find a page or an action, arrows to move, Enter to run, Escape to
 * close. It offers the pages the user's role can see, the theme and signing out. A screen that adds
 * an action adds a group here.
 */
export function CommandPalette({
  open,
  onOpenChange,
  roles,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  roles: readonly Role[];
}) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { client } = useAuth();
  const { theme, toggle } = useTheme();
  const ThemeIcon = theme === 'dark' ? Sun : Moon;

  function run(action: () => void): void {
    onOpenChange(false);
    action();
  }

  return (
    <Command.Dialog
      open={open}
      onOpenChange={onOpenChange}
      label={t('palette.title')}
      overlayClassName="fixed inset-0 z-40 bg-canvas/80"
      contentClassName="fixed top-[20vh] left-1/2 z-50 w-[calc(100vw-2rem)] max-w-xl -translate-x-1/2 overflow-hidden rounded-panel border border-line-strong bg-surface-2 shadow-overlay"
    >
      <VisuallyHidden.Root>
        <DialogPrimitive.Description>{t('palette.description')}</DialogPrimitive.Description>
      </VisuallyHidden.Root>
      <Command.Input
        placeholder={t('palette.placeholder')}
        className="h-12 w-full border-b border-line bg-transparent px-4 text-body-lg text-fg outline-none placeholder:text-fg-secondary"
      />
      <Command.List className="max-h-80 overflow-auto p-2">
        <Command.Empty className="px-2 py-6 text-center text-body-md text-fg-secondary">
          {t('palette.empty')}
        </Command.Empty>
        {visibleNavigation(roles).map((group) => (
          <Command.Group key={group.label} heading={t(group.label)} className={groupClass}>
            {group.items.map((item) => (
              <Command.Item
                key={item.to}
                value={t(item.label)}
                onSelect={() => {
                  run(() => void navigate(item.to));
                }}
                className={itemClass}
              >
                <item.icon aria-hidden="true" className="size-4" />
                {t(item.label)}
              </Command.Item>
            ))}
          </Command.Group>
        ))}
        <Command.Group heading={t('palette.group.actions')} className={groupClass}>
          <Command.Item
            value={theme === 'dark' ? t('theme.toLight') : t('theme.toDark')}
            onSelect={() => {
              run(toggle);
            }}
            className={itemClass}
          >
            <ThemeIcon aria-hidden="true" className="size-4" />
            {theme === 'dark' ? t('theme.toLight') : t('theme.toDark')}
          </Command.Item>
          <Command.Item
            value={t('session.signOut')}
            onSelect={() => {
              run(() => void client.signOut());
            }}
            className={itemClass}
          >
            <LogOut aria-hidden="true" className="size-4" />
            {t('session.signOut')}
          </Command.Item>
        </Command.Group>
      </Command.List>
    </Command.Dialog>
  );
}
