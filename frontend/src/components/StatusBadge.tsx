import type { ReactNode } from 'react';
import { toneBadge, type Tone } from './tone';

/**
 * A status as a small label: stock status, order status, a task. The text says what it is, the color
 * only helps, and `dot` adds a mark for places where the badge sits among many.
 */
export function StatusBadge({
  tone = 'neutral',
  dot = false,
  children,
}: {
  tone?: Tone;
  dot?: boolean;
  children: ReactNode;
}) {
  return (
    <span
      data-tone={tone}
      className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-control border px-2 py-0.5 text-label-sm ${toneBadge[tone]}`}
    >
      {dot && <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />}
      {children}
    </span>
  );
}
