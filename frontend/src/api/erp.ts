import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type ErpConfirmation = Schema<'ErpConfirmation'>;
export type ConfirmationStatus = ErpConfirmation['status'];
export type ErpImport = Schema<'ErpImport'>;
export type ImportResult = ErpImport['result'];

export type ConfirmationSearch = NonNullable<
  paths['/api/erp/confirmations']['get']['parameters']['query']
>;
export type ImportSearch = NonNullable<paths['/api/erp/imports']['get']['parameters']['query']>;

/** How often the panel asks again: a confirmation that goes out or fails shows by itself. */
export const erpRefreshMs = 10_000;

export const erpKeys = {
  all: ['erp'] as const,
  confirmations: (search: ConfirmationSearch) => [...erpKeys.all, 'confirmations', search] as const,
  imports: (search: ImportSearch) => [...erpKeys.all, 'imports', search] as const,
};

/** The confirmations to the ERP, the latest first. */
export function useErpConfirmations(search: ConfirmationSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: erpKeys.confirmations(search),
    queryFn: async ({ signal }): Promise<Page<ErpConfirmation>> =>
      unwrapPage(await api.GET('/api/erp/confirmations', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    refetchInterval: erpRefreshMs,
  });
}

/** The files of orders the import dealt with, the latest first. */
export function useErpImports(search: ImportSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: erpKeys.imports(search),
    queryFn: async ({ signal }): Promise<Page<ErpImport>> =>
      unwrapPage(await api.GET('/api/erp/imports', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    refetchInterval: erpRefreshMs,
  });
}

/** Puts a failed confirmation back in the queue: the next run of the job tries it again. */
export function useRetryConfirmation() {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (shipmentId: string): Promise<ErpConfirmation> =>
      unwrap(
        await api.POST('/api/erp/confirmations/{shipmentId}/retry', {
          params: { path: { shipmentId } },
        }),
      ),
    onSuccess: () => queries.invalidateQueries({ queryKey: erpKeys.all }),
  });
}
