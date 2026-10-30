import type { Asn, AsnStatus } from '../api/inbound';
import type { Product } from '../api/products';
import type { Tone } from '../components/tone';

export const asnTone: Record<AsnStatus, Tone> = {
  expected: 'info',
  partially_received: 'warn',
  received: 'ok',
  closed: 'neutral',
  cancelled: 'neutral',
};

/** What a person reads for a product: its SKU and name, or a short id while it loads. */
export function productLabel(products: ReadonlyMap<string, Product>, id: string): string {
  const product = products.get(id);

  return product === undefined ? id.slice(0, 8) : `${product.sku} · ${product.name}`;
}

/** An ASN takes a receipt while goods may still arrive: expected, or partly received. */
export function takesReceipts(status: AsnStatus): boolean {
  return status === 'expected' || status === 'partially_received';
}

/** Only a delivery that is still expected, and with no receipt open. */
export function canCancelAsn(asn: Asn): boolean {
  return (
    asn.status === 'expected' && (asn.openReceiptId === null || asn.openReceiptId === undefined)
  );
}

/** Once goods have arrived, and with no receipt open. What did not arrive stays as a shortage. */
export function canCloseAsn(asn: Asn): boolean {
  return (
    (asn.status === 'partially_received' || asn.status === 'received') &&
    (asn.openReceiptId === null || asn.openReceiptId === undefined)
  );
}
