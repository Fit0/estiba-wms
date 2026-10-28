import { Check, ChevronDown } from 'lucide-react';
import { Select as SelectPrimitive } from 'radix-ui';
import { useId } from 'react';

export interface SelectOption {
  readonly value: string;
  readonly label: string;
}

/**
 * A list to choose one value from, with a visible label. Radix draws the list in a layer of its own
 * and keeps the keyboard behaviour of a native select (type to jump, arrows, Home and End).
 */
export function Select({
  label,
  value,
  onValueChange,
  options,
  placeholder,
}: {
  label: string;
  value: string | undefined;
  onValueChange: (value: string) => void;
  options: readonly SelectOption[];
  placeholder?: string;
}) {
  const id = useId();

  return (
    <div className="grid gap-1">
      <label htmlFor={id} className="text-label-md text-fg-secondary">
        {label}
      </label>
      <SelectPrimitive.Root
        {...(value === undefined ? {} : { value })}
        onValueChange={onValueChange}
      >
        <SelectPrimitive.Trigger
          id={id}
          className="inline-flex h-control min-w-44 items-center justify-between gap-2 rounded-control border border-input-border bg-surface-1 px-3 text-body-md text-fg data-[placeholder]:text-fg-secondary"
        >
          <SelectPrimitive.Value {...(placeholder === undefined ? {} : { placeholder })} />
          <SelectPrimitive.Icon>
            <ChevronDown aria-hidden="true" className="size-4" />
          </SelectPrimitive.Icon>
        </SelectPrimitive.Trigger>
        <SelectPrimitive.Portal>
          <SelectPrimitive.Content
            position="popper"
            sideOffset={4}
            className="z-50 max-h-72 min-w-(--radix-select-trigger-width) overflow-hidden rounded-panel border border-line-strong bg-surface-2 shadow-popover"
          >
            <SelectPrimitive.Viewport className="p-1">
              {options.map((option) => (
                <SelectPrimitive.Item
                  key={option.value}
                  value={option.value}
                  className="flex min-h-8 cursor-default items-center justify-between gap-2 rounded-control px-2 text-body-md outline-none select-none data-[highlighted]:bg-surface-3"
                >
                  <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
                  <SelectPrimitive.ItemIndicator>
                    <Check aria-hidden="true" className="size-4" />
                  </SelectPrimitive.ItemIndicator>
                </SelectPrimitive.Item>
              ))}
            </SelectPrimitive.Viewport>
          </SelectPrimitive.Content>
        </SelectPrimitive.Portal>
      </SelectPrimitive.Root>
    </div>
  );
}
