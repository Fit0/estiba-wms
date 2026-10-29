import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { useAsn, useProductsById } from '../api/inbound';
import { Fact, Facts } from '../catalog/Facts';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { useTranslation } from '../i18n';
import { formatDate, formatDateTime, formatNumber } from '../i18n/format';
import { asnTone, productLabel, takesReceipts } from '../inbound/display';
import { OpenReceiptDialog } from '../inbound/OpenReceiptDialog';
import { parseAsnLines, type AsnLine } from '../inbound/schemas';

/**
 * One expected delivery: its lines (what was announced, what has arrived) and the way into the
 * receipt. Operators open the receipt at a dock; the API refuses a delivery that no longer takes one.
 */
export function AsnPage() {
  const { id = '' } = useParams();
  const { t, language } = useTranslation();
  const asn = useAsn(id);
  const [opening, setOpening] = useState(false);

  const lines = useMemo(
    () => (asn.data === undefined ? [] : parseAsnLines(asn.data.lines)),
    [asn.data],
  );
  const products = useProductsById(lines.map((line) => line.productId));

  if (asn.isError) {
    return (
      <>
        <BackLink />
        <PageHeader title={t('asn.notFound')} />
      </>
    );
  }

  const data = asn.data;
  const number = (value: number): string => formatNumber(language, value);

  const columns: Column<AsnLine>[] = [
    {
      id: 'product',
      header: t('asn.line.product'),
      cell: (line) => productLabel(products, line.productId),
    },
    { id: 'lot', header: t('stock.lot'), cell: (line) => line.lot ?? '—' },
    {
      id: 'expiry',
      header: t('stock.expiry'),
      cell: (line) =>
        line.expiryDate === null || line.expiryDate === undefined
          ? '—'
          : formatDate(language, line.expiryDate),
    },
    {
      id: 'expected',
      header: t('asn.line.expected'),
      kind: 'number',
      cell: (line) => number(line.expectedUnits),
    },
    {
      id: 'received',
      header: t('asn.line.received'),
      kind: 'number',
      cell: (line) => number(line.receivedUnits),
    },
    {
      id: 'pending',
      header: t('asn.line.pending'),
      kind: 'number',
      cell: (line) => number(Math.max(0, line.expectedUnits - line.receivedUnits)),
    },
  ];

  return (
    <>
      <BackLink />
      <PageHeader title={data === undefined ? '' : `${data.reference} · ${data.supplier}`}>
        {data === undefined ? undefined : (
          <StatusBadge tone={asnTone[data.status]} dot>
            {t(`asn.status.${data.status}`)}
          </StatusBadge>
        )}
      </PageHeader>

      {data !== undefined && (
        <>
          <div className="mb-gutter-lg grid gap-gutter">
            <Facts>
              <Fact term={t('asn.expectedDate')}>
                {formatDateTime(language, data.expectedDate)}
              </Fact>
              <Fact term={t('asn.lines')}>
                <span className="tnum">{lines.length}</span>
              </Fact>
            </Facts>
            <div className="flex flex-wrap items-center gap-2">
              {data.openReceiptId !== null && data.openReceiptId !== undefined ? (
                <Link
                  to={`/receipts/${data.openReceiptId}`}
                  className="inline-flex h-control items-center rounded-control bg-primary px-4 text-body-md font-semibold text-on-primary hover:bg-primary-hover"
                >
                  {t('asn.receipt.continue')}
                </Link>
              ) : takesReceipts(data.status) ? (
                <Button
                  variant="primary"
                  onClick={() => {
                    setOpening(true);
                  }}
                >
                  {t('asn.receipt.open')}
                </Button>
              ) : (
                <p className="text-body-md text-fg-secondary">{t('asn.receipt.unavailable')}</p>
              )}
            </div>
          </div>

          <section aria-labelledby="asn-lines-title" className="grid gap-gutter">
            <h2 id="asn-lines-title" className="text-headline-md">
              {t('asn.section.lines')}
            </h2>
            <DataTable
              caption={t('asn.lines.caption')}
              columns={columns}
              rows={lines}
              rowKey={(line) => `${line.productId}|${line.lot ?? ''}|${line.expiryDate ?? ''}`}
              empty={t('asn.lines.empty')}
            />
          </section>

          {opening && <OpenReceiptDialog asn={data} open onOpenChange={setOpening} />}
        </>
      )}
    </>
  );
}

function BackLink() {
  const { t } = useTranslation();

  return (
    <Link
      to="/inbound"
      className="mb-gutter inline-flex min-h-control items-center gap-1 text-body-md text-accent hover:underline"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('asn.back')}
    </Link>
  );
}
