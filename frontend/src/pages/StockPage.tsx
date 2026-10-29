import { X } from 'lucide-react';
import { Tabs } from 'radix-ui';
import { useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import {
  useStockItems,
  useStockMovements,
  type MovementSearch,
  type StockSearch,
} from '../api/stock';
import { useLocations } from '../api/warehouses';
import { Button } from '../components/Button';
import { DataTable } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { ProductFilter } from '../stock/ProductFilter';
import { itemColumns, movementColumns } from '../stock/columns';
import { useActiveWarehouse } from '../warehouse/ActiveWarehouse';

const pageSizes = [10, 30, 100] as const;
type Tab = 'items' | 'movements';
const stockStatuses = ['available', 'quarantine', 'damaged', 'on_hold'] as const;

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

const tabTrigger =
  'inline-flex min-h-control items-center border-b-2 border-transparent px-4 text-body-md font-semibold text-fg-secondary hover:text-fg data-[state=active]:border-primary data-[state=active]:text-fg';

/**
 * The stock of the company in two views of the same filters: the lines (what is where) and the ledger
 * of movements (how it got there). The filters and the page live in the address (ADR 0031), so a line
 * can link to its own history and the history can be sent to a colleague.
 */
export function StockPage() {
  const { t, language } = useTranslation();
  const [params, setParams] = useSearchParams();
  const { active, loading } = useActiveWarehouse();

  const tab: Tab = params.get('tab') === 'movements' ? 'movements' : 'items';
  const productId = params.get('product') ?? undefined;
  const locationId = params.get('location') ?? undefined;
  const itemId = params.get('item') ?? undefined;
  const status = stockStatuses.find((value) => value === params.get('status'));
  const page = positiveInt(params.get('page'), 1);
  const requestedSize = positiveInt(params.get('size'), pageSizes[1]);
  const pageSize = pageSizes.find((size) => size === requestedSize) ?? pageSizes[1];

  function change(values: Record<string, string | undefined>, resetPage = true): void {
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
        if (resetPage) {
          next.delete('page');
        }

        return next;
      },
      { replace: true },
    );
  }

  const locations = useLocations(active?.id ?? '', { page: 1, itemsPerPage: 100 });

  const itemSearch: StockSearch = {
    page,
    itemsPerPage: pageSize,
    ...(productId === undefined ? {} : { productId }),
    ...(locationId === undefined ? {} : { locationId }),
    ...(active === null ? {} : { warehouseId: active.id }),
    ...(status === undefined ? {} : { status }),
  };
  const movementSearch: MovementSearch = {
    page,
    itemsPerPage: pageSize,
    ...(itemId === undefined ? {} : { stockItemId: itemId }),
    ...(productId === undefined ? {} : { productId }),
    ...(locationId === undefined ? {} : { locationId }),
  };
  // The lines are of the active warehouse, so they wait until it is known.
  const items = useStockItems(itemSearch, { enabled: !loading });
  const movements = useStockMovements(movementSearch);

  function pagination(total: number) {
    return {
      page,
      pageSize,
      total,
      onPageChange: (next: number) => {
        change({ page: String(next) }, false);
      },
      onPageSizeChange: (size: number) => {
        change({ size: String(size) });
      },
      pageSizes,
    };
  }

  function movementsOf(id: string): string {
    const next = new URLSearchParams(params);
    next.set('tab', 'movements');
    next.set('item', id);
    next.delete('page');

    return `/stock?${next.toString()}`;
  }

  return (
    <>
      <PageHeader title={t('stock.title')}>{t('stock.intro')}</PageHeader>

      <div className="mb-gutter flex flex-wrap items-start gap-2">
        <div className="min-w-64 flex-1">
          <ProductFilter
            productId={productId}
            onChange={(next) => {
              change({ product: next });
            }}
          />
        </div>
        {active !== null && (
          <Select
            label={t('stock.filter.location')}
            value={locationId ?? 'all'}
            onValueChange={(value) => {
              change({ location: value === 'all' ? undefined : value });
            }}
            options={[
              { value: 'all', label: t('stock.filter.location.all') },
              ...(locations.data?.items ?? []).map((location) => ({
                value: location.id,
                label: location.code,
              })),
            ]}
          />
        )}
        {tab === 'items' && (
          <Select
            label={t('stock.filter.status')}
            value={status ?? 'all'}
            onValueChange={(value) => {
              change({ status: stockStatuses.find((item) => item === value) });
            }}
            options={[
              { value: 'all', label: t('stock.filter.status.all') },
              ...stockStatuses.map((value) => ({ value, label: t(`stock.status.${value}`) })),
            ]}
          />
        )}
      </div>

      {itemId !== undefined && tab === 'movements' && (
        <div className="mb-gutter flex items-center gap-2 text-body-md">
          <span>{t('stock.filter.item')}</span>
          <Button
            variant="ghost"
            onClick={() => {
              change({ item: undefined });
            }}
          >
            <X aria-hidden="true" className="size-4" />
            {t('stock.filter.item.clear')}
          </Button>
        </div>
      )}

      <Tabs.Root
        value={tab}
        onValueChange={(value) => {
          change({ tab: value === 'movements' ? 'movements' : undefined, item: undefined });
        }}
      >
        <Tabs.List
          aria-label={t('stock.tabs')}
          className="mb-gutter flex flex-wrap border-b border-line"
        >
          <Tabs.Trigger value="items" className={tabTrigger}>
            {t('stock.tab.items')}
          </Tabs.Trigger>
          <Tabs.Trigger value="movements" className={tabTrigger}>
            {t('stock.tab.movements')}
          </Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="items">
          <DataTable
            caption={t('stock.items.caption')}
            columns={itemColumns(t, language, (item) => movementsOf(item.id))}
            rows={items.data?.items ?? []}
            rowKey={(item) => item.id}
            status={items.isPending ? 'loading' : items.isError ? 'error' : 'ready'}
            empty={t('stock.items.empty')}
            errorMessage={`${t('table.error')} (${describeError(items.error)})`}
            onRetry={() => void items.refetch()}
            pagination={pagination(items.data?.total ?? 0)}
          />
        </Tabs.Content>

        <Tabs.Content value="movements">
          <DataTable
            caption={t('stock.movements.caption')}
            columns={movementColumns(t, language)}
            rows={movements.data?.items ?? []}
            rowKey={(movement) => movement.id}
            status={movements.isPending ? 'loading' : movements.isError ? 'error' : 'ready'}
            empty={t('stock.movements.empty')}
            errorMessage={`${t('table.error')} (${describeError(movements.error)})`}
            onRetry={() => void movements.refetch()}
            pagination={pagination(movements.data?.total ?? 0)}
          />
        </Tabs.Content>
      </Tabs.Root>
    </>
  );
}
