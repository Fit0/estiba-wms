import { RefreshCw } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { describeError } from '../api/describeError';
import { kpiPeriods, useKpis, type KpiPeriod, type Kpis } from '../api/kpis';
import { Button } from '../components/Button';
import { KpiCard } from '../components/KpiCard';
import { formatDate, formatNumber } from '../i18n/format';
import { useTranslation, type Language, type MessageKey } from '../i18n';

const periodLabels: Record<KpiPeriod, MessageKey> = {
  day: 'dashboard.period.day',
  week: 'dashboard.period.week',
  month: 'dashboard.period.month',
};

const dash = '—';

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section aria-label={title} className="grid gap-3">
      <h2 className="text-headline-sm">{title}</h2>
      <div className="grid gap-gutter sm:grid-cols-2 xl:grid-cols-4">{children}</div>
    </section>
  );
}

function absent(value: number | null | undefined): value is null | undefined {
  return value === undefined || value === null;
}

/** A ratio the API leaves out when there was nothing to divide by shows a dash, never a zero. */
function ratio(language: Language, value: number | null | undefined, suffix = ''): string {
  return absent(value)
    ? dash
    : `${formatNumber(language, value, { maximumFractionDigits: 1 })}${suffix}`;
}

function Figures({ kpis }: { kpis: Kpis | undefined }) {
  const { language, t } = useTranslation();
  const loading = kpis === undefined;
  const count = (value: number | undefined): string =>
    value === undefined ? dash : formatNumber(language, value);
  const noData = { direction: 'flat', label: t('dashboard.noData') } as const;
  const picking = kpis?.picking;

  return (
    <>
      <Section title={t('dashboard.orders')}>
        <KpiCard
          label={t('dashboard.orders.open')}
          value={count(kpis?.orders.open)}
          trend={{ direction: 'flat', label: t('dashboard.now') }}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.orders.registered')}
          value={count(kpis?.orders.registered)}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.orders.shipped')}
          value={count(kpis?.orders.shipped)}
          loading={loading}
        />
      </Section>

      <Section title={t('dashboard.picking')}>
        <KpiCard
          label={t('dashboard.picking.lists')}
          value={count(picking?.listsCompleted)}
          {...(picking === undefined
            ? {}
            : {
                trend: {
                  direction: 'flat',
                  label: t('dashboard.picking.lines', {
                    count: formatNumber(language, picking.linesPicked),
                  }),
                },
              })}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.picking.rate')}
          value={ratio(language, picking?.linesPerHour)}
          {...(picking !== undefined && absent(picking.linesPerHour) ? { trend: noData } : {})}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.picking.minutes')}
          value={
            absent(picking?.averageListMinutes)
              ? dash
              : t('dashboard.picking.minutesValue', {
                  value: ratio(language, picking.averageListMinutes),
                })
          }
          {...(picking !== undefined && absent(picking.averageListMinutes)
            ? { trend: noData }
            : {})}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.picking.short')}
          value={ratio(language, picking?.shortPickPercent, ' %')}
          {...(picking === undefined
            ? {}
            : absent(picking.shortPickPercent)
              ? { trend: noData }
              : {
                  trend: {
                    direction: 'flat',
                    label: t('dashboard.picking.shortDetail', {
                      units: formatNumber(language, picking.unitsShort),
                      lines: formatNumber(language, picking.shortPickLines),
                    }),
                  },
                })}
          loading={loading}
        />
      </Section>

      <Section title={t('dashboard.stock')}>
        <KpiCard
          label={t('dashboard.stock.onHand')}
          value={count(kpis?.stock.onHand)}
          trend={{ direction: 'flat', label: t('dashboard.now') }}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.stock.available')}
          value={count(kpis?.stock.available)}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.stock.quarantine')}
          value={count(kpis?.stock.quarantine)}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.stock.damaged')}
          value={count(kpis?.stock.damaged)}
          loading={loading}
        />
        <KpiCard
          label={t('dashboard.stock.onHold')}
          value={count(kpis?.stock.onHold)}
          loading={loading}
        />
      </Section>

      <Section title={t('dashboard.locations')}>
        <KpiCard
          label={t('dashboard.locations.occupancy')}
          value={ratio(language, kpis?.locations.occupancyPercent, ' %')}
          {...(kpis === undefined
            ? {}
            : {
                trend: {
                  direction: 'flat',
                  label: t('dashboard.locations.detail', {
                    occupied: formatNumber(language, kpis.locations.occupied),
                    storage: formatNumber(language, kpis.locations.storage),
                  }),
                },
              })}
          loading={loading}
        />
      </Section>
    </>
  );
}

/**
 * The figures a supervisor looks at to run the warehouse (E07-05): orders, picking, stock and how full
 * the storage is, for the last 24 hours, 7 days or 30 days. They are asked again every 30 s. The
 * API refuses anyone below supervisor, so the caller only shows this to those who may see it.
 */
export function Dashboard() {
  const { language, t } = useTranslation();
  const [period, setPeriod] = useState<KpiPeriod>('week');
  const kpis = useKpis(period);

  return (
    <section aria-label={t('dashboard.label')} className="grid gap-gutter-lg">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div role="group" aria-label={t('dashboard.period')} className="flex gap-1">
          {kpiPeriods.map((option) => (
            <Button
              key={option}
              variant={option === period ? 'primary' : 'secondary'}
              aria-pressed={option === period}
              onClick={() => {
                setPeriod(option);
              }}
            >
              {t(periodLabels[option])}
            </Button>
          ))}
        </div>
        <div className="flex items-center gap-2">
          {kpis.dataUpdatedAt > 0 && (
            <span className="text-body-sm text-fg-secondary">
              {t('dashboard.updated', {
                time: formatDate(language, new Date(kpis.dataUpdatedAt), { timeStyle: 'short' }),
              })}
            </span>
          )}
          <Button
            icon
            variant="ghost"
            aria-label={t('dashboard.refresh')}
            disabled={kpis.isFetching}
            onClick={() => void kpis.refetch()}
          >
            <RefreshCw
              aria-hidden="true"
              className={`size-4 ${kpis.isFetching ? 'animate-spin' : ''}`}
            />
          </Button>
        </div>
      </div>

      {kpis.isError && (
        <div
          role="alert"
          className="flex flex-wrap items-center gap-3 rounded-panel border border-bad-line bg-bad-fill p-4 text-body-md text-bad"
        >
          <p>
            {t('dashboard.error')} ({describeError(kpis.error)})
          </p>
          <Button onClick={() => void kpis.refetch()}>{t('common.retry')}</Button>
        </div>
      )}

      <Figures kpis={kpis.data} />
    </section>
  );
}
