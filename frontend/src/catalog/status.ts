import type { ProductStatus } from '../api/products';
import type { Tone } from '../components/tone';

/** The tone of each status of a product; the text always says it too. */
export const productTone: Record<ProductStatus, Tone> = {
  active: 'ok',
  blocked: 'warn',
  discontinued: 'neutral',
};
