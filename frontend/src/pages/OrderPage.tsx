import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { useProductsById } from '../api/inbound';
import { useChangeOrder, useOrder, type OrderAction } from '../api/orders';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Fact, Facts } from '../catalog/Facts';
import { Button } from '../components/Button';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { DataTable, type Column } from '../components/DataTable';
import { failureText } from '../components/FormDialog';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { formatDateTime, formatNumber } from '../i18n/format';
import { productLabel } from '../inbound/display';
import {
  canAcceptBackorder,
  canAllocate,
  canCancel,
  orderTone,
  priorityTone,
} from '../orders/display';
import { parseOrderLines, type OrderLine } from '../orders/schemas';

/**
 * One order: its data, its lines (ordered, reserved, picked, shipped and what is short) and, for a
 * supervisor, the ways to move it on: reserve stock, accept the backorder or cancel. The page asks
 * again every few seconds, so what happens on the floor shows without a reload.
 */
export function OrderPage() {
  const { id = '' } = useParams();
  const { t, language } = useTranslation();
  const { session } = useAuth();
  const canChange = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');
  const order = useOrder(id);
  const change = useChangeOrder(id);
  const [cancelling, setCancelling] = useState(false);

  const lines = useMemo(
    () => (order.data === undefined ? [] : parseOrderLines(order.data.lines)),
    [order.data],
  );
  const products = useProductsById(lines.map((line) => line.productId));

  if (order.isError) {
    return (
      <>
        <BackLink />
        <PageHeader title={t('order.notFound')} />
      </>
    );
  }

  const data = order.data;
  const number = (value: number): string => formatNumber(language, value);

  function run(action: OrderAction): void {
    change.mutate(action, {
      onSuccess: (updated) => {
        setCancelling(false);
        toast.success(t(`order.${action}.done`, { reference: updated.reference }));
      },
    });
  }

  const columns: Column<OrderLine>[] = [
    {
      id: 'product',
      header: t('order.line.product'),
      cell: (line) => productLabel(products, line.productId),
    },
    {
      id: 'ordered',
      header: t('order.line.ordered'),
      kind: 'number',
      cell: (line) => number(line.orderedUnits),
    },
    {
      id: 'allocated',
      header: t('order.line.allocated'),
      kind: 'number',
      cell: (line) => number(line.allocatedUnits),
    },
    {
      id: 'shortage',
      header: t('order.shortage'),
      kind: 'number',
      cell: (line) => number(line.shortageUnits),
    },
    {
      id: 'picked',
      header: t('order.line.picked'),
      kind: 'number',
      cell: (line) => number(line.pickedUnits),
    },
    {
      id: 'shipped',
      header: t('order.line.shipped'),
      kind: 'number',
      cell: (line) => number(line.shippedUnits),
    },
  ];

  return (
    <>
      <BackLink />
      <PageHeader title={data === undefined ? '' : `${data.reference} · ${data.customer}`}>
        {data === undefined ? undefined : (
          <StatusBadge tone={orderTone[data.status]} dot>
            {t(`order.status.${data.status}`)}
          </StatusBadge>
        )}
      </PageHeader>

      {data !== undefined && (
        <>
          {canChange && (
            <div className="mb-gutter flex flex-wrap justify-end gap-2">
              {canAllocate(data.status) && (
                <Button
                  variant="primary"
                  disabled={change.isPending}
                  onClick={() => {
                    run('allocate');
                  }}
                >
                  {t('order.allocate')}
                </Button>
              )}
              {canAcceptBackorder(data.status, data.backorderAccepted) && (
                <Button
                  disabled={change.isPending}
                  onClick={() => {
                    run('accept-backorder');
                  }}
                >
                  {t('order.acceptBackorder')}
                </Button>
              )}
              {canCancel(data.status) && (
                <Button
                  onClick={() => {
                    change.reset();
                    setCancelling(true);
                  }}
                >
                  {t('order.cancel')}
                </Button>
              )}
            </div>
          )}
          {change.isError && !cancelling && (
            <p role="alert" className="mb-gutter text-body-md text-bad">
              {failureText(change.error, t)}
            </p>
          )}

          <div className="mb-gutter-lg grid gap-gutter-lg">
            <section aria-labelledby="order-data-title" className="grid gap-gutter">
              <h2 id="order-data-title" className="text-headline-md">
                {t('order.section.data')}
              </h2>
              <Facts>
                <Fact term={t('order.priority')}>
                  <StatusBadge tone={priorityTone[data.priority]}>
                    {t(`order.priority.${data.priority}`)}
                  </StatusBadge>
                </Fact>
                <Fact term={t('order.cutOff')}>{formatDateTime(language, data.cutOffAt)}</Fact>
                <Fact term={t('order.registeredAt')}>
                  {formatDateTime(language, data.registeredAt)}
                </Fact>
                <Fact term={t('order.shipTo')}>
                  {t('order.shipTo.value', {
                    street: data.shipTo.street,
                    postalCode: data.shipTo.postalCode,
                    city: data.shipTo.city,
                    country: data.shipTo.countryCode,
                  })}
                </Fact>
                <Fact term={t('order.shortage')}>
                  <span className="tnum">{number(data.shortageUnits)}</span>
                </Fact>
                <Fact term={t('order.backorder')}>
                  {data.backorderAccepted ? t('common.yes') : t('common.no')}
                </Fact>
              </Facts>
            </section>

            <section aria-labelledby="order-lines-title" className="grid gap-gutter">
              <h2 id="order-lines-title" className="text-headline-md">
                {t('order.section.lines')}
              </h2>
              <DataTable
                caption={t('order.lines.caption')}
                columns={columns}
                rows={lines}
                rowKey={(line) => String(line.lineNumber)}
                empty={t('order.lines.empty')}
              />
            </section>
          </div>

          <ConfirmDialog
            open={cancelling}
            onOpenChange={setCancelling}
            title={t('order.cancel.title', { reference: data.reference })}
            description={t('order.cancel.description')}
            confirmLabel={t('order.cancel.confirm')}
            pending={change.isPending}
            failure={change.isError ? failureText(change.error, t) : undefined}
            onConfirm={() => {
              run('cancel');
            }}
          />
        </>
      )}
    </>
  );
}

function BackLink() {
  const { t } = useTranslation();

  return (
    <Link
      to="/orders"
      className="mb-gutter inline-flex min-h-control items-center gap-1 text-body-md text-accent hover:underline"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('order.back')}
    </Link>
  );
}
