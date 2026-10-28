import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { useWarehouses, type Warehouse } from '../api/warehouses';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { useTranslation } from '../i18n';
import { WarehouseDialog } from '../warehouse/WarehouseDialog';

/**
 * The warehouses of the company. Anyone signed in can read them; supervisors and admins add them.
 */
export function WarehousesPage() {
  const { t } = useTranslation();
  const { session } = useAuth();
  const warehouses = useWarehouses();
  const [creating, setCreating] = useState(false);
  const canEdit = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');

  const columns: Column<Warehouse>[] = [
    {
      id: 'code',
      header: t('warehouses.code'),
      kind: 'code',
      cell: (warehouse) => (
        <Link
          to={`/warehouses/${warehouse.id}`}
          aria-label={t('warehouses.open', { name: warehouse.name })}
          className="text-accent underline-offset-2 hover:underline"
        >
          {warehouse.code}
        </Link>
      ),
    },
    { id: 'name', header: t('warehouses.name'), cell: (warehouse) => warehouse.name },
    {
      id: 'zones',
      header: t('warehouses.zones'),
      kind: 'number',
      cell: (warehouse) => warehouse.zones.length,
    },
  ];

  return (
    <>
      <PageHeader title={t('warehouses.title')}>{t('warehouses.intro')}</PageHeader>
      {canEdit && (
        <div className="mb-gutter flex justify-end">
          <Button
            variant="primary"
            onClick={() => {
              setCreating(true);
            }}
          >
            <Plus aria-hidden="true" className="size-4" />
            {t('warehouses.new')}
          </Button>
        </div>
      )}
      <DataTable
        caption={t('warehouses.caption')}
        columns={columns}
        rows={warehouses.data ?? []}
        rowKey={(warehouse) => warehouse.id}
        status={warehouses.isPending ? 'loading' : warehouses.isError ? 'error' : 'ready'}
        empty={t('warehouses.empty')}
        onRetry={() => void warehouses.refetch()}
      />
      <WarehouseDialog open={creating} onOpenChange={setCreating} />
    </>
  );
}
