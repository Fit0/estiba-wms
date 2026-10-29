import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useWaves, type Wave, type WaveSearch, type WaveStatus } from '../api/waves';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { formatDateTime, formatNumber } from '../i18n/format';
import { waveName, waveTone } from '../waves/display';
import { CreateWaveDialog } from '../waves/WaveDialogs';

const pageSizes = [10, 30, 100] as const;
const statuses: readonly WaveStatus[] = ['planned', 'released', 'completed', 'cancelled'];

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

/**
 * The waves: groups of orders sent to the floor together. Status, page and size live in the address
 * (ADR 0031) and the list asks again every few seconds, so a wave that completes shows by itself.
 */
export function WavesPage() {
  const { t, language } = useTranslation();
  const { session } = useAuth();
  const navigate = useNavigate();
  const canCreate = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');
  const [creating, setCreating] = useState(false);
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

  const search: WaveSearch = {
    page,
    itemsPerPage: pageSize,
    ...(status === undefined ? {} : { status }),
  };
  const waves = useWaves(search);

  const columns: Column<Wave>[] = [
    {
      id: 'wave',
      header: t('wave.name'),
      kind: 'code',
      cell: (wave) => (
        <Link
          to={`/waves/${wave.id}`}
          aria-label={t('wave.open', { name: waveName(wave.id) })}
          className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
        >
          {waveName(wave.id)}
        </Link>
      ),
    },
    {
      id: 'orders',
      header: t('wave.orders'),
      kind: 'number',
      cell: (wave) => formatNumber(language, wave.orderIds.length),
    },
    {
      id: 'pickLists',
      header: t('wave.pickLists'),
      kind: 'number',
      cell: (wave) => formatNumber(language, wave.pickListIds.length),
    },
    {
      id: 'created',
      header: t('wave.createdAt'),
      cell: (wave) => formatDateTime(language, wave.createdAt),
    },
    {
      id: 'status',
      header: t('wave.status'),
      cell: (wave) => (
        <StatusBadge tone={waveTone[wave.status]} dot>
          {t(`wave.status.${wave.status}`)}
        </StatusBadge>
      ),
    },
  ];

  return (
    <>
      <PageHeader title={t('waves.title')}>{t('waves.intro')}</PageHeader>

      <div className="mb-gutter flex flex-wrap items-end justify-between gap-2">
        <Select
          label={t('waves.filter')}
          value={status ?? 'all'}
          onValueChange={(value) => {
            change({ status: statuses.find((item) => item === value), page: undefined });
          }}
          options={[
            { value: 'all', label: t('waves.filter.all') },
            ...statuses.map((value) => ({ value, label: t(`wave.status.${value}`) })),
          ]}
        />
        {canCreate && (
          <Button
            variant="primary"
            onClick={() => {
              setCreating(true);
            }}
          >
            <Plus aria-hidden="true" className="size-4" />
            {t('waves.new')}
          </Button>
        )}
      </div>

      <DataTable
        caption={t('waves.caption')}
        columns={columns}
        rows={waves.data?.items ?? []}
        rowKey={(wave) => wave.id}
        status={waves.isPending ? 'loading' : waves.isError ? 'error' : 'ready'}
        empty={status === undefined ? t('waves.empty') : t('waves.empty.filtered')}
        errorMessage={`${t('table.error')} (${describeError(waves.error)})`}
        onRetry={() => void waves.refetch()}
        pagination={{
          page,
          pageSize,
          total: waves.data?.total ?? 0,
          onPageChange: (next) => {
            change({ page: String(next) });
          },
          onPageSizeChange: (size) => {
            change({ size: String(size), page: undefined });
          },
          pageSizes,
        }}
      />

      {creating && (
        <CreateWaveDialog
          onClose={() => {
            setCreating(false);
          }}
          onCreated={(wave) => {
            setCreating(false);
            void navigate(`/waves/${wave.id}`);
          }}
        />
      )}
    </>
  );
}
