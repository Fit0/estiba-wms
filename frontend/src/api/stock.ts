import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type StockItem = Schema<'StockItem'>;
export type StockSearch = NonNullable<paths['/api/stock-items']['get']['parameters']['query']>;

/** The keys of the stock in the cache, from the widest to the narrowest. */
export const stockKeys = {
  all: ['stock'] as const,
  lists: () => [...stockKeys.all, 'list'] as const,
  list: (search: StockSearch) => [...stockKeys.lists(), search] as const,
  item: (id: string) => [...stockKeys.all, 'item', id] as const,
};

/**
 * The stock lines of a page and how many there are in all (E03-06). While the next page loads, the
 * one on the screen stays, so the table does not flash empty between pages.
 */
export function useStockItems(search: StockSearch = {}, options: { enabled?: boolean } = {}) {
  const api = useApi();

  return useQuery({
    queryKey: stockKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<StockItem>> =>
      unwrapPage(await api.GET('/api/stock-items', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    enabled: options.enabled ?? true,
  });
}

export function useStockItem(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: stockKeys.item(id),
    queryFn: async ({ signal }): Promise<StockItem> =>
      unwrap(await api.GET('/api/stock-items/{id}', { params: { path: { id } }, signal })),
  });
}

export type StockMovement = Schema<'StockMovement'>;
export type MovementSearch = NonNullable<
  paths['/api/stock-movements']['get']['parameters']['query']
>;

export const movementKeys = {
  all: ['stock-movements'] as const,
  list: (search: MovementSearch) => [...movementKeys.all, 'list', search] as const,
};

/** The ledger of the stock, the latest movement first, a page at a time. */
export function useStockMovements(search: MovementSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: movementKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<StockMovement>> =>
      unwrapPage(await api.GET('/api/stock-movements', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
  });
}
