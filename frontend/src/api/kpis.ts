import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { unwrap } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';

export type Kpis = Schema<'Kpis'>;

/** The periods the dashboard offers, each one counted back from now. */
export const kpiPeriods = ['day', 'week', 'month'] as const;
export type KpiPeriod = (typeof kpiPeriods)[number];

const hours: Record<KpiPeriod, number> = { day: 24, week: 7 * 24, month: 30 * 24 };

/** How often the figures are asked again while the dashboard is open. */
export const kpiRefreshMs = 30_000;

export const kpiKeys = {
  all: ['kpis'] as const,
  period: (period: KpiPeriod) => [...kpiKeys.all, period] as const,
};

/**
 * The start of a period that ends now, as the API reads it: ISO 8601 with a time and no
 * milliseconds (the API refuses a fraction of a second).
 */
export function periodStart(period: KpiPeriod, now: Date = new Date()): string {
  const start = new Date(now.getTime() - hours[period] * 3_600_000);

  return start.toISOString().replace(/\.\d{3}Z$/, 'Z');
}

/**
 * The operating figures of the warehouse for a period that ends now (E07-04). They are asked again
 * every 30 s while the screen is open; the figures on the screen stay while a new period loads.
 */
export function useKpis(period: KpiPeriod, options: { enabled?: boolean } = {}) {
  const api = useApi();

  return useQuery({
    queryKey: kpiKeys.period(period),
    queryFn: async ({ signal }): Promise<Kpis> =>
      unwrap(
        await api.GET('/api/kpis', { params: { query: { from: periodStart(period) } }, signal }),
      ),
    placeholderData: keepPreviousData,
    refetchInterval: kpiRefreshMs,
    enabled: options.enabled ?? true,
  });
}
