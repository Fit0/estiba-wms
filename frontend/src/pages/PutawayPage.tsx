import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useProductsById } from '../api/inbound';
import {
  usePutawayTasks,
  type PutawaySearch,
  type PutawayStatus,
  type PutawayTask,
} from '../api/putaway';
import { useActiveLocations } from '../api/warehouses';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { formatDate, formatDateTime, formatNumber } from '../i18n/format';
import { productLabel } from '../inbound/display';
import { ConfirmPutawayDialog, SplitPutawayDialog } from '../putaway/PutawayDialogs';
import { useActiveWarehouse } from '../warehouse/ActiveWarehouse';

const pageSizes = [10, 30, 100] as const;
const statuses: readonly PutawayStatus[] = ['pending', 'confirmed'];

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

/**
 * The goods waiting at the dock to go to a location, and what was already put away. Operators do this
 * work, so every signed-in user sees it. Status, receipt, page and size live in the address
 * (ADR 0031), and the list asks again every few seconds, so the stock that moves shows by itself.
 */
export function PutawayPage() {
  const { t, language } = useTranslation();
  const { active } = useActiveWarehouse();
  const locations = useActiveLocations(active?.id);
  const [params, setParams] = useSearchParams();
  const [confirming, setConfirming] = useState<PutawayTask | null>(null);
  const [splitting, setSplitting] = useState<PutawayTask | null>(null);

  // Pending is what there is to do, so it is what the screen opens on; `status=all` shows both.
  const requested = params.get('status');
  const status: PutawayStatus | undefined =
    requested === 'all' ? undefined : (statuses.find((value) => value === requested) ?? 'pending');
  const receiptId = params.get('receipt') ?? undefined;
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

  const search: PutawaySearch = {
    page,
    itemsPerPage: pageSize,
    ...(status === undefined ? {} : { status }),
    ...(receiptId === undefined ? {} : { receiptId }),
  };
  const tasks = usePutawayTasks(search);
  const rows = tasks.data?.items ?? [];
  const products = useProductsById(rows.map((task) => task.productId));
  const codes = new Map((locations.data ?? []).map((location) => [location.id, location.code]));
  const code = (id: string | null | undefined): string =>
    id === null || id === undefined ? '—' : (codes.get(id) ?? id.slice(0, 8));

  const columns: Column<PutawayTask>[] = [
    {
      id: 'product',
      header: t('asn.line.product'),
      cell: (task) => productLabel(products, task.productId),
    },
    {
      id: 'units',
      header: t('receive.units'),
      kind: 'number',
      cell: (task) => formatNumber(language, task.units),
    },
    { id: 'lot', header: t('stock.lot'), cell: (task) => task.lot ?? '—' },
    {
      id: 'expiry',
      header: t('stock.expiry'),
      cell: (task) =>
        task.expiryDate === null || task.expiryDate === undefined
          ? '—'
          : formatDate(language, task.expiryDate),
    },
    {
      id: 'from',
      header: t('putaway.from'),
      kind: 'code',
      cell: (task) => code(task.sourceLocationId),
    },
    {
      id: 'to',
      header: t('putaway.to'),
      kind: 'code',
      cell: (task) =>
        task.status === 'confirmed'
          ? code(task.confirmedLocationId)
          : code(task.suggestedLocationId),
    },
    {
      id: 'status',
      header: t('putaway.status'),
      cell: (task) => (
        <span className="inline-flex flex-wrap items-center gap-2">
          <StatusBadge tone={task.status === 'pending' ? 'info' : 'ok'} dot>
            {t(`putaway.status.${task.status}`)}
          </StatusBadge>
          {task.quarantine && <StatusBadge tone="warn">{t('putaway.quarantine')}</StatusBadge>}
          {task.status === 'confirmed' && (
            <span className="text-body-sm text-fg-secondary">
              {task.stockMoved
                ? formatDateTime(language, task.confirmedAt ?? task.createdAt)
                : t('putaway.moving')}
            </span>
          )}
        </span>
      ),
    },
    {
      id: 'actions',
      header: t('putaway.actions'),
      cell: (task) =>
        task.status === 'pending' ? (
          <span className="flex flex-wrap gap-2">
            <Button
              variant="primary"
              aria-label={t('putaway.confirm.open', {
                product: productLabel(products, task.productId),
              })}
              onClick={() => {
                setConfirming(task);
              }}
            >
              {t('putaway.confirm.action')}
            </Button>
            {task.units > 1 && (
              <Button
                aria-label={t('putaway.split.open', {
                  product: productLabel(products, task.productId),
                })}
                onClick={() => {
                  setSplitting(task);
                }}
              >
                {t('putaway.split.action')}
              </Button>
            )}
          </span>
        ) : null,
    },
  ];

  return (
    <>
      <PageHeader title={t('putaway.title')}>{t('putaway.intro')}</PageHeader>

      <div className="mb-gutter flex flex-wrap items-end justify-between gap-2">
        <Select
          label={t('putaway.filter')}
          value={status ?? 'all'}
          onValueChange={(value) => {
            change({ status: value === 'pending' ? undefined : value, page: undefined });
          }}
          options={[
            { value: 'pending', label: t('putaway.filter.pending') },
            { value: 'confirmed', label: t('putaway.filter.confirmed') },
            { value: 'all', label: t('putaway.filter.all') },
          ]}
        />
        {receiptId !== undefined && (
          <p className="flex items-center gap-2 text-body-md text-fg-secondary">
            {t('putaway.receiptFilter')}
            <Link
              to={`/receipts/${receiptId}`}
              className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
            >
              {t('putaway.receiptFilter.open')}
            </Link>
            <Button
              variant="ghost"
              onClick={() => {
                change({ receipt: undefined, page: undefined });
              }}
            >
              {t('putaway.receiptFilter.clear')}
            </Button>
          </p>
        )}
      </div>

      <DataTable
        caption={t('putaway.caption')}
        columns={columns}
        rows={rows}
        rowKey={(task) => task.id}
        status={tasks.isPending ? 'loading' : tasks.isError ? 'error' : 'ready'}
        empty={status === 'pending' ? t('putaway.empty') : t('putaway.empty.filtered')}
        errorMessage={`${t('table.error')} (${describeError(tasks.error)})`}
        onRetry={() => void tasks.refetch()}
        pagination={{
          page,
          pageSize,
          total: tasks.data?.total ?? 0,
          onPageChange: (next) => {
            change({ page: String(next) });
          },
          onPageSizeChange: (size) => {
            change({ size: String(size), page: undefined });
          },
          pageSizes,
        }}
      />

      <ConfirmPutawayDialog
        task={confirming}
        product={confirming === null ? '' : productLabel(products, confirming.productId)}
        locations={locations.data ?? []}
        onClose={() => {
          setConfirming(null);
        }}
      />
      <SplitPutawayDialog
        task={splitting}
        product={splitting === null ? '' : productLabel(products, splitting.productId)}
        onClose={() => {
          setSplitting(null);
        }}
      />
    </>
  );
}
