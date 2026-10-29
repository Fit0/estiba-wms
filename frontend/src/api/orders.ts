import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type Order = Schema<'Order'>;
export type OrderStatus = Order['status'];
export type OrderPriority = Order['priority'];
export type OrderSearch = NonNullable<paths['/api/orders']['get']['parameters']['query']>;
export type RegisterOrderBody = NonNullable<
  paths['/api/orders']['post']['requestBody']
>['content']['application/json'];

/** How often an open order screen asks again, so what the floor does shows up without a reload. */
export const orderRefreshMs = 15_000;

export const orderKeys = {
  all: ['orders'] as const,
  lists: () => [...orderKeys.all, 'list'] as const,
  list: (search: OrderSearch) => [...orderKeys.lists(), search] as const,
  detail: (id: string) => [...orderKeys.all, 'detail', id] as const,
};

/** The orders of a page, the most urgent first, and how many there are in all. */
export function useOrders(search: OrderSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: orderKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<Order>> =>
      unwrapPage(await api.GET('/api/orders', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    refetchInterval: orderRefreshMs,
  });
}

export function useOrder(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: orderKeys.detail(id),
    enabled: id !== '',
    queryFn: async ({ signal }): Promise<Order> =>
      unwrap(await api.GET('/api/orders/{id}', { params: { path: { id } }, signal })),
    refetchInterval: orderRefreshMs,
  });
}

/** Every change answers with the order as it is now: it replaces the cached one and the lists refresh. */
function useOrderMutation<Variables>(
  id: string | undefined,
  call: (variables: Variables) => Promise<Order>,
) {
  const queries = useQueryClient();

  return useMutation({
    mutationFn: call,
    onSuccess: async (order) => {
      queries.setQueryData(orderKeys.detail(id ?? order.id), order);
      await queries.invalidateQueries({ queryKey: orderKeys.lists() });
    },
  });
}

export function useRegisterOrder() {
  const api = useApi();

  return useOrderMutation(undefined, async (body: RegisterOrderBody) =>
    unwrap(await api.POST('/api/orders', { body })),
  );
}

export type OrderAction = 'allocate' | 'accept-backorder' | 'cancel';

export function useChangeOrder(id: string) {
  const api = useApi();

  return useOrderMutation(id, async (action: OrderAction) => {
    const params = { params: { path: { id } } };
    switch (action) {
      case 'allocate':
        return unwrap(await api.POST('/api/orders/{id}/allocate', params));
      case 'accept-backorder':
        return unwrap(await api.POST('/api/orders/{id}/accept-backorder', params));
      case 'cancel':
        return unwrap(await api.POST('/api/orders/{id}/cancel', params));
    }
  });
}
