import type { Order } from '../api/orders';
import type { PickListStatus, WaveStatus } from '../api/waves';
import type { Tone } from '../components/tone';

export const waveTone: Record<WaveStatus, Tone> = {
  planned: 'info',
  released: 'warn',
  completed: 'ok',
  cancelled: 'neutral',
};

export const pickListTone: Record<PickListStatus, Tone> = {
  generated: 'info',
  in_progress: 'warn',
  completed: 'ok',
  cancelled: 'neutral',
};

/** A planned wave can be sent to the floor. */
export function canRelease(status: WaveStatus): boolean {
  return status === 'planned';
}

/** Until it is completed or cancelled; the API refuses it once a pick has been confirmed. */
export function canCancelWave(status: WaveStatus): boolean {
  return status === 'planned' || status === 'released';
}

/**
 * An order that can join a wave: fully reserved, or partly reserved with the backorder accepted, and
 * in no wave yet. The API checks the same and that all the orders are served from one warehouse.
 */
export function isWaveable(order: Order): boolean {
  return (
    (order.status === 'allocated' ||
      (order.status === 'partially_allocated' && order.backorderAccepted)) &&
    (order.waveId === null || order.waveId === undefined)
  );
}

/** A short name for a wave, which has no reference of its own: the start of its id. */
export function waveName(id: string): string {
  return id.slice(0, 8);
}
