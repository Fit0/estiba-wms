import { useSyncExternalStore } from 'react';
import { chooseTheme, currentTheme, subscribeTheme, type Theme } from './theme';

export function useTheme(): { theme: Theme; toggle: () => void } {
  const theme = useSyncExternalStore(subscribeTheme, () => currentTheme());

  return {
    theme,
    toggle: () => {
      chooseTheme(theme === 'dark' ? 'light' : 'dark');
    },
  };
}
