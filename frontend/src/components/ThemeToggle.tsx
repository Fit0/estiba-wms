import { Moon, Sun } from 'lucide-react';
import { useTranslation } from '../i18n';
import { useTheme } from '../theme/useTheme';
import { Button } from './Button';

/**
 * Switches between the dark and the light theme. The icon and the name say what the button will do,
 * not what is on now, as a button should.
 */
export function ThemeToggle() {
  const { theme, toggle } = useTheme();
  const { t } = useTranslation();
  const Icon = theme === 'dark' ? Sun : Moon;

  return (
    <Button
      variant="ghost"
      icon
      onClick={toggle}
      aria-label={theme === 'dark' ? t('theme.toLight') : t('theme.toDark')}
    >
      <Icon aria-hidden="true" className="size-4" />
    </Button>
  );
}
