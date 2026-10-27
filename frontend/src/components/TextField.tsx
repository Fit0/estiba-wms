import { useId, type ComponentProps, type ReactNode } from 'react';

const inputClass =
  'h-control w-full rounded-control border border-input-border bg-surface-1 px-3 text-body-md text-fg placeholder:text-fg-muted';

/**
 * A labelled input. The label is always there, since an input without one cannot be read by a
 * screen reader, and `action` is for a button that sits inside the field (show the password).
 */
export function TextField({
  label,
  action,
  inputClassName = '',
  ...props
}: Omit<ComponentProps<'input'>, 'id'> & {
  label: string;
  action?: ReactNode;
  /** More classes for the input itself, such as the ring that shows a barcode was read. */
  inputClassName?: string;
}) {
  const id = useId();

  return (
    <div className="grid gap-1">
      <label htmlFor={id} className="text-label-md text-fg-secondary">
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          className={`${inputClass} ${action === undefined ? '' : 'pr-11'} ${inputClassName}`}
          {...props}
        />
        {action !== undefined && (
          <div className="absolute inset-y-0 right-0 flex items-center pr-1">{action}</div>
        )}
      </div>
    </div>
  );
}
