import { DropdownMenu as MenuPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const DropdownMenu = MenuPrimitive.Root;
export const DropdownMenuTrigger = MenuPrimitive.Trigger;

/** A menu of actions that opens from a button. Arrow keys move, Enter chooses, Escape closes. */
export function DropdownMenuContent({
  className = '',
  sideOffset = 6,
  ...props
}: ComponentProps<typeof MenuPrimitive.Content>) {
  return (
    <MenuPrimitive.Portal>
      <MenuPrimitive.Content
        sideOffset={sideOffset}
        className={`z-50 min-w-44 rounded-panel border border-line-strong bg-surface-2 p-1 shadow-popover ${className}`}
        {...props}
      />
    </MenuPrimitive.Portal>
  );
}

export function DropdownMenuItem({
  className = '',
  ...props
}: ComponentProps<typeof MenuPrimitive.Item>) {
  return (
    <MenuPrimitive.Item
      className={`flex min-h-control cursor-default items-center gap-2 rounded-control px-2 text-body-md outline-none select-none data-[disabled]:opacity-50 data-[highlighted]:bg-surface-3 ${className}`}
      {...props}
    />
  );
}

export function DropdownMenuLabel({
  className = '',
  ...props
}: ComponentProps<typeof MenuPrimitive.Label>) {
  return (
    <MenuPrimitive.Label
      className={`px-2 py-1 text-label-sm text-fg-secondary uppercase ${className}`}
      {...props}
    />
  );
}

export function DropdownMenuSeparator({
  className = '',
  ...props
}: ComponentProps<typeof MenuPrimitive.Separator>) {
  return <MenuPrimitive.Separator className={`my-1 h-px bg-line ${className}`} {...props} />;
}
