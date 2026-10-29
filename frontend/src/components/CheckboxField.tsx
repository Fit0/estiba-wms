import { useId, type ComponentProps } from 'react';

/** A labelled checkbox. The whole label is the target, so touch density makes it easy to hit. */
export function CheckboxField({
  label,
  error,
  ref,
  ...props
}: Omit<ComponentProps<'input'>, 'id' | 'type'> & { label: string; error?: string | undefined }) {
  const id = useId();
  const errorId = `${id}-error`;

  return (
    <div className="grid gap-1">
      <label htmlFor={id} className="flex min-h-control items-center gap-2 text-body-md">
        <input
          id={id}
          ref={ref}
          type="checkbox"
          aria-invalid={error === undefined ? undefined : true}
          aria-describedby={error === undefined ? undefined : errorId}
          className="size-4 accent-primary"
          {...props}
        />
        {label}
      </label>
      {error !== undefined && (
        <p id={errorId} className="text-label-md text-bad">
          {error}
        </p>
      )}
    </div>
  );
}
