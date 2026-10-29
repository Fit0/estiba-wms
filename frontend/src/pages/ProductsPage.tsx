import { useEffect, useRef, useState } from 'react';
import { Plus } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useProducts, type Product, type ProductSearch } from '../api/products';
import { hasRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { ProductDialog } from '../catalog/ProductDialogs';
import { productTone } from '../catalog/status';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';

const pageSizes = [10, 30, 100] as const;
const searchDelay = 300;
const statuses = ['active', 'blocked', 'discontinued'] as const;

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

/**
 * The catalogue. The text, the status, the page and its size live in the address, so going back from
 * a product lands on the same list, and a search can be sent to a colleague. The server searches and
 * pages; the screen only asks.
 */
export function ProductsPage() {
  const { t } = useTranslation();
  const { session } = useAuth();
  const navigate = useNavigate();
  const canCreate = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');
  const [creating, setCreating] = useState(false);
  const [params, setParams] = useSearchParams();

  const text = params.get('q') ?? '';
  const statusParam = params.get('status');
  const status = statuses.find((value) => value === statusParam);
  const page = positiveInt(params.get('page'), 1);
  const requestedSize = positiveInt(params.get('size'), pageSizes[1]);
  const pageSize = pageSizes.find((size) => size === requestedSize) ?? pageSizes[1];

  function change(values: Record<string, string | undefined>): void {
    setParams(
      (current) => {
        const next = new URLSearchParams(current);
        for (const [key, value] of Object.entries(values)) {
          if (value === undefined || value === '') {
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

  // The box keeps what is typed; the address and the request follow it after a short pause.
  const [typed, setTyped] = useState(text);
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );

  function type(value: string): void {
    setTyped(value);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      change({ q: value.trim(), page: undefined });
    }, searchDelay);
  }

  const search: ProductSearch = {
    page,
    itemsPerPage: pageSize,
    ...(status === undefined ? {} : { status }),
    ...(text === '' ? {} : { q: text }),
  };
  const products = useProducts(search);

  const columns: Column<Product>[] = [
    {
      id: 'sku',
      header: t('product.sku'),
      kind: 'code',
      cell: (product) => (
        <Link
          to={`/products/${product.id}`}
          aria-label={t('product.open', { sku: product.sku })}
          className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
        >
          {product.sku}
        </Link>
      ),
    },
    { id: 'name', header: t('product.name'), cell: (product) => product.name },
    {
      id: 'unit',
      header: t('product.baseUnit'),
      cell: (product) => t(`product.unit.${product.baseUnit}`),
    },
    {
      id: 'class',
      header: t('product.storageClass'),
      cell: (product) => t(`zone.class.${product.storageClass}`),
    },
    {
      id: 'status',
      header: t('product.status'),
      cell: (product) => (
        <StatusBadge tone={productTone[product.status]} dot>
          {t(`product.status.${product.status}`)}
        </StatusBadge>
      ),
    },
  ];

  const filtered = text !== '' || status !== undefined;

  return (
    <>
      <PageHeader title={t('products.title')}>{t('products.intro')}</PageHeader>

      {canCreate && (
        <div className="mb-gutter flex justify-end">
          <Button
            variant="primary"
            onClick={() => {
              setCreating(true);
            }}
          >
            <Plus aria-hidden="true" className="size-4" />
            {t('products.new')}
          </Button>
        </div>
      )}

      <div className="mb-gutter flex flex-wrap items-end gap-2">
        <div className="min-w-64 flex-1">
          <TextField
            type="search"
            label={t('products.search')}
            placeholder={t('products.search.placeholder')}
            autoComplete="off"
            value={typed}
            onChange={(event) => {
              type(event.target.value);
            }}
          />
        </div>
        <Select
          label={t('products.filter')}
          value={status ?? 'all'}
          onValueChange={(value) => {
            change({ status: statuses.find((item) => item === value), page: undefined });
          }}
          options={[
            { value: 'all', label: t('products.filter.all') },
            ...statuses.map((value) => ({ value, label: t(`product.status.${value}`) })),
          ]}
        />
      </div>

      <DataTable
        caption={t('products.caption')}
        columns={columns}
        rows={products.data?.items ?? []}
        rowKey={(product) => product.id}
        status={products.isPending ? 'loading' : products.isError ? 'error' : 'ready'}
        empty={filtered ? t('products.empty.filtered') : t('products.empty')}
        errorMessage={`${t('table.error')} (${describeError(products.error)})`}
        onRetry={() => void products.refetch()}
        pagination={{
          page,
          pageSize,
          total: products.data?.total ?? 0,
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
        <ProductDialog
          onClose={() => {
            setCreating(false);
          }}
          onCreated={(product) => {
            setCreating(false);
            void navigate(`/products/${product.id}`);
          }}
        />
      )}
    </>
  );
}
