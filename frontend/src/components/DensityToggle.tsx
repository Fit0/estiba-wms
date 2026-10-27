import { Rows3, Rows4, type LucideIcon } from 'lucide-react';
import { useTranslation, type MessageKey } from '../i18n';
import { useDensity } from '../theme/useDensity';
import type { TableDensity } from '../theme/density';
import { Button } from './Button';

const options: readonly { density: TableDensity; icon: LucideIcon; label: MessageKey }[] = [
  { density: 'compact', icon: Rows4, label: 'density.compact' },
  { density: 'comfortable', icon: Rows3, label: 'density.comfortable' },
];

/**
 * Compact rows show more records above the fold, comfortable ones are easier to read. The choice
 * is a person's and is kept, and it applies to every table and control of the app.
 */
export function DensityToggle() {
  const { density, choose } = useDensity();
  const { t } = useTranslation();

  return (
    <div role="group" aria-label={t('density.label')} className="flex gap-1">
      {options.map(({ density: value, icon: Icon, label }) => (
        <Button
          key={value}
          variant={density === value ? 'secondary' : 'ghost'}
          icon
          aria-label={t(label)}
          aria-pressed={density === value}
          onClick={() => {
            choose(value);
          }}
        >
          <Icon aria-hidden="true" className="size-4" />
        </Button>
      ))}
    </div>
  );
}
