import { Minus, TrendingDown, TrendingUp, type LucideIcon } from 'lucide-react';
import { useTranslation, type MessageKey } from '../i18n';
import { StatusBadge, type Tone } from './StatusBadge';

export interface Trend {
  readonly direction: 'up' | 'down' | 'flat';
  /** What the change is measured against, with its sign: "+2,4 % vs. the last shift". */
  readonly label: string;
  /** Whether the change is good (`ok`), bad (`bad`) or only a fact (`neutral`). Up is not always good. */
  readonly tone?: Tone;
}

const trendIcons = { up: TrendingUp, down: TrendingDown, flat: Minus } as const;
const trendNames: Record<Trend['direction'], MessageKey> = {
  up: 'trend.up',
  down: 'trend.down',
  flat: 'trend.flat',
};

/**
 * A figure of the operation: what it measures, how much, and how it changed. The figure is big and
 * keeps its width while it changes (`tnum`).
 */
export function KpiCard({
  label,
  value,
  trend,
  icon: Icon,
}: {
  label: string;
  value: string;
  trend?: Trend;
  icon?: LucideIcon;
}) {
  const { t } = useTranslation();
  const TrendIcon = trend === undefined ? Minus : trendIcons[trend.direction];

  return (
    <article className="grid gap-2 rounded-panel border border-line bg-surface-1 p-4">
      <div className="flex items-start justify-between gap-2">
        <p className="text-label-sm text-fg-muted uppercase">{label}</p>
        {Icon !== undefined && <Icon aria-hidden="true" className="size-4 text-fg-muted" />}
      </div>
      <p className="tnum text-headline-lg-mobile font-bold">{value}</p>
      {trend !== undefined && (
        <div>
          <StatusBadge tone={trend.tone ?? 'neutral'}>
            <TrendIcon aria-hidden="true" className="size-3" />
            <span className="sr-only">{t(trendNames[trend.direction])}</span>
            {trend.label}
          </StatusBadge>
        </div>
      )}
    </article>
  );
}
