import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import {
  useApproveOverReceipt,
  useAsn,
  useCloseReceipt,
  useProductsById,
  useReceipt,
} from '../api/inbound';
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
import { formatDate, formatDateTime, formatNumber } from '../i18n/format';
import { productLabel } from '../inbound/display';
import { ReceiveForm } from '../inbound/ReceiveForm';
import {
  parseDiscrepancies,
  parseExpected,
  parseReceivedLines,
  type Discrepancy,
  type ReceivedLine,
} from '../inbound/schemas';

interface Row {
  readonly productId: string;
  readonly expected: number;
  readonly counted: number;
}

/**
 * A receipt at the dock: what the delivery announced against what has been counted, one count at a
 * time, with the discrepancies the API finds on the way (shortage, excess, a product that is not on
 * the delivery, damaged units). More than announced needs a supervisor's approval before closing.
 */
export function ReceiptPage() {
  const { id = '' } = useParams();
  const { t, language } = useTranslation();
  const { session } = useAuth();
  const isSupervisor = session.status === 'signedIn' && hasRole(session.user.roles, 'supervisor');

  const receipt = useReceipt(id);
  const asn = useAsn(receipt.data?.asnId ?? '');
  const approve = useApproveOverReceipt(id);
  const close = useCloseReceipt(id);
  const [closing, setClosing] = useState(false);

  const expected = useMemo(
    () => (receipt.data === undefined ? [] : parseExpected(receipt.data.expected)),
    [receipt.data],
  );
  const counts = useMemo(
    () => (receipt.data === undefined ? [] : parseReceivedLines(receipt.data.lines)),
    [receipt.data],
  );
  const discrepancies = useMemo(
    () => (receipt.data === undefined ? [] : parseDiscrepancies(receipt.data.discrepancies)),
    [receipt.data],
  );
  const rows = useMemo<Row[]>(() => {
    const byProduct = new Map<string, { expected: number; counted: number }>();
    for (const item of expected) {
      const row = byProduct.get(item.productId) ?? { expected: 0, counted: 0 };
      byProduct.set(item.productId, { ...row, expected: row.expected + item.units });
    }
    for (const line of counts) {
      const row = byProduct.get(line.productId) ?? { expected: 0, counted: 0 };
      byProduct.set(line.productId, { ...row, counted: row.counted + line.units });
    }

    return [...byProduct].map(([productId, row]) => ({ productId, ...row }));
  }, [expected, counts]);
  const products = useProductsById(rows.map((row) => row.productId));

  if (receipt.isError) {
    return (
      <>
        <BackLink to="/inbound" />
        <PageHeader title={t('receipt.notFound')} />
      </>
    );
  }

  const data = receipt.data;
  const number = (value: number): string => formatNumber(language, value);
  const isOpen = data?.status === 'open';
  const overUnapproved =
    data !== undefined && data.overReceivedUnits > 0 && !data.overReceiptApproved;

  const rowColumns: Column<Row>[] = [
    {
      id: 'product',
      header: t('asn.line.product'),
      cell: (r) => productLabel(products, r.productId),
    },
    {
      id: 'expected',
      header: t('receipt.col.expected'),
      kind: 'number',
      cell: (r) => number(r.expected),
    },
    {
      id: 'counted',
      header: t('receipt.col.counted'),
      kind: 'number',
      cell: (r) => number(r.counted),
    },
    {
      id: 'difference',
      header: t('receipt.col.difference'),
      kind: 'number',
      cell: (r) => {
        const difference = r.counted - r.expected;

        return difference > 0 ? `+${number(difference)}` : number(difference);
      },
    },
  ];

  const lineColumns: Column<ReceivedLine>[] = [
    { id: 'number', header: t('receipt.line.number'), kind: 'number', cell: (l) => l.lineNumber },
    {
      id: 'product',
      header: t('asn.line.product'),
      cell: (l) => productLabel(products, l.productId),
    },
    { id: 'units', header: t('receive.units'), kind: 'number', cell: (l) => number(l.units) },
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
      id: 'condition',
      header: t('receive.condition'),
      cell: (l) => (
        <StatusBadge tone={l.condition === 'good' ? 'ok' : 'bad'} dot>
          {t(`receive.condition.${l.condition}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'time',
      header: t('receipt.line.time'),
      cell: (l) => formatDateTime(language, l.receivedAt),
    },
  ];

  const discrepancyColumns: Column<Discrepancy>[] = [
    {
      id: 'type',
      header: t('discrepancy.type'),
      cell: (d) => (
        <StatusBadge tone={d.type === 'shortage' ? 'warn' : 'bad'} dot>
          {t(`discrepancy.type.${d.type}`)}
        </StatusBadge>
      ),
    },
    {
      id: 'product',
      header: t('asn.line.product'),
      cell: (d) => productLabel(products, d.productId),
    },
    { id: 'units', header: t('discrepancy.units'), kind: 'number', cell: (d) => number(d.units) },
  ];

  const reference = asn.data?.reference ?? '';

  return (
    <>
      <BackLink to={data === undefined ? '/inbound' : `/inbound/${data.asnId}`} />
      <PageHeader title={t('receipt.title', { reference })}>
        {data === undefined ? undefined : (
          <StatusBadge tone={isOpen ? 'info' : 'neutral'} dot>
            {t(`receipt.status.${data.status}`)}
          </StatusBadge>
        )}
      </PageHeader>

      {data !== undefined && (
        <div className="grid gap-gutter-lg">
          <Facts>
            <Fact term={t('receipt.status.open')}>{formatDateTime(language, data.openedAt)}</Fact>
            {data.closedAt !== null && data.closedAt !== undefined && (
              <Fact term={t('receipt.status.closed')}>
                {formatDateTime(language, data.closedAt)}
              </Fact>
            )}
            {!isOpen && (
              <Fact term={t('product.section.stock')}>
                {data.stockBooked ? t('receipt.stockBooked') : t('receipt.stockPending')}
              </Fact>
            )}
          </Facts>

          {isOpen && (
            <section aria-labelledby="count-title" className="grid gap-gutter">
              <h2 id="count-title" className="text-headline-md">
                {t('receipt.section.count')}
              </h2>
              <ReceiveForm receiptId={id} />
            </section>
          )}

          <section aria-labelledby="expected-title" className="grid gap-gutter">
            <h2 id="expected-title" className="text-headline-md">
              {t('receipt.section.expected')}
            </h2>
            <DataTable
              caption={t('receipt.expected.caption')}
              columns={rowColumns}
              rows={rows}
              rowKey={(r) => r.productId}
              empty={t('receipt.lines.empty')}
            />
          </section>

          <section aria-labelledby="discrepancies-title" className="grid gap-gutter">
            <h2 id="discrepancies-title" className="text-headline-md">
              {t('receipt.section.discrepancies')}
            </h2>
            {data.overReceivedUnits > 0 && (
              <div role="status" className="grid gap-2 text-body-md">
                {data.overReceiptApproved ? (
                  <p className="text-ok">{t('receipt.over.approved')}</p>
                ) : (
                  <p className="text-warn">
                    {t('receipt.over.warning', { units: number(data.overReceivedUnits) })}
                  </p>
                )}
                {isOpen && isSupervisor && !data.overReceiptApproved && (
                  <div>
                    <Button
                      disabled={approve.isPending}
                      onClick={() => {
                        approve.mutate(undefined, {
                          onSuccess: () => {
                            toast.success(t('receipt.over.done'));
                          },
                        });
                      }}
                    >
                      {t('receipt.over.approve')}
                    </Button>
                  </div>
                )}
              </div>
            )}
            {approve.isError && (
              <p role="alert" className="text-body-md text-bad">
                {failureText(approve.error, t)}
              </p>
            )}
            <DataTable
              caption={t('receipt.discrepancies.caption')}
              columns={discrepancyColumns}
              rows={discrepancies}
              rowKey={(d) => `${d.type}|${d.productId}`}
              empty={t('receipt.discrepancies.none')}
            />
          </section>

          <section aria-labelledby="lines-title" className="grid gap-gutter">
            <h2 id="lines-title" className="text-headline-md">
              {t('receipt.section.lines')}
            </h2>
            <DataTable
              caption={t('receipt.lines.caption')}
              columns={lineColumns}
              rows={counts}
              rowKey={(l) => String(l.lineNumber)}
              empty={t('receipt.lines.empty')}
            />
          </section>

          {isOpen && (
            <div className="flex flex-wrap items-center justify-end gap-2">
              {overUnapproved && (
                <p className="text-body-md text-fg-secondary">
                  {t('receipt.over.warning', { units: number(data.overReceivedUnits) })}
                </p>
              )}
              <Button
                variant="primary"
                disabled={overUnapproved}
                onClick={() => {
                  close.reset();
                  setClosing(true);
                }}
              >
                {t('receipt.close')}
              </Button>
            </div>
          )}
        </div>
      )}

      <ConfirmDialog
        open={closing}
        onOpenChange={setClosing}
        title={t('receipt.close.title', { reference })}
        description={t('receipt.close.description')}
        confirmLabel={t('receipt.close.confirm')}
        pending={close.isPending}
        failure={close.isError ? failureText(close.error, t) : undefined}
        onConfirm={() => {
          close.mutate(undefined, {
            onSuccess: () => {
              setClosing(false);
              toast.success(t('receipt.close.done'));
            },
          });
        }}
      />
    </>
  );
}

function BackLink({ to }: { to: string }) {
  const { t } = useTranslation();

  return (
    <Link
      to={to}
      className="mb-gutter inline-flex min-h-control items-center gap-1 text-body-md text-accent hover:underline"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('receipt.back')}
    </Link>
  );
}
