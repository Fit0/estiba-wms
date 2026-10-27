import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import { toneText, type Tone } from './tone';

export interface Trend {
  direction: 'up' | 'down' | 'flat';
  label: string;
  tone?: Tone;
}

const trendIcon = { up: TrendingUp, down: TrendingDown, flat: Minus } as const;

/**
 * One figure of a dashboard, with what it measures. The trend names its direction in text, so the
 * arrow and the color are a help and not the message. While `loading`, the card keeps its size.
 */
export function KpiCard({
  label,
  value,
  trend,
  loading = false,
}: {
  label: string;
  value: string;
  trend?: Trend;
  loading?: boolean;
}) {
  const Icon = trend === undefined ? Minus : trendIcon[trend.direction];

  return (
    <article
      aria-busy={loading}
      className="grid min-h-28 content-between gap-2 rounded-panel border border-line bg-surface-1 p-4"
    >
      <h3 className="font-sans text-label-md text-fg-secondary">{label}</h3>
      {loading ? (
        <div aria-hidden="true" className="h-8 w-24 animate-pulse rounded-control bg-surface-3" />
      ) : (
        <p className="font-display text-display tnum">{value}</p>
      )}
      {!loading && trend !== undefined && (
        <p
          data-tone={trend.tone ?? 'neutral'}
          className={`flex items-center gap-1 text-body-sm ${toneText[trend.tone ?? 'neutral']}`}
        >
          <Icon aria-hidden="true" className="size-4" />
          {trend.label}
        </p>
      )}
    </article>
  );
}
