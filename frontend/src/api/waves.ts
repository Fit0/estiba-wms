import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import { orderKeys, orderRefreshMs } from './orders';
import type { paths } from './schema';

export type Wave = Schema<'Wave'>;
export type WaveStatus = Wave['status'];
export type PickList = Schema<'PickList'>;
export type PickListStatus = PickList['status'];
export type WaveSearch = NonNullable<paths['/api/waves']['get']['parameters']['query']>;

export const waveKeys = {
  all: ['waves'] as const,
  lists: () => [...waveKeys.all, 'list'] as const,
  list: (search: WaveSearch) => [...waveKeys.lists(), search] as const,
  detail: (id: string) => [...waveKeys.all, 'detail', id] as const,
  pickLists: (waveId: string) => [...waveKeys.all, 'pick-lists', waveId] as const,
};

/** The waves of a page, newest first as the API sorts them, asked again every few seconds. */
export function useWaves(search: WaveSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: waveKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<Wave>> =>
      unwrapPage(await api.GET('/api/waves', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    refetchInterval: orderRefreshMs,
  });
}

export function useWave(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: waveKeys.detail(id),
    enabled: id !== '',
    queryFn: async ({ signal }): Promise<Wave> =>
      unwrap(await api.GET('/api/waves/{id}', { params: { path: { id } }, signal })),
    refetchInterval: orderRefreshMs,
  });
}

/** The pick lists of a wave, with how much of each is picked: the live state of the floor. */
export function usePickLists(waveId: string) {
  const api = useApi();

  return useQuery({
    queryKey: waveKeys.pickLists(waveId),
    enabled: waveId !== '',
    queryFn: async ({ signal }): Promise<PickList[]> =>
      unwrap(await api.GET('/api/pick-lists', { params: { query: { waveId } }, signal })),
    refetchInterval: orderRefreshMs,
  });
}

/** Every change answers with the wave as it is now; waves and orders (their status moves) refresh. */
function useWaveMutation<Variables>(
  id: string | undefined,
  call: (variables: Variables) => Promise<Wave>,
) {
  const queries = useQueryClient();

  return useMutation({
    mutationFn: call,
    onSuccess: async (wave) => {
      queries.setQueryData(waveKeys.detail(id ?? wave.id), wave);
      await Promise.all([
        queries.invalidateQueries({ queryKey: waveKeys.lists() }),
        queries.invalidateQueries({ queryKey: waveKeys.pickLists(wave.id) }),
        queries.invalidateQueries({ queryKey: orderKeys.all }),
      ]);
    },
  });
}

export function useCreateWave() {
  const api = useApi();

  return useWaveMutation(undefined, async (orderIds: string[]) =>
    unwrap(await api.POST('/api/waves', { body: { orderIds } })),
  );
}

/** Sends the wave to the floor, shared among `pickLists` lists (one per operator). */
export function useReleaseWave(id: string) {
  const api = useApi();

  return useWaveMutation(id, async (pickLists: number) =>
    unwrap(
      await api.POST('/api/waves/{id}/release', {
        params: { path: { id } },
        // The OpenAPI document types this optional body as an empty object; the API reads `pickLists`.
        body: { pickLists } as unknown as Record<string, never>,
      }),
    ),
  );
}

export function useCancelWave(id: string) {
  const api = useApi();

  return useWaveMutation<undefined>(id, async () =>
    unwrap(await api.POST('/api/waves/{id}/cancel', { params: { path: { id } } })),
  );
}
