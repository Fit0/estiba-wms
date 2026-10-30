import { useQuery } from '@tanstack/react-query';
import { unwrap } from './ApiError';
import { useApi } from './ApiProvider';
import { orderRefreshMs } from './orders';
import type { PickList } from './waves';

export const pickingKeys = {
  all: ['picking'] as const,
  lists: () => [...pickingKeys.all, 'lists'] as const,
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
