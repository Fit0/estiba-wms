import { useState } from 'react';

const SIDEBAR_KEY = 'estiba-sidebar';

/** From this width the sidebar starts open; below it, as a rail of icons (DESIGN.md, layout). */
const WIDE = '(min-width: 1024px)';

function initiallyCollapsed(): boolean {
  try {
    const stored = window.localStorage.getItem(SIDEBAR_KEY);
    if (stored === 'collapsed' || stored === 'expanded') {
      return stored === 'collapsed';
    }
  } catch {
    // No storage, no memory of the last choice.
  }

  return !window.matchMedia(WIDE).matches;
}

/**
 * Whether the side navigation is a rail of icons or open, and the choice kept for the next visit.
 */
export function useSidebar(): { collapsed: boolean; toggle: () => void } {
  const [collapsed, setCollapsed] = useState(initiallyCollapsed);

  return {
    collapsed,
    toggle: () => {
      const next = !collapsed;
      setCollapsed(next);
      try {
        window.localStorage.setItem(SIDEBAR_KEY, next ? 'collapsed' : 'expanded');
      } catch {
        // The choice still holds for this visit.
      }
    },
  };
}
