import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useOrdersById, type Order } from '../api/orders';
import { useCancelWave, usePickLists, useWave, type PickList } from '../api/waves';
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
import { orderTone } from '../orders/display';
import { canCancelWave, canRelease, pickListTone, waveName, waveTone } from '../waves/display';
import { parseIds } from '../waves/schemas';
import { ReleaseWaveDialog } from '../waves/WaveDialogs';

/**
 * One wave: its orders and, once it is on the floor, its pick lists with how much each has picked.
 * The page asks again every few seconds, so the progress moves by itself. A supervisor sends a
 * planned wave to the floor or cancels it.
 */
export function WavePage() {
  const { id = '' } = useParams();
  const { t, language } = useTranslation();
  const { session } = useAuth();
  const canChange = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');
  const wave = useWave(id);
  const pickLists = usePickLists(id);
  const cancel = useCancelWave(id);
  const [releasing, setReleasing] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  const orderIds = useMemo(
    () => (wave.data === undefined ? [] : parseIds(wave.data.orderIds)),
    [wave.data],
  );
  const orders = useOrdersById(orderIds);

  if (wave.isError) {
    return (
      <>
        <BackLink />
        <PageHeader title={t('wave.notFound')} />
      </>
    );
  }

  const data = wave.data;
  const number = (value: number): string => formatNumber(language, value);

  const orderColumns: Column<string>[] = [
    {
      id: 'order',
      header: t('order.reference'),
      kind: 'code',
      cell: (orderId) => {
        const order = orders.get(orderId);

        return (
          <Link
            to={`/orders/${orderId}`}
            className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
          >
            {order?.reference ?? orderId.slice(0, 8)}
          </Link>
        );
      },
    },
    {
      id: 'customer',
      header: t('order.customer'),
      cell: (orderId) => orders.get(orderId)?.customer ?? '—',
    },
    {
      id: 'status',
      header: t('order.status'),
      cell: (orderId) => {
        const order: Order | undefined = orders.get(orderId);

        return order === undefined ? (
          '—'
        ) : (
          <StatusBadge tone={orderTone[order.status]} dot>
            {t(`order.status.${order.status}`)}
          </StatusBadge>
        );
      },
    },
  ];

  const pickListColumns: Column<PickList>[] = [
    { id: 'list', header: t('wave.pickList'), kind: 'code', cell: (list) => waveName(list.id) },
    {
      id: 'operator',
      header: t('wave.operator'),
      cell: (list) =>
        list.operatorId === null || list.operatorId === undefined
          ? t('wave.operator.none')
          : waveName(list.operatorId),
    },
    {
      id: 'progress',
      header: t('wave.progress'),
      kind: 'number',
      cell: (list) =>
        t('wave.progress.value', {
          picked: number(list.unitsPicked),
          total: number(list.unitsToPick),
        }),
    },
    {
      id: 'status',
      header: t('wave.status'),
      cell: (list) => (
        <StatusBadge tone={pickListTone[list.status]} dot>
          {t(`pickList.status.${list.status}`)}
        </StatusBadge>
      ),
    },
  ];

  return (
    <>
      <BackLink />
      <PageHeader title={data === undefined ? '' : t('wave.title', { name: waveName(data.id) })}>
        {data === undefined ? undefined : (
          <StatusBadge tone={waveTone[data.status]} dot>
            {t(`wave.status.${data.status}`)}
          </StatusBadge>
        )}
      </PageHeader>

      {data !== undefined && (
        <>
          {canChange && (canRelease(data.status) || canCancelWave(data.status)) && (
            <div className="mb-gutter flex flex-wrap justify-end gap-2">
              {canRelease(data.status) && (
                <Button
                  variant="primary"
                  onClick={() => {
                    setReleasing(true);
                  }}
                >
                  {t('wave.release')}
                </Button>
              )}
              {canCancelWave(data.status) && (
                <Button
                  onClick={() => {
                    cancel.reset();
                    setCancelling(true);
                  }}
                >
                  {t('wave.cancel')}
                </Button>
              )}
            </div>
          )}

          <div className="mb-gutter-lg grid gap-gutter-lg">
            <section aria-labelledby="wave-data-title" className="grid gap-gutter">
              <h2 id="wave-data-title" className="text-headline-md">
                {t('wave.section.data')}
              </h2>
              <Facts>
                <Fact term={t('wave.createdAt')}>{formatDateTime(language, data.createdAt)}</Fact>
                <Fact term={t('wave.releasedAt')}>
                  {data.releasedAt === null || data.releasedAt === undefined
                    ? '—'
                    : formatDateTime(language, data.releasedAt)}
                </Fact>
                <Fact term={t('wave.completedAt')}>
                  {data.completedAt === null || data.completedAt === undefined
                    ? '—'
                    : formatDateTime(language, data.completedAt)}
                </Fact>
              </Facts>
            </section>

            <section aria-labelledby="wave-orders-title" className="grid gap-gutter">
              <h2 id="wave-orders-title" className="text-headline-md">
                {t('wave.section.orders')}
              </h2>
              <DataTable
                caption={t('wave.orders.caption')}
                columns={orderColumns}
                rows={orderIds}
                rowKey={(orderId) => orderId}
                empty={t('wave.orders.empty')}
              />
            </section>

            <section aria-labelledby="wave-lists-title" className="grid gap-gutter">
              <h2 id="wave-lists-title" className="text-headline-md">
                {t('wave.section.pickLists')}
              </h2>
              <DataTable
                caption={t('wave.pickLists.caption')}
                columns={pickListColumns}
                rows={pickLists.data ?? []}
                rowKey={(list) => list.id}
                status={pickLists.isPending ? 'loading' : pickLists.isError ? 'error' : 'ready'}
                empty={t('wave.pickLists.empty')}
                errorMessage={`${t('table.error')} (${describeError(pickLists.error)})`}
                onRetry={() => void pickLists.refetch()}
              />
            </section>
          </div>

          {releasing && (
            <ReleaseWaveDialog
              wave={data}
              onClose={() => {
                setReleasing(false);
              }}
            />
          )}
          <ConfirmDialog
            open={cancelling}
            onOpenChange={setCancelling}
            title={t('wave.cancel.title', { name: waveName(data.id) })}
            description={t('wave.cancel.description')}
            confirmLabel={t('wave.cancel.confirm')}
            pending={cancel.isPending}
            failure={cancel.isError ? failureText(cancel.error, t) : undefined}
            onConfirm={() => {
              cancel.mutate(undefined, {
                onSuccess: () => {
                  setCancelling(false);
                  toast.success(t('wave.cancel.done'));
                },
              });
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
      to="/waves"
      className="mb-gutter inline-flex min-h-control items-center gap-1 text-body-md text-accent hover:underline"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('wave.back')}
    </Link>
  );
}
