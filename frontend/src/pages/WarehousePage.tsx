import { ArrowLeft, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { toast } from '../components/ui/Toaster';
import {
  useLocations,
  useSetLocationBlocked,
  useWarehouse,
  type Location,
  type LocationSearch,
} from '../api/warehouses';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { BlockDialog } from '../warehouse/BlockDialog';
import { LocationDialog } from '../warehouse/LocationDialog';
import { parseZones, type Zone } from '../warehouse/schemas';
import { ZoneDialog } from '../warehouse/ZoneDialog';

type StatusFilter = 'all' | 'active' | 'blocked';

const pageSizes = [10, 30, 100] as const;

/**
 * One warehouse: its zones and its locations. The locations come a page at a time from the server.
 * Supervisors and admins add zones and locations and block them; the API refuses anyone else.
 */
export function WarehousePage() {
  const { id = '' } = useParams();
  const { t } = useTranslation();
  const { session } = useAuth();
  const canEdit = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');

  const warehouse = useWarehouse(id);
  const [filter, setFilter] = useState<StatusFilter>('all');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(pageSizes[1]);
  const search: LocationSearch = {
    page,
    itemsPerPage: pageSize,
    ...(filter === 'all' ? {} : { status: filter }),
  };
  const locations = useLocations(id, search);
  const unblock = useSetLocationBlocked(id);

  const [addingZone, setAddingZone] = useState(false);
  const [addingLocation, setAddingLocation] = useState(false);
  const [blocking, setBlocking] = useState<Location | null>(null);

  const zones = useMemo<Zone[]>(
    () => (warehouse.data === undefined ? [] : parseZones(warehouse.data.zones)),
    [warehouse.data],
  );
  const zoneCode = (zoneId: string): string =>
    zones.find((zone) => zone.id === zoneId)?.code ?? '—';

  if (warehouse.isError) {
    return (
      <>
        <PageHeader title={t('warehouse.notFound')} />
        <BackLink />
      </>
    );
  }

  const zoneColumns: Column<Zone>[] = [
    { id: 'code', header: t('zone.code'), kind: 'code', cell: (zone) => zone.code },
    { id: 'name', header: t('zone.name'), cell: (zone) => zone.name },
    {
      id: 'class',
      header: t('zone.storageClass'),
      cell: (zone) => t(`zone.class.${zone.storageClass}`),
    },
    {
      id: 'sequence',
      header: t('zone.pickSequence'),
      kind: 'number',
      cell: (zone) => zone.pickSequence,
    },
  ];

  const locationColumns: Column<Location>[] = [
    { id: 'code', header: t('location.code'), kind: 'code', cell: (location) => location.code },
    { id: 'zone', header: t('location.zone'), cell: (location) => zoneCode(location.zoneId) },
    {
      id: 'type',
      header: t('location.type'),
      cell: (location) => t(`location.type.${location.type}`),
    },
    {
      id: 'status',
      header: t('location.status'),
      cell: (location) => (
        <StatusBadge tone={location.status === 'active' ? 'ok' : 'bad'} dot>
          {t(`location.status.${location.status}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'sequence',
      header: t('location.pickSequence'),
      kind: 'number',
      cell: (location) => location.pickSequence,
    },
    ...(canEdit
      ? [
          {
            id: 'actions',
            header: t('location.actions'),
            cell: (location: Location) =>
              location.status === 'active' ? (
                <Button
                  variant="ghost"
                  onClick={() => {
                    setBlocking(location);
                  }}
                >
                  {t('location.block', { code: location.code })}
                </Button>
              ) : (
                <Button
                  variant="ghost"
                  disabled={unblock.isPending}
                  onClick={() => {
                    unblock.mutate(
                      { id: location.id },
                      {
                        onSuccess: () => {
                          toast.success(t('location.unblocked', { code: location.code }));
                        },
                      },
                    );
                  }}
                >
                  {t('location.unblock', { code: location.code })}
                </Button>
              ),
          },
        ]
      : []),
  ];

  const title =
    warehouse.data === undefined ? '' : `${warehouse.data.code} · ${warehouse.data.name}`;

  return (
    <>
      <BackLink />
      <PageHeader title={title} />

      <section aria-labelledby="zones-title" className="mb-gutter-lg grid gap-gutter">
        <div className="flex items-center justify-between">
          <h2 id="zones-title" className="text-headline-md">
            {t('warehouse.zones.title')}
          </h2>
          {canEdit && (
            <Button
              onClick={() => {
                setAddingZone(true);
              }}
            >
              <Plus aria-hidden="true" className="size-4" />
              {t('zone.new')}
            </Button>
          )}
        </div>
        <DataTable
          caption={t('zone.caption')}
          columns={zoneColumns}
          rows={zones}
          rowKey={(zone) => zone.id}
          status={warehouse.isPending ? 'loading' : 'ready'}
          empty={t('zone.empty')}
        />
      </section>

      <section aria-labelledby="locations-title" className="grid gap-gutter">
        <div className="flex flex-wrap items-end justify-between gap-gutter">
          <h2 id="locations-title" className="text-headline-md">
            {t('warehouse.locations.title')}
          </h2>
          <div className="flex flex-wrap items-end gap-2">
            <Select
              label={t('location.filter')}
              value={filter}
              onValueChange={(value) => {
                setFilter(value === 'active' || value === 'blocked' ? value : 'all');
                setPage(1);
              }}
              options={[
                { value: 'all', label: t('location.filter.all') },
                { value: 'active', label: t('location.status.active') },
                { value: 'blocked', label: t('location.status.blocked') },
              ]}
            />
            {canEdit && (
              <Button
                variant="primary"
                disabled={zones.length === 0}
                onClick={() => {
                  setAddingLocation(true);
                }}
              >
                <Plus aria-hidden="true" className="size-4" />
                {t('location.new')}
              </Button>
            )}
          </div>
        </div>
        {canEdit && zones.length === 0 && !warehouse.isPending && (
          <p className="text-body-md text-fg-secondary">{t('location.needsZone')}</p>
        )}
        <DataTable
          caption={t('location.caption')}
          columns={locationColumns}
          rows={locations.data?.items ?? []}
          rowKey={(location) => location.id}
          status={locations.isPending ? 'loading' : locations.isError ? 'error' : 'ready'}
          empty={t('location.empty')}
          onRetry={() => void locations.refetch()}
          pagination={{
            page,
            pageSize,
            total: locations.data?.total ?? 0,
            onPageChange: setPage,
            onPageSizeChange: (size) => {
              setPageSize(size);
              setPage(1);
            },
            pageSizes,
          }}
        />
      </section>

      <ZoneDialog warehouseId={id} open={addingZone} onOpenChange={setAddingZone} />
      {addingLocation && (
        <LocationDialog warehouseId={id} zones={zones} open onOpenChange={setAddingLocation} />
      )}
      <BlockDialog
        warehouseId={id}
        location={blocking}
        onClose={() => {
          setBlocking(null);
        }}
      />
    </>
  );
}

function BackLink() {
  const { t } = useTranslation();

  return (
    <Link
      to="/warehouses"
      className="mb-gutter inline-flex min-h-control items-center gap-1 text-body-md text-accent hover:underline"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('warehouse.back')}
    </Link>
  );
}
