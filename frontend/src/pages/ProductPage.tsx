import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { describeError } from '../api/describeError';
import {
  useChangeProductStatus,
  useProduct,
  useProductStock,
  type ProductAction,
} from '../api/products';
import { useStockItems, type StockItem } from '../api/stock';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Fact, Facts } from '../catalog/Facts';
import {
  parseGtins,
  parsePackagingLevels,
  parseWarehouseStock,
  type WarehouseStock,
} from '../catalog/schemas';
import { productTone } from '../catalog/status';
import { Button } from '../components/Button';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { failureText } from '../components/FormDialog';
import { formatDate, formatNumber } from '../i18n/format';

const pageSizes = [10, 30, 100] as const;

/**
 * One product, read-only except for its status: its data, size, tracking, GTINs and packaging, and
 * the stock it has, by warehouse and by location. Supervisors and admins block, unblock and
 * discontinue it; the API refuses anyone else.
 */
export function ProductPage() {
  const { id = '' } = useParams();
  const { t, language } = useTranslation();
  const { session } = useAuth();
  const canEdit = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');

  const product = useProduct(id);
  const stock = useProductStock(id);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(pageSizes[1]);
  const lines = useStockItems({ productId: id, page, itemsPerPage: pageSize });
  const change = useChangeProductStatus(id);
  const [confirming, setConfirming] = useState<'block' | 'discontinue' | null>(null);

  const gtins = useMemo(
    () => (product.data === undefined ? [] : parseGtins(product.data.gtins)),
    [product.data],
  );
  const levels = useMemo(
    () => (product.data === undefined ? [] : parsePackagingLevels(product.data.packagingLevels)),
    [product.data],
  );
  const warehouses = useMemo(
    () => (stock.data === undefined ? [] : parseWarehouseStock(stock.data.warehouses)),
    [stock.data],
  );

  if (product.isError) {
    return (
      <>
        <BackLink />
        <PageHeader title={t('product.notFound')} />
      </>
    );
  }

  const data = product.data;
  const number = (value: number): string => formatNumber(language, value);

  function run(action: ProductAction): void {
    change.mutate(action, {
      onSuccess: (updated) => {
        setConfirming(null);
        toast.success(t(`product.${action}.done`, { sku: updated.sku }));
      },
    });
  }

  const warehouseColumns: Column<WarehouseStock>[] = [
    { id: 'warehouse', header: t('stock.warehouse'), kind: 'code', cell: (w) => w.warehouseCode },
    { id: 'onHand', header: t('stock.onHand'), kind: 'number', cell: (w) => number(w.onHand) },
    {
      id: 'available',
      header: t('stock.status.available'),
      kind: 'number',
      cell: (w) => number(w.available),
    },
    {
      id: 'quarantine',
      header: t('stock.status.quarantine'),
      kind: 'number',
      cell: (w) => number(w.quarantine),
    },
    {
      id: 'damaged',
      header: t('stock.status.damaged'),
      kind: 'number',
      cell: (w) => number(w.damaged),
    },
    {
      id: 'onHold',
      header: t('stock.status.on_hold'),
      kind: 'number',
      cell: (w) => number(w.onHold),
    },
    {
      id: 'locations',
      header: t('stock.locations'),
      kind: 'number',
      cell: (w) => number(w.locations),
    },
  ];

  const lineColumns: Column<StockItem>[] = [
    { id: 'warehouse', header: t('stock.warehouse'), kind: 'code', cell: (l) => l.warehouseCode },
    { id: 'location', header: t('stock.location'), kind: 'code', cell: (l) => l.locationCode },
    { id: 'lot', header: t('stock.lot'), cell: (l) => l.lot ?? '—' },
    {
      id: 'expiry',
      header: t('stock.expiry'),
      cell: (l) =>
        l.expiryDate === null || l.expiryDate === undefined
          ? '—'
          : formatDate(language, l.expiryDate),
    },
    {
      id: 'status',
      header: t('stock.state'),
      cell: (l) => (
        <StatusBadge tone={l.status === 'available' ? 'ok' : 'warn'} dot>
          {t(`stock.status.${l.status}`)}
        </StatusBadge>
      ),
    },
    { id: 'onHand', header: t('stock.onHand'), kind: 'number', cell: (l) => number(l.onHand) },
  ];

  return (
    <>
      <BackLink />
      <PageHeader title={data === undefined ? '' : `${data.sku} · ${data.name}`}>
        {data === undefined ? undefined : (
          <StatusBadge tone={productTone[data.status]} dot>
            {t(`product.status.${data.status}`)}
          </StatusBadge>
        )}
      </PageHeader>

      {canEdit && data !== undefined && data.status !== 'discontinued' && (
        <div className="mb-gutter flex flex-wrap justify-end gap-2">
          {data.status === 'active' ? (
            <Button
              onClick={() => {
                change.reset();
                setConfirming('block');
              }}
            >
              {t('product.block')}
            </Button>
          ) : (
            <Button
              disabled={change.isPending}
              onClick={() => {
                run('unblock');
              }}
            >
              {t('product.unblock')}
            </Button>
          )}
          <Button
            onClick={() => {
              change.reset();
              setConfirming('discontinue');
            }}
          >
            {t('product.discontinue')}
          </Button>
        </div>
      )}
      {change.isError && confirming === null && (
        <p role="alert" className="mb-gutter text-body-md text-bad">
          {failureText(change.error, t)}
        </p>
      )}

      {data !== undefined && (
        <div className="mb-gutter-lg grid gap-gutter-lg">
          <section aria-labelledby="data-title" className="grid gap-gutter">
            <h2 id="data-title" className="text-headline-md">
              {t('product.section.data')}
            </h2>
            <Facts>
              <Fact term={t('product.sku')}>
                <span className="text-data tnum">{data.sku}</span>
              </Fact>
              <Fact term={t('product.name')}>{data.name}</Fact>
              <Fact term={t('product.baseUnit')}>{t(`product.unit.${data.baseUnit}`)}</Fact>
              <Fact term={t('product.storageClass')}>{t(`zone.class.${data.storageClass}`)}</Fact>
              <Fact term={t('product.family')}>{data.family ?? '—'}</Fact>
              <Fact term={t('product.turnover')}>{data.turnoverClass}</Fact>
            </Facts>
          </section>

          <section aria-labelledby="size-title" className="grid gap-gutter">
            <h2 id="size-title" className="text-headline-md">
              {t('product.section.size')}
            </h2>
            <Facts>
              <Fact term={t('product.dimensions')}>
                <span className="tnum">
                  {t('product.dimensions.value', {
                    length: number(data.lengthMm),
                    width: number(data.widthMm),
                    height: number(data.heightMm),
                  })}
                </span>
              </Fact>
              <Fact term={t('product.weight')}>
                <span className="tnum">
                  {t('product.weight.value', { grams: number(data.weightGrams) })}
                </span>
              </Fact>
            </Facts>
          </section>

          <section aria-labelledby="tracking-title" className="grid gap-gutter">
            <h2 id="tracking-title" className="text-headline-md">
              {t('product.section.tracking')}
            </h2>
            <Facts>
              <Fact term={t('product.trackedByLot')}>
                {data.trackedByLot ? t('common.yes') : t('common.no')}
              </Fact>
              <Fact term={t('product.trackedByExpiry')}>
                {data.trackedByExpiry ? t('common.yes') : t('common.no')}
              </Fact>
              {data.trackedByExpiry && (
                <>
                  <Fact term={t('product.shelfLifeReceipt')}>
                    {data.minShelfLifeAtReceiptDays === null ||
                    data.minShelfLifeAtReceiptDays === undefined
                      ? '—'
                      : t('product.days', { days: number(data.minShelfLifeAtReceiptDays) })}
                  </Fact>
                  <Fact term={t('product.shelfLifeShipment')}>
                    {data.minShelfLifeAtShipmentDays === null ||
                    data.minShelfLifeAtShipmentDays === undefined
                      ? '—'
                      : t('product.days', { days: number(data.minShelfLifeAtShipmentDays) })}
                  </Fact>
                </>
              )}
            </Facts>
          </section>

          <section aria-labelledby="codes-title" className="grid gap-gutter">
            <h2 id="codes-title" className="text-headline-md">
              {t('product.section.codes')}
            </h2>
            <Facts>
              <Fact term={t('product.gtins')}>
                {gtins.length === 0 ? (
                  t('product.gtins.none')
                ) : (
                  <ul className="grid gap-1">
                    {gtins.map((gtin) => (
                      <li key={gtin} className="text-data tnum">
                        {gtin}
                      </li>
                    ))}
                  </ul>
                )}
              </Fact>
              <Fact term={t('product.packaging')}>
                {levels.length === 0 ? (
                  t('product.packaging.none')
                ) : (
                  <ul className="grid gap-1">
                    {levels.map((level) => (
                      <li key={level.type}>
                        {t('product.packaging.level', {
                          type: t(`product.packaging.${level.type}`),
                          units: number(level.baseUnits),
                        })}
                      </li>
                    ))}
                  </ul>
                )}
              </Fact>
            </Facts>
          </section>
        </div>
      )}

      <section aria-labelledby="stock-title" className="mb-gutter-lg grid gap-gutter">
        <h2 id="stock-title" className="text-headline-md">
          {t('product.section.stock')}
        </h2>
        {stock.data !== undefined && (
          <Facts>
            <Fact term={t('stock.onHand')}>
              <span className="tnum">{number(stock.data.onHand)}</span>
            </Fact>
            <Fact term={t('stock.status.available')}>
              <span className="tnum">{number(stock.data.available)}</span>
            </Fact>
          </Facts>
        )}
        <DataTable
          caption={t('product.stock.byWarehouse')}
          columns={warehouseColumns}
          rows={warehouses}
          rowKey={(w) => w.warehouseId}
          status={stock.isPending ? 'loading' : stock.isError ? 'error' : 'ready'}
          empty={t('product.stock.empty')}
          errorMessage={`${t('table.error')} (${describeError(stock.error)})`}
          onRetry={() => void stock.refetch()}
        />
        <DataTable
          caption={t('product.stock.byLocation')}
          columns={lineColumns}
          rows={lines.data?.items ?? []}
          rowKey={(l) => l.id}
          status={lines.isPending ? 'loading' : lines.isError ? 'error' : 'ready'}
          empty={t('product.stock.empty')}
          errorMessage={`${t('table.error')} (${describeError(lines.error)})`}
          onRetry={() => void lines.refetch()}
          pagination={{
            page,
            pageSize,
            total: lines.data?.total ?? 0,
            onPageChange: setPage,
            onPageSizeChange: (size) => {
              setPageSize(size);
              setPage(1);
            },
            pageSizes,
          }}
        />
      </section>

      <ConfirmDialog
        open={confirming !== null}
        onOpenChange={(open) => {
          if (!open) {
            setConfirming(null);
          }
        }}
        title={t(`product.${confirming ?? 'block'}.title`, { sku: data?.sku ?? '' })}
        description={t(`product.${confirming ?? 'block'}.description`)}
        confirmLabel={t(`product.${confirming ?? 'block'}.confirm`)}
        pending={change.isPending}
        failure={change.isError ? failureText(change.error, t) : undefined}
        onConfirm={() => {
          if (confirming !== null) {
            run(confirming);
          }
        }}
      />
    </>
  );
}

function BackLink() {
  const { t } = useTranslation();

  return (
    <Link
      to="/products"
      className="mb-gutter inline-flex min-h-control items-center gap-1 text-body-md text-accent hover:underline"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('product.back')}
    </Link>
  );
}
