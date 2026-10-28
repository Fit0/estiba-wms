import { Hand, Rows2, Rows3, type LucideIcon } from 'lucide-react';
import { useTranslation } from '../i18n';
import { useDensity, type Density } from '../theme/density';
import { Button } from './Button';

const icons: Record<Density, LucideIcon> = { compact: Rows3, comfortable: Rows2, touch: Hand };

/**
 * Cycles the density: compact for the office, comfortable, and touch for gloves (48 px controls).
 * Its name says the density that is on and the one it will switch to. The choice is remembered.
 */
export function DensityToggle() {
  const { density, next, cycle } = useDensity();
  const { t } = useTranslation();
  const Icon = icons[density];

  return (
    <Button
      variant="ghost"
      icon
      onClick={cycle}
      aria-label={t('density.change', {
        current: t(`density.${density}`),
        next: t(`density.${next}`),
      })}
    >
      <Icon aria-hidden="true" className="size-4" />
    </Button>
  );
}
