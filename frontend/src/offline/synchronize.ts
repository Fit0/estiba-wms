import { ApiError } from '../api/ApiError';
import { isNetworkFailure } from '../api/networkFailure';
import type { PickList } from '../api/waves';
import type { ConfirmationQueue, QueuedConfirmation } from './ConfirmationQueue';

/** The code of the API for a stop that was already confirmed: a retry of something that got through. */
export const ALREADY_CONFIRMED = 'outbound.pick_line_already_confirmed';

export interface SyncResult {
  /** Confirmations that reached the API (or had reached it already). */
  readonly sent: number;
  /** Confirmations the API refused: they stay in the queue, marked. */
  readonly refused: number;
  /** The connection (or the session) failed before the queue was empty. */
  readonly stopped: boolean;
}

/**
 * Sends the pending confirmations of a user, one at a time and in the order they were made, and
 * stops at the first one that cannot be sent for lack of connection: the next ones would fail the
 * same way and the order matters. What the API refuses does not stop the rest, because each stop is
 * its own: the refusal is kept for the operator to see. A stop the API says it already has counts as
 * sent, since the first try got through and only its answer was lost.
 */
export async function synchronize(
  queue: ConfirmationQueue,
  userId: string,
  send: (entry: QueuedConfirmation) => Promise<PickList>,
): Promise<SyncResult> {
  await queue.ready;
  let sent = 0;
  let refused = 0;

  for (const entry of queue.getSnapshot()) {
    if (entry.userId !== userId || entry.status !== 'pending') {
      continue;
    }
    try {
      await send(entry);
      await queue.discard(entry.id);
      sent += 1;
    } catch (error) {
      if (isNetworkFailure(error) || (error instanceof ApiError && error.status === 401)) {
        return { sent, refused, stopped: true };
      }
      if (error instanceof ApiError && error.code === ALREADY_CONFIRMED) {
        await queue.discard(entry.id);
        sent += 1;
        continue;
      }
      await queue.reject(entry.id, {
        code: error instanceof ApiError ? error.code : 'shared.internal_error',
        detail: error instanceof ApiError ? error.detail : '',
      });
      refused += 1;
    }
  }

  return { sent, refused, stopped: false };
}
