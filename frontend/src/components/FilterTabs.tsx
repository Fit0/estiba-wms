import { useTranslation } from '../i18n';

export interface FilterOption<Value extends string> {
  readonly value: Value;
  readonly label: string;
  /** How many rows the filter would show. */
  readonly count?: number;
}

/**
 * The filters over a list, one of them on: "all", "low", "out". They are buttons that are pressed or
 * not, and not tabs, because they do not show another panel: they change what one table shows.
 */
export function FilterTabs<Value extends string>({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: readonly FilterOption<Value>[];
  value: Value;
  onChange: (value: Value) => void;
}) {
  const { language } = useTranslation();

  return (
    <div role="group" aria-label={label} className="flex flex-wrap gap-1 border-b border-line">
      {options.map((option) => {
        const on = option.value === value;

        return (
          <button
            key={option.value}
            type="button"
            aria-pressed={on}
            onClick={() => {
              onChange(option.value);
            }}
            className={`-mb-px inline-flex h-control items-center gap-2 border-b-2 px-3 text-body-md font-medium ${on ? 'border-primary text-accent' : 'border-transparent text-fg-secondary hover:text-fg'}`}
          >
            {option.label}
            {option.count !== undefined && (
              <span className="tnum rounded-full bg-surface-2 px-1.5 text-label-sm text-fg-secondary">
                {option.count.toLocaleString(language)}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
