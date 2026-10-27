import { useState } from 'react';
import { Button } from '../components/Button';
import { DataTable, type Column, type Sort } from '../components/DataTable';
import { KpiCard } from '../components/KpiCard';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import type { Tone } from '../components/tone';
import { useTranslation, type MessageKey } from '../i18n';

type StockStatus = 'available' | 'quarantine' | 'blocked' | 'inTransit' | 'draft';

interface SampleRow {
  id: string;
  sku: string;
  product: string;
  location: string;
  status: StockStatus;
  quantity: number;
}

const statuses: Record<StockStatus, { tone: Tone; label: MessageKey }> = {
  available: { tone: 'ok', label: 'design.status.available' },
  quarantine: { tone: 'warn', label: 'design.status.quarantine' },
  blocked: { tone: 'bad', label: 'design.status.blocked' },
  inTransit: { tone: 'info', label: 'design.status.inTransit' },
  draft: { tone: 'neutral', label: 'design.status.draft' },
};

const statusOrder = Object.keys(statuses) as StockStatus[];
const products = ['Camiseta', 'Pantalón', 'Chaqueta', 'Sudadera', 'Falda', 'Camisa'];

/** Sixty rows that look like stock, always the same so the page and its test are stable. */
const sampleRows: SampleRow[] = Array.from({ length: 60 }, (_, index) => ({
  id: String(index + 1),
  sku: `SKU-${String(1000 + index * 7)}`,
  product: `${products[index % products.length] ?? ''} ${String(Math.floor(index / products.length) + 1)}`,
  location: `A-${String((index % 9) + 1).padStart(2, '0')}-${String((index % 4) + 1).padStart(2, '0')}`,
  status: statusOrder[index % statusOrder.length] ?? 'available',
  quantity: ((index * 37) % 500) + 1,
}));

type TableState = 'ready' | 'loading' | 'error' | 'empty';
const tableStates: TableState[] = ['ready', 'loading', 'error', 'empty'];

/**
 * The components of the design system with example data, in whatever theme the app is in. It is the
 * place to look at a component before a screen uses it, and it only exists in development (`routes.tsx`).
 * The table sorts, selects and pages on the client here; a real screen asks the server instead.
 */
export function DesignGalleryPage() {
  const { t, language } = useTranslation();
  const [state, setState] = useState<TableState>('ready');
  const [sort, setSort] = useState<Sort | undefined>(undefined);
  const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);

  const collator = new Intl.Collator(language, { numeric: true });
  const sorted = [...sampleRows];
  if (sort !== undefined) {
    const sign = sort.direction === 'asc' ? 1 : -1;
    const key = sort.columnId as keyof SampleRow;
    sorted.sort((a, b) => sign * collator.compare(String(a[key]), String(b[key])));
  }
  const rows = state === 'empty' ? [] : sorted;
  const visible = rows.slice((page - 1) * pageSize, page * pageSize);

  const columns: Column<SampleRow>[] = [
    {
      id: 'sku',
      header: t('design.column.sku'),
      kind: 'code',
      sortable: true,
      cell: (row) => row.sku,
    },
    {
      id: 'product',
      header: t('design.column.product'),
      sortable: true,
      cell: (row) => row.product,
    },
    {
      id: 'location',
      header: t('design.column.location'),
      kind: 'code',
      cell: (row) => row.location,
    },
    {
      id: 'status',
      header: t('design.column.status'),
      cell: (row) => (
        <StatusBadge tone={statuses[row.status].tone} dot>
          {t(statuses[row.status].label)}
        </StatusBadge>
      ),
    },
    {
      id: 'quantity',
      header: t('design.column.quantity'),
      kind: 'number',
      sortable: true,
      cell: (row) => new Intl.NumberFormat(language).format(row.quantity),
    },
  ];

  return (
    <div className="grid gap-gutter-lg">
      <PageHeader title={t('design.title')}>{t('design.intro')}</PageHeader>

      <section aria-labelledby="design-kpis" className="grid gap-gutter">
        <h2 id="design-kpis" className="text-headline-md">
          {t('design.kpis')}
        </h2>
        <div className="grid gap-gutter sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard
            label={t('design.kpi.units')}
            value={new Intl.NumberFormat(language).format(12480)}
            trend={{ direction: 'up', label: t('design.kpi.units.trend'), tone: 'ok' }}
          />
          <KpiCard
            label={t('design.kpi.locations')}
            value="86 %"
            trend={{ direction: 'flat', label: t('design.kpi.locations.trend') }}
          />
          <KpiCard
            label={t('design.kpi.expiring')}
            value="14"
            trend={{ direction: 'up', label: t('design.kpi.expiring.trend'), tone: 'warn' }}
          />
          <KpiCard label={t('design.kpi.units')} value="" loading />
        </div>
      </section>

      <section aria-labelledby="design-badges" className="grid gap-gutter">
        <h2 id="design-badges" className="text-headline-md">
          {t('design.badges')}
        </h2>
        <div className="flex flex-wrap gap-2">
          {statusOrder.map((status) => (
            <StatusBadge key={status} tone={statuses[status].tone} dot>
              {t(statuses[status].label)}
            </StatusBadge>
          ))}
        </div>
      </section>

      <section aria-labelledby="design-table" className="grid gap-gutter">
        <h2 id="design-table" className="text-headline-md">
          {t('design.table')}
        </h2>
        <div role="group" aria-label={t('design.state')} className="flex flex-wrap gap-2">
          {tableStates.map((option) => (
            <Button
              key={option}
              variant={option === state ? 'primary' : 'secondary'}
              aria-pressed={option === state}
              onClick={() => {
                setState(option);
                setPage(1);
              }}
            >
              {t(`design.state.${option}`)}
            </Button>
          ))}
        </div>
        <DataTable
          caption={t('design.table')}
          columns={columns}
          rows={visible}
          rowKey={(row) => row.id}
          status={state === 'empty' ? 'ready' : state}
          onRetry={() => {
            setState('ready');
          }}
          sort={sort}
          onSortChange={(next) => {
            setSort(next);
            setPage(1);
          }}
          selection={{ selected, onChange: setSelected, rowLabel: (row) => row.sku }}
          pagination={{
            page,
            pageSize,
            total: rows.length,
            onPageChange: setPage,
            onPageSizeChange: (size) => {
              setPageSize(size);
              setPage(1);
            },
          }}
        />
      </section>
    </div>
  );
}
