import { Link, useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useAsns, type Asn, type AsnSearch, type AsnStatus } from '../api/inbound';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { formatDateTime } from '../i18n/format';
import { asnTone } from '../inbound/display';

const pageSizes = [10, 30, 100] as const;
const statuses: readonly AsnStatus[] = [
  'expected',
  'partially_received',
  'received',
  'closed',
  'cancelled',
];

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

/**
 * The deliveries the suppliers announced. The status, the page and its size live in the address,
 * like in the catalogue (ADR 0031), and the server does the filtering and the paging.
 */
export function AsnsPage() {
  const { t, language } = useTranslation();
  const [params, setParams] = useSearchParams();

  const status = statuses.find((value) => value === params.get('status'));
  const page = positiveInt(params.get('page'), 1);
  const requestedSize = positiveInt(params.get('size'), pageSizes[1]);
  const pageSize = pageSizes.find((size) => size === requestedSize) ?? pageSizes[1];

  function change(values: Record<string, string | undefined>): void {
    setParams(
      (current) => {
        const next = new URLSearchParams(current);
        for (const [key, value] of Object.entries(values)) {
          if (value === undefined) {
            next.delete(key);
          } else {
            next.set(key, value);
          }
        }

        return next;
      },
      { replace: true },
    );
  }

  const search: AsnSearch = {
    page,
    itemsPerPage: pageSize,
    ...(status === undefined ? {} : { status }),
  };
  const asns = useAsns(search);

  const columns: Column<Asn>[] = [
    {
      id: 'reference',
      header: t('asn.reference'),
      kind: 'code',
      cell: (asn) => (
        <Link
          to={`/inbound/${asn.id}`}
          aria-label={t('asn.open', { reference: asn.reference })}
          className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
        >
          {asn.reference}
        </Link>
      ),
    },
    { id: 'supplier', header: t('asn.supplier'), cell: (asn) => asn.supplier },
    {
      id: 'date',
      header: t('asn.expectedDate'),
      cell: (asn) => formatDateTime(language, asn.expectedDate),
    },
    {
      id: 'lines',
      header: t('asn.lines'),
      kind: 'number',
      cell: (asn) => asn.lines.length,
    },
    {
      id: 'status',
      header: t('asn.status'),
      cell: (asn) => (
        <StatusBadge tone={asnTone[asn.status]} dot>
          {t(`asn.status.${asn.status}`)}
        </StatusBadge>
      ),
    },
  ];

  return (
    <>
      <PageHeader title={t('inbound.title')}>{t('inbound.intro')}</PageHeader>

      <div className="mb-gutter flex flex-wrap items-end gap-2">
        <Select
          label={t('inbound.filter')}
          value={status ?? 'all'}
          onValueChange={(value) => {
            change({ status: statuses.find((item) => item === value), page: undefined });
          }}
          options={[
            { value: 'all', label: t('inbound.filter.all') },
            ...statuses.map((value) => ({ value, label: t(`asn.status.${value}`) })),
          ]}
        />
      </div>

      <DataTable
        caption={t('inbound.caption')}
        columns={columns}
        rows={asns.data?.items ?? []}
        rowKey={(asn) => asn.id}
        status={asns.isPending ? 'loading' : asns.isError ? 'error' : 'ready'}
        empty={status === undefined ? t('inbound.empty') : t('inbound.empty.filtered')}
        errorMessage={`${t('table.error')} (${describeError(asns.error)})`}
        onRetry={() => void asns.refetch()}
        pagination={{
          page,
          pageSize,
          total: asns.data?.total ?? 0,
          onPageChange: (next) => {
            change({ page: String(next) });
          },
          onPageSizeChange: (size) => {
            change({ size: String(size), page: undefined });
          },
          pageSizes,
        }}
      />
    </>
  );
}
