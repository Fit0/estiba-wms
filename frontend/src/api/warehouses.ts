import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type Warehouse = Schema<'Warehouse'>;
export type Location = Schema<'Location'>;
export type LocationSearch = NonNullable<
  paths['/api/warehouses/{warehouseId}/locations']['get']['parameters']['query']
>;

/** The keys of the warehouses in the cache, from the widest to the narrowest. */
export const warehouseKeys = {
  all: ['warehouses'] as const,
  list: () => [...warehouseKeys.all, 'list'] as const,
  detail: (id: string) => [...warehouseKeys.all, 'detail', id] as const,
  locations: (warehouseId: string) => [...warehouseKeys.all, 'locations', warehouseId] as const,
  locationList: (warehouseId: string, search: LocationSearch) =>
    [...warehouseKeys.locations(warehouseId), search] as const,
};

export function useWarehouses() {
  const api = useApi();

  return useQuery({
    queryKey: warehouseKeys.list(),
    queryFn: async ({ signal }): Promise<Warehouse[]> =>
      unwrap(await api.GET('/api/warehouses', { signal })),
  });
}

/** A warehouse with its zones. */
export function useWarehouse(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: warehouseKeys.detail(id),
    queryFn: async ({ signal }): Promise<Warehouse> =>
      unwrap(await api.GET('/api/warehouses/{id}', { params: { path: { id } }, signal })),
  });
}

/** The locations of a warehouse, a page at a time. */
export function useLocations(warehouseId: string, search: LocationSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: warehouseKeys.locationList(warehouseId, search),
    queryFn: async ({ signal }): Promise<Page<Location>> =>
      unwrapPage(
        await api.GET('/api/warehouses/{warehouseId}/locations', {
          params: { path: { warehouseId }, query: search },
          signal,
        }),
      ),
    placeholderData: keepPreviousData,
  });
}

type WarehouseBody = NonNullable<
  paths['/api/warehouses']['post']['requestBody']
>['content']['application/json'];
type ZoneBody = NonNullable<
  paths['/api/warehouses/{warehouseId}/zones']['post']['requestBody']
>['content']['application/json'];
type LocationBody = NonNullable<
  paths['/api/warehouses/{warehouseId}/locations']['post']['requestBody']
>['content']['application/json'];

export function useCreateWarehouse() {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (body: WarehouseBody): Promise<Warehouse> =>
      unwrap(await api.POST('/api/warehouses', { body })),
    onSuccess: () => queries.invalidateQueries({ queryKey: warehouseKeys.list() }),
  });
}

export function useCreateZone(warehouseId: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (body: ZoneBody) =>
      unwrap(
        await api.POST('/api/warehouses/{warehouseId}/zones', {
          params: { path: { warehouseId } },
          body,
        }),
      ),
    onSuccess: () => queries.invalidateQueries({ queryKey: warehouseKeys.detail(warehouseId) }),
  });
}

export function useCreateLocation(warehouseId: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (body: LocationBody): Promise<Location> =>
      unwrap(
        await api.POST('/api/warehouses/{warehouseId}/locations', {
          params: { path: { warehouseId } },
          body,
        }),
      ),
    onSuccess: () => queries.invalidateQueries({ queryKey: warehouseKeys.locations(warehouseId) }),
  });
}

/** Blocks a location with a reason, or unblocks it when `reason` is left out. */
export function useSetLocationBlocked(warehouseId: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, reason }: { id: string; reason?: string }): Promise<Location> =>
      reason === undefined
        ? unwrap(await api.POST('/api/locations/{id}/unblock', { params: { path: { id } } }))
        : unwrap(
            await api.POST('/api/locations/{id}/block', {
              params: { path: { id } },
              body: { reason },
            }),
          ),
    onSuccess: () => queries.invalidateQueries({ queryKey: warehouseKeys.locations(warehouseId) }),
  });
}

/**
 * The active docks of a warehouse, where a delivery can be received. The API cannot filter the
 * locations by type, so this reads them all, a page of 100 at a time, and keeps the docks.
 */
export function useDockLocations(warehouseId: string | undefined) {
  const api = useApi();
  const id = warehouseId ?? '';

  return useQuery({
    queryKey: [...warehouseKeys.locations(id), 'docks'] as const,
    enabled: warehouseId !== undefined,
    queryFn: async ({ signal }): Promise<Location[]> => {
      const docks: Location[] = [];
      for (let page = 1; page <= 50; page += 1) {
        const result = unwrapPage(
          await api.GET('/api/warehouses/{warehouseId}/locations', {
            params: {
              path: { warehouseId: id },
              query: { page, itemsPerPage: 100, status: 'active' },
            },
            signal,
          }),
        );
        docks.push(...result.items.filter((location) => location.type === 'dock'));
        if (page * 100 >= result.total) {
          break;
        }
      }

      return docks;
    },
  });
}
