import type { PickList } from '../api/waves';
import type { QueuedConfirmation } from './ConfirmationQueue';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

/**
 * A pick list as the operator has left it: what the API says, with the confirmations that have not
 * been sent yet applied on top. The API's answer is never changed, so asking again cannot undo what
 * the operator did; when a confirmation is sent, the API's list already has it and the overlay has
 * nothing left to add.
 */
export function applyQueued(list: PickList, queued: readonly QueuedConfirmation[]): PickList {
  const mine = queued.filter((entry) => entry.status === 'pending' && entry.listId === list.id);
  if (mine.length === 0) {
    return list;
  }

  let lines = list.lines;
  let picked = list.unitsPicked;
  for (const entry of mine) {
    lines = lines.map((line) => {
      if (!isRecord(line) || line.position !== entry.position || line.confirmed === true) {
        return line;
      }
      const asked = typeof line.quantityToPick === 'number' ? line.quantityToPick : 0;
      picked += entry.confirmation.units;

      return {
        ...line,
        confirmed: true,
        quantityPicked: entry.confirmation.units,
        shortReason: entry.confirmation.units < asked ? (entry.confirmation.reason ?? null) : null,
      };
    });
  }
  const everyStopDone = lines.every((line) => isRecord(line) && line.confirmed === true);

  return {
    ...list,
    lines,
    unitsPicked: picked,
    status: list.status === 'in_progress' && everyStopDone ? 'completed' : list.status,
  };
}
