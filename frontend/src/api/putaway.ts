import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import { inboundKeys } from './inbound';
import type { paths } from './schema';
import { stockKeys } from './stock';

export type PutawayTask = Schema<'PutawayTask'>;
export type PutawayStatus = PutawayTask['status'];
export type PutawaySearch = NonNullable<paths['/api/putaway-tasks']['get']['parameters']['query']>;
export type ConfirmPutawayBody = NonNullable<
  paths['/api/putaway-tasks/{id}/confirm']['post']['requestBody']
>['content']['application/json'];

/** How often the list asks again: the stock of a confirmed task moves a moment later (ADR 0012). */
export const putawayRefreshMs = 10_000;

export const putawayKeys = {
  all: ['putaway-tasks'] as const,
  lists: () => [...putawayKeys.all, 'list'] as const,
  list: (search: PutawaySearch) => [...putawayKeys.lists(), search] as const,
};

/** The putaway tasks of a page and how many there are in all. */
export function usePutawayTasks(search: PutawaySearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: putawayKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<PutawayTask>> =>
      unwrapPage(await api.GET('/api/putaway-tasks', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    refetchInterval: putawayRefreshMs,
  });
}

/** What changes after a task does: the lists of tasks, the stock and the receipts that show it. */
function useRefreshAfterChange() {
  const queries = useQueryClient();

  return async (): Promise<void> => {
    await Promise.all([
      queries.invalidateQueries({ queryKey: putawayKeys.all }),
      queries.invalidateQueries({ queryKey: stockKeys.all }),
      queries.invalidateQueries({ queryKey: inboundKeys.receipts }),
    ]);
  };
}

/** Puts the goods of a task in a location. The answer is the task, confirmed. */
export function useConfirmPutaway() {
  const api = useApi();
  const refresh = useRefreshAfterChange();

  return useMutation({
    mutationFn: async (variables: { id: string; body: ConfirmPutawayBody }): Promise<PutawayTask> =>
      unwrap(
        await api.POST('/api/putaway-tasks/{id}/confirm', {
          params: { path: { id: variables.id } },
          body: variables.body,
        }),
      ),
    onSuccess: refresh,
  });
}

/** Gives some units of a task to a new one, so they can go to another location. */
export function useSplitPutaway() {
  const api = useApi();
  const refresh = useRefreshAfterChange();

  return useMutation({
    mutationFn: async (variables: { id: string; units: number }): Promise<PutawayTask> =>
      unwrap(
        await api.POST('/api/putaway-tasks/{id}/split', {
          params: { path: { id: variables.id } },
          body: { units: variables.units },
        }),
      ),
    onSuccess: refresh,
  });
}
