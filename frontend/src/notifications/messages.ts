/**
 * What the API announces (ADR 0045): a type that names the fact and a few identifiers and numbers. The
 * screen chooses the words.
 */
export type LiveNotification =
  | { readonly type: 'pick_list_completed'; readonly pickListId: string }
  | {
      readonly type: 'short_pick_reported';
      readonly pickListId: string;
      readonly missingUnits: number;
    };

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

/**
 * Reads the text of a server-sent event. What the screen does not know, or is not shaped as expected, is
 * ignored: a newer API may announce things an older screen cannot show.
 */
export function parseNotification(text: string): LiveNotification | null {
  let body: unknown;
  try {
    body = JSON.parse(text);
  } catch {
    return null;
  }
  if (!isRecord(body) || !isRecord(body.data) || typeof body.data.pickListId !== 'string') {
    return null;
  }
  const { pickListId } = body.data;

  if (body.type === 'outbound.pick_list_completed') {
    return { type: 'pick_list_completed', pickListId };
  }
  if (body.type === 'outbound.short_pick_reported' && typeof body.data.missingUnits === 'number') {
    return { type: 'short_pick_reported', pickListId, missingUnits: body.data.missingUnits };
  }

  return null;
}

/** How long to wait before asking for access again: 4 s, 8 s, 16 s… up to a minute. */
export function retryDelay(failures: number): number {
  return Math.min(60_000, 2_000 * 2 ** failures);
}

/**
 * When to renew access: five minutes before the cookie expires, and not sooner than a minute from now,
 * so a clock that is wrong does not make the screen ask in a loop.
 */
export function renewDelay(expiresAt: string, now: number = Date.now()): number {
  return Math.max(60_000, Date.parse(expiresAt) - now - 5 * 60_000);
}
