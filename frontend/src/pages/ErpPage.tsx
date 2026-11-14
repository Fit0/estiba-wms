import { RotateCw } from 'lucide-react';
import { useSearchParams } from 'react-router';
import {
  useErpConfirmations,
  useErpImports,
  useRetryConfirmation,
  type ConfirmationSearch,
  type ConfirmationStatus,
  type ErpConfirmation,
  type ErpImport,
  type ImportResult,
  type ImportSearch,
} from '../api/erp';
import { describeError } from '../api/describeError';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import type { Tone } from '../components/tone';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { formatDateTime, formatNumber } from '../i18n/format';

const pageSize = 10;
const statuses: readonly ConfirmationStatus[] = ['pending', 'sent', 'failed'];
const results: readonly ImportResult[] = ['processed', 'rejected'];

const statusTone: Record<ConfirmationStatus, Tone> = {
  pending: 'info',
  sent: 'ok',
  failed: 'bad',
};
const resultTone: Record<ImportResult, Tone> = { processed: 'ok', rejected: 'bad' };

function positiveInt(value: string | null): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : 1;
}

/**
 * The log of the integration with the ERP (ADR 0050): what goes out (the confirmations of shipments,
 * with the failed ones to retry) and what comes in (the files of orders and what became of each).
 * Filters and pages live in the address, so a link shows what the supervisor was looking at.
 */
export function ErpPage() {
  const { t, language } = useTranslation();
  const [params, setParams] = useSearchParams();

  const status = statuses.find((value) => value === params.get('status'));
  const result = results.find((value) => value === params.get('result'));
  const confirmationPage = positiveInt(params.get('cpage'));
  const importPage = positiveInt(params.get('ipage'));

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

  const confirmationSearch: ConfirmationSearch = {
    page: confirmationPage,
    itemsPerPage: pageSize,
    ...(status === undefined ? {} : { status }),
  };
  const importSearch: ImportSearch = {
    page: importPage,
    itemsPerPage: pageSize,
    ...(result === undefined ? {} : { result }),
  };
  const confirmations = useErpConfirmations(confirmationSearch);
  const imports = useErpImports(importSearch);
  const retry = useRetryConfirmation();

  const confirmationColumns: Column<ErpConfirmation>[] = [
    { id: 'order', header: t('erp.order'), kind: 'code', cell: (row) => row.orderReference },
    {
      id: 'carrier',
      header: t('erp.carrier'),
      cell: (row) =>
        row.trackingNumber === null || row.trackingNumber === undefined
          ? row.carrier
          : `${row.carrier} · ${row.trackingNumber}`,
    },
    {
      id: 'status',
      header: t('erp.status'),
      cell: (row) => (
        <StatusBadge tone={statusTone[row.status]} dot>
          {t(`erp.status.${row.status}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'attempts',
      header: t('erp.attempts'),
      kind: 'number',
      cell: (row) => formatNumber(language, row.attempts),
    },
    {
      id: 'when',
      header: t('erp.when'),
      cell: (row) => formatDateTime(language, row.sentAt ?? row.createdAt),
    },
    {
      id: 'error',
      header: t('erp.error'),
      cell: (row) => row.lastError ?? '',
    },
    {
      id: 'actions',
      header: t('erp.actions'),
      cell: (row) =>
        row.status === 'failed' ? (
          <Button
            variant="secondary"
            disabled={retry.isPending}
            aria-label={t('erp.retry.for', { order: row.orderReference })}
            onClick={() => {
              retry.mutate(row.shipmentId, {
                onSuccess: () => {
                  toast.success(t('erp.retry.done'));
                },
                onError: (error) => {
                  toast.error(`${t('erp.retry.failed')} (${describeError(error)})`);
                },
              });
            }}
          >
            <RotateCw aria-hidden="true" className="size-4" />
            {t('erp.retry')}
          </Button>
        ) : null,
    },
  ];

  const importColumns: Column<ErpImport>[] = [
    { id: 'file', header: t('erp.file'), kind: 'code', cell: (row) => row.fileName },
    {
      id: 'result',
      header: t('erp.result'),
      cell: (row) => (
        <StatusBadge tone={resultTone[row.result]} dot>
          {t(`erp.result.${row.result}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'orders',
      header: t('erp.orders'),
      kind: 'number',
      cell: (row) => formatNumber(language, row.orders),
    },
    { id: 'when', header: t('erp.when'), cell: (row) => formatDateTime(language, row.recordedAt) },
    {
      id: 'detail',
      header: t('erp.detail'),
      cell: (row) => row.message ?? '',
    },
  ];

  return (
    <>
      <PageHeader title={t('erp.title')}>{t('erp.intro')}</PageHeader>

      <section className="mb-8">
        <h2 className="mb-2 text-headline-md">{t('erp.confirmations')}</h2>
        <div className="mb-gutter">
          <Select
            label={t('erp.status')}
            value={status ?? 'all'}
            onValueChange={(value) => {
              change({ status: statuses.find((item) => item === value), cpage: undefined });
            }}
            options={[
              { value: 'all', label: t('erp.filter.all') },
              ...statuses.map((value) => ({ value, label: t(`erp.status.${value}`) })),
            ]}
          />
        </div>
        <DataTable
          caption={t('erp.confirmations')}
          columns={confirmationColumns}
          rows={confirmations.data?.items ?? []}
          rowKey={(row) => row.shipmentId}
          status={confirmations.isPending ? 'loading' : confirmations.isError ? 'error' : 'ready'}
          empty={t('erp.confirmations.empty')}
          errorMessage={`${t('table.error')} (${describeError(confirmations.error)})`}
          onRetry={() => void confirmations.refetch()}
          pagination={{
            label: t('erp.confirmations.pagination'),
            page: confirmationPage,
            pageSize,
            total: confirmations.data?.total ?? 0,
            onPageChange: (next) => {
              change({ cpage: String(next) });
            },
          }}
        />
      </section>

      <section>
        <h2 className="mb-2 text-headline-md">{t('erp.imports')}</h2>
        <div className="mb-gutter">
          <Select
            label={t('erp.result')}
            value={result ?? 'all'}
            onValueChange={(value) => {
              change({ result: results.find((item) => item === value), ipage: undefined });
            }}
            options={[
              { value: 'all', label: t('erp.filter.all') },
              ...results.map((value) => ({ value, label: t(`erp.result.${value}`) })),
            ]}
          />
        </div>
        <DataTable
          caption={t('erp.imports')}
          columns={importColumns}
          rows={imports.data?.items ?? []}
          rowKey={(row) => row.id}
          status={imports.isPending ? 'loading' : imports.isError ? 'error' : 'ready'}
          empty={t('erp.imports.empty')}
          errorMessage={`${t('table.error')} (${describeError(imports.error)})`}
          onRetry={() => void imports.refetch()}
          pagination={{
            label: t('erp.imports.pagination'),
            page: importPage,
            pageSize,
            total: imports.data?.total ?? 0,
            onPageChange: (next) => {
              change({ ipage: String(next) });
            },
          }}
        />
      </section>
    </>
  );
}
