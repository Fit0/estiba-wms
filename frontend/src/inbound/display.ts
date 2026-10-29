import type { AsnStatus } from '../api/inbound';
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
