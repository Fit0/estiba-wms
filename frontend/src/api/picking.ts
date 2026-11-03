import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap } from './ApiError';
import { useApi } from './ApiProvider';
import { orderKeys, orderRefreshMs } from './orders';
import { waveKeys, type PickList } from './waves';

export const pickingKeys = {
  all: ['picking'] as const,
  lists: () => [...pickingKeys.all, 'lists'] as const,
  detail: (id: string) => [...pickingKeys.all, 'detail', id] as const,
};

/** A list that is still to be picked: generated or in progress. */
export function isActive(list: PickList): boolean {
  return list.status === 'generated' || list.status === 'in_progress';
}

/**
 * The pick lists of the floor that are still to be picked, asked again every few seconds. The API
 * returns every list of the tenant; the screen splits them into the operator's own and the free ones.
 */
export function useFloorPickLists() {
  const api = useApi();

  return useQuery({
    queryKey: pickingKeys.lists(),
    queryFn: async ({ signal }): Promise<PickList[]> =>
      unwrap(await api.GET('/api/pick-lists', { signal })).filter(isActive),
    refetchInterval: orderRefreshMs,
  });
}

/** One stop of the walk. The OpenAPI document types the lines as `unknown[]`, so they are read here. */
export interface PickLine {
  readonly position: number;
  readonly orderId: string;
  readonly orderLineNumber: number;
  readonly productId: string;
  readonly locationCode: string;
  readonly lot: string | null;
  readonly expiryDate: string | null;
  readonly quantityToPick: number;
  readonly quantityPicked: number;
  readonly confirmed: boolean;
  readonly shortReason: string | null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function text(source: Record<string, unknown>, key: string): string {
  const value = source[key];

  return typeof value === 'string' ? value : '';
}

function optionalText(source: Record<string, unknown>, key: string): string | null {
  const value = source[key];

  return typeof value === 'string' ? value : null;
}

function whole(source: Record<string, unknown>, key: string): number {
  const value = source[key];

  return typeof value === 'number' ? value : 0;
}

/** The stops of a list in the order of the walk. */
export function pickLines(list: PickList): PickLine[] {
  return list.lines
    .filter(isRecord)
    .map((line) => ({
      position: whole(line, 'position'),
      orderId: text(line, 'orderId'),
      orderLineNumber: whole(line, 'orderLineNumber'),
      productId: text(line, 'productId'),
      locationCode: text(line, 'locationCode'),
      lot: optionalText(line, 'lot'),
      expiryDate: optionalText(line, 'expiryDate'),
      quantityToPick: whole(line, 'quantityToPick'),
      quantityPicked: whole(line, 'quantityPicked'),
      confirmed: line.confirmed === true,
      shortReason: optionalText(line, 'shortReason'),
    }))
    .sort((a, b) => a.position - b.position);
}

/** The next stop to walk to: the first that is not confirmed yet. */
export function nextStop(lines: readonly PickLine[]): PickLine | undefined {
  return lines.find((line) => !line.confirmed);
}

/** One pick list, with its stops. It is asked again so that a change made elsewhere shows up. */
export function usePickList(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: pickingKeys.detail(id),
    enabled: id !== '',
    queryFn: async ({ signal }): Promise<PickList> =>
      unwrap(await api.GET('/api/pick-lists/{id}', { params: { path: { id } }, signal })),
    refetchInterval: orderRefreshMs,
  });
}

/** Every change answers with the list as it is now; the lists of the floor and of the wave refresh. */
function usePickListMutation<Variables>(
  id: string,
  call: (variables: Variables) => Promise<PickList>,
) {
  const queries = useQueryClient();

  return useMutation({
    mutationFn: call,
    onSuccess: async (list) => {
      queries.setQueryData(pickingKeys.detail(id), list);
      await Promise.all([
        queries.invalidateQueries({ queryKey: pickingKeys.lists() }),
        queries.invalidateQueries({ queryKey: waveKeys.pickLists(list.waveId) }),
        queries.invalidateQueries({ queryKey: waveKeys.detail(list.waveId) }),
        queries.invalidateQueries({ queryKey: orderKeys.all }),
      ]);
    },
  });
}

/** The operator takes the list and starts walking it. */
export function useStartPickList(id: string) {
  const api = useApi();

  return usePickListMutation<undefined>(id, async () =>
    unwrap(await api.POST('/api/pick-lists/{id}/start', { params: { path: { id } } })),
  );
}

/** What the operator scanned and counted at a stop. */
export interface PickConfirmation {
  readonly location: string;
  readonly gtin: string;
  readonly units: number;
  readonly lot?: string;
  readonly reason?: string;
}

export function useConfirmPickLine(id: string) {
  const api = useApi();

  return usePickListMutation(
    id,
    async ({ position, ...body }: PickConfirmation & { position: number }) =>
      unwrap(
        await api.POST('/api/pick-lists/{id}/lines/{position}/confirm', {
          params: { path: { id, position: String(position) } },
          body,
        }),
      ),
  );
}
