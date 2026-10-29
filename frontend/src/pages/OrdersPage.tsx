import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useOrders, type Order, type OrderSearch, type OrderStatus } from '../api/orders';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { formatDateTime, formatNumber } from '../i18n/format';
import { orderTone, priorityTone } from '../orders/display';
import { OrderDialog } from '../orders/OrderDialog';

const pageSizes = [10, 30, 100] as const;
const statuses: readonly OrderStatus[] = [
  'registered',
  'partially_allocated',
  'allocated',
  'in_wave',
  'picked',
  'shipped',
  'cancelled',
];

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

/**
 * The orders, the most urgent first as the API sorts them. The status, the page and its size live in
 * the address (ADR 0031). The list asks again every few seconds while it is open.
 */
export function OrdersPage() {
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

  const search: OrderSearch = {
    page,
    itemsPerPage: pageSize,
    ...(status === undefined ? {} : { status }),
  };
  const orders = useOrders(search);

  const columns: Column<Order>[] = [
    {
      id: 'reference',
      header: t('order.reference'),
      kind: 'code',
      cell: (order) => (
        <Link
          to={`/orders/${order.id}`}
          aria-label={t('order.open', { reference: order.reference })}
          className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
        >
          {order.reference}
        </Link>
      ),
    },
    { id: 'customer', header: t('order.customer'), cell: (order) => order.customer },
    {
      id: 'priority',
      header: t('order.priority'),
      cell: (order) => (
        <StatusBadge tone={priorityTone[order.priority]}>
          {t(`order.priority.${order.priority}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'cutOff',
      header: t('order.cutOff'),
      cell: (order) => formatDateTime(language, order.cutOffAt),
    },
    {
      id: 'shortage',
      header: t('order.shortage'),
      kind: 'number',
      cell: (order) => formatNumber(language, order.shortageUnits),
    },
    {
      id: 'status',
      header: t('order.status'),
      cell: (order) => (
        <StatusBadge tone={orderTone[order.status]} dot>
          {t(`order.status.${order.status}`)}
        </StatusBadge>
      ),
    },
  ];

  return (
    <>
      <PageHeader title={t('orders.title')}>{t('orders.intro')}</PageHeader>

      <div className="mb-gutter flex flex-wrap items-end justify-between gap-2">
        <Select
          label={t('orders.filter')}
          value={status ?? 'all'}
          onValueChange={(value) => {
            change({ status: statuses.find((item) => item === value), page: undefined });
          }}
          options={[
            { value: 'all', label: t('orders.filter.all') },
            ...statuses.map((value) => ({ value, label: t(`order.status.${value}`) })),
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
            {t('orders.new')}
          </Button>
        )}
      </div>

      <DataTable
        caption={t('orders.caption')}
        columns={columns}
        rows={orders.data?.items ?? []}
        rowKey={(order) => order.id}
        status={orders.isPending ? 'loading' : orders.isError ? 'error' : 'ready'}
        empty={status === undefined ? t('orders.empty') : t('orders.empty.filtered')}
        errorMessage={`${t('table.error')} (${describeError(orders.error)})`}
        onRetry={() => void orders.refetch()}
        pagination={{
          page,
          pageSize,
          total: orders.data?.total ?? 0,
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
        <OrderDialog
          onClose={() => {
            setCreating(false);
          }}
          onCreated={(order) => {
            setCreating(false);
            void navigate(`/orders/${order.id}`);
          }}
        />
      )}
    </>
  );
}
