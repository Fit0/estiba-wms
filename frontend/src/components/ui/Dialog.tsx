import { X } from 'lucide-react';
import { Dialog as DialogPrimitive } from 'radix-ui';
import type { ComponentProps, ReactNode } from 'react';
import { useTranslation } from '../../i18n';

export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogClose = DialogPrimitive.Close;

/**
 * A modal window. Radix traps the focus inside, closes with Escape, gives the focus back to what
 * opened it and hides the rest of the page from screen readers. A dialog always has a `title` (the
 * name that is read when it opens) and may have a `description`.
 */
export function DialogContent({
  title,
  description,
  children,
  className = '',
  ...props
}: Omit<ComponentProps<typeof DialogPrimitive.Content>, 'title'> & {
  title: string;
  description?: string;
  children?: ReactNode;
}) {
  const { t } = useTranslation();

  return (
    <DialogPrimitive.Portal>
      <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-canvas/80" />
      <DialogPrimitive.Content
        className={`fixed top-1/2 left-1/2 z-50 grid max-h-[90vh] w-[calc(100vw-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 overflow-auto rounded-panel border border-line-strong bg-surface-2 p-5 shadow-overlay ${className}`}
        {...(description === undefined ? { 'aria-describedby': undefined } : {})}
        {...props}
      >
        <div className="grid gap-1 pr-12">
          <DialogPrimitive.Title className="font-display text-headline-md">
            {title}
          </DialogPrimitive.Title>
          {description !== undefined && (
            <DialogPrimitive.Description className="text-body-md text-fg-secondary">
              {description}
            </DialogPrimitive.Description>
          )}
        </div>
        {children}
        <DialogPrimitive.Close
          aria-label={t('ui.close')}
          className="absolute top-3 right-3 inline-flex size-control items-center justify-center rounded-control text-fg-secondary hover:bg-surface-3 hover:text-fg"
        >
          <X aria-hidden="true" className="size-4" />
        </DialogPrimitive.Close>
      </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
  );
}
