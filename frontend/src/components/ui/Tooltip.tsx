import { Tooltip as TooltipPrimitive } from 'radix-ui';
import type { ReactElement } from 'react';

export const TooltipProvider = TooltipPrimitive.Provider;

/**
 * A short note on a control, shown on hover and on keyboard focus. It only repeats or completes
 * the name of a control: what a person needs to know must not live only in a tooltip.
 */
export function Tooltip({ content, children }: { content: string; children: ReactElement }) {
  return (
    <TooltipPrimitive.Root>
      <TooltipPrimitive.Trigger asChild>{children}</TooltipPrimitive.Trigger>
      <TooltipPrimitive.Portal>
        <TooltipPrimitive.Content
          sideOffset={6}
          className="z-50 rounded-control border border-line-strong bg-surface-3 px-2 py-1 text-body-sm text-fg shadow-popover"
        >
          {content}
        </TooltipPrimitive.Content>
      </TooltipPrimitive.Portal>
    </TooltipPrimitive.Root>
  );
}
