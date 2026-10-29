import { Link } from 'react-router';
import type { StockItem, StockMovement } from '../api/stock';
import type { Column } from '../components/DataTable';
import { StatusBadge } from '../components/StatusBadge';
import type { MessageKey } from '../i18n';
import { formatDate, formatDateTime, formatNumber } from '../i18n/format';
import type { Language } from '../i18n';

type Translate = (key: MessageKey, values?: Readonly<Record<string, string>>) => string;

const none = '—';

const date = (language: Language, value: string | null | undefined): string =>
  value === null || value === undefined ? none : formatDate(language, value);

/** The columns of the stock lines. `movementsTo` is the address of the ledger for one line. */
export function itemColumns(
  t: Translate,
  language: Language,
  movementsTo: (item: StockItem) => string,
): Column<StockItem>[] {
  return [
    {
      id: 'product',
      header: t('stock.col.product'),
      cell: (item) => (
        <Link
          to={`/products/${item.productId}`}
          className="inline-flex min-h-control items-center gap-2 text-accent underline-offset-2 hover:underline"
        >
          <span className="text-data tnum">{item.sku}</span>
          <span className="text-fg-secondary">{item.productName}</span>
        </Link>
      ),
    },
    { id: 'warehouse', header: t('stock.warehouse'), kind: 'code', cell: (i) => i.warehouseCode },
    { id: 'location', header: t('stock.location'), kind: 'code', cell: (i) => i.locationCode },
    { id: 'lot', header: t('stock.lot'), cell: (i) => i.lot ?? none },
    { id: 'expiry', header: t('stock.expiry'), cell: (i) => date(language, i.expiryDate) },
    {
      id: 'status',
      header: t('stock.state'),
      cell: (i) => (
        <StatusBadge tone={i.status === 'available' ? 'ok' : 'warn'} dot>
          {t(`stock.status.${i.status}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'onHand',
      header: t('stock.onHand'),
      kind: 'number',
      cell: (i) => formatNumber(language, i.onHand),
    },
    {
      id: 'movements',
      header: t('stock.col.movements'),
      cell: (i) => (
        <Link
          to={movementsTo(i)}
          aria-label={t('stock.movements.of', { sku: i.sku, location: i.locationCode })}
          className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
        >
          {t('stock.movements.see')}
        </Link>
      ),
    },
  ];
}

export function movementColumns(t: Translate, language: Language): Column<StockMovement>[] {
  return [
    {
      id: 'date',
      header: t('stock.col.date'),
      cell: (m) => formatDateTime(language, m.occurredAt),
    },
    { id: 'type', header: t('stock.col.type'), cell: (m) => t(`stock.movement.${m.type}`) },
    { id: 'sku', header: t('product.sku'), kind: 'code', cell: (m) => m.sku },
    { id: 'location', header: t('stock.location'), kind: 'code', cell: (m) => m.locationCode },
    { id: 'lot', header: t('stock.lot'), cell: (m) => m.lot ?? none },
    {
      id: 'status',
      header: t('stock.state'),
      cell: (m) => t(`stock.status.${m.status}`),
    },
    {
      id: 'change',
      header: t('stock.col.change'),
      kind: 'number',
      cell: (m) =>
        m.change > 0 ? `+${formatNumber(language, m.change)}` : formatNumber(language, m.change),
    },
    {
      id: 'balance',
      header: t('stock.col.balance'),
      kind: 'number',
      cell: (m) => formatNumber(language, m.balanceAfter),
    },
    {
      id: 'reference',
      header: t('stock.col.reference'),
      cell: (m) => m.reference ?? m.reason ?? none,
    },
  ];
}
