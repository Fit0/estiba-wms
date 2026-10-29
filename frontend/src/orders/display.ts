import type { OrderPriority, OrderStatus } from '../api/orders';
import type { Tone } from '../components/tone';

export const orderTone: Record<OrderStatus, Tone> = {
  registered: 'info',
  partially_allocated: 'warn',
  allocated: 'ok',
  in_wave: 'info',
  picked: 'ok',
  shipped: 'neutral',
  cancelled: 'neutral',
};

export const priorityTone: Record<OrderPriority, Tone> = {
  low: 'neutral',
  normal: 'neutral',
  high: 'warn',
  urgent: 'bad',
};

/** Stock can be reserved while nothing is reserved yet, or only part of it. */
export function canAllocate(status: OrderStatus): boolean {
  return status === 'registered' || status === 'partially_allocated';
}

/** The customer can take what is covered and wait for the rest. */
export function canAcceptBackorder(status: OrderStatus, accepted: boolean): boolean {
  return status === 'partially_allocated' && !accepted;
}

/** Up to the wave: once the goods are picked or shipped there is nothing to undo here. */
export function canCancel(status: OrderStatus): boolean {
  return status !== 'picked' && status !== 'shipped' && status !== 'cancelled';
}
