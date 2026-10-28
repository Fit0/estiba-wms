import type { CSSProperties } from 'react';
import { Toaster as Sonner, toast } from 'sonner';
import { useTheme } from '../../theme/useTheme';

export { toast };

/**
 * Where notices appear. They are announced to screen readers without taking the focus. The colors
 * come from the tokens, so a notice is right in both themes, and the kind of notice shows in its edge
 * and in its icon, never in the color of the text alone.
 */
export function Toaster() {
  const { theme } = useTheme();

  return (
    <Sonner
      theme={theme}
      position="bottom-right"
      style={
        {
          '--normal-bg': 'var(--surface-2)',
          '--normal-text': 'var(--fg)',
          '--normal-border': 'var(--line-strong)',
          '--success-bg': 'var(--surface-2)',
          '--success-text': 'var(--fg)',
          '--success-border': 'var(--ok)',
          '--error-bg': 'var(--surface-2)',
          '--error-text': 'var(--fg)',
          '--error-border': 'var(--bad)',
        } as CSSProperties
      }
    />
  );
}
