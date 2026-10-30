import {
  keepPreviousData,
  useMutation,
  useQueries,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import { productKeys, type Product } from './products';
import type { paths } from './schema';

export type Asn = Schema<'Asn'>;
export type AsnStatus = Asn['status'];
export type Receipt = Schema<'Receipt'>;
export type AsnSearch = NonNullable<paths['/api/asns']['get']['parameters']['query']>;
export type RegisterAsnBody = NonNullable<
  paths['/api/asns']['post']['requestBody']
>['content']['application/json'];
export type ReceiveItemBody = NonNullable<
  paths['/api/receipts/{id}/items']['post']['requestBody']
>['content']['application/json'];

/** The keys of the deliveries and receipts in the cache, from the widest to the narrowest. */
export const inboundKeys = {
  asns: ['asns'] as const,
  asnList: (search: AsnSearch) => [...inboundKeys.asns, 'list', search] as const,
  asn: (id: string) => [...inboundKeys.asns, 'detail', id] as const,
  receipts: ['receipts'] as const,
  receipt: (id: string) => [...inboundKeys.receipts, id] as const,
};

/** The expected deliveries of a page and how many there are in all. */
export function useAsns(search: AsnSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: inboundKeys.asnList(search),
    queryFn: async ({ signal }): Promise<Page<Asn>> =>
      unwrapPage(await api.GET('/api/asns', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
  });
}

export function useAsn(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: inboundKeys.asn(id),
    enabled: id !== '',
    queryFn: async ({ signal }): Promise<Asn> =>
      unwrap(await api.GET('/api/asns/{id}', { params: { path: { id } }, signal })),
  });
}

export function useReceipt(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: inboundKeys.receipt(id),
    queryFn: async ({ signal }): Promise<Receipt> =>
      unwrap(await api.GET('/api/receipts/{id}', { params: { path: { id } }, signal })),
  });
}

/**
 * The products behind a list of ids, by id. The API has no call for several products at once, so
 * each id is its own query and the ones already in the cache cost nothing.
 */
export function useProductsById(ids: readonly string[]): ReadonlyMap<string, Product> {
  const api = useApi();
  const unique = [...new Set(ids)];
  const results = useQueries({
    queries: unique.map((id) => ({
      queryKey: productKeys.detail(id),
      queryFn: async ({ signal }: { signal: AbortSignal }): Promise<Product> =>
        unwrap(await api.GET('/api/products/{id}', { params: { path: { id } }, signal })),
    })),
  });

  const found = new Map<string, Product>();
  results.forEach((result, index) => {
    const id = unique[index];
    if (id !== undefined && result.data !== undefined) {
      found.set(id, result.data);
    }
  });

  return found;
}

/** Opens a receipt for the delivery at a dock. The answer is the receipt. */
export function useOpenReceipt(asnId: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (dockLocationId: string): Promise<Receipt> =>
      unwrap(
        await api.POST('/api/asns/{asnId}/receipts', {
          params: { path: { asnId } },
          body: { dockLocationId },
        }),
      ),
    onSuccess: async (receipt) => {
      queries.setQueryData(inboundKeys.receipt(receipt.id), receipt);
      await queries.invalidateQueries({ queryKey: inboundKeys.asns });
    },
  });
}

/** What changes a receipt: each call answers with the receipt as it is now. */
function useReceiptMutation<Variables>(
  receiptId: string,
  call: (variables: Variables) => Promise<Receipt>,
) {
  const queries = useQueryClient();

  return useMutation({
    mutationFn: call,
    onSuccess: async (receipt) => {
      queries.setQueryData(inboundKeys.receipt(receiptId), receipt);
      await queries.invalidateQueries({ queryKey: inboundKeys.asns });
    },
  });
}

export function useReceiveItem(receiptId: string) {
  const api = useApi();

  return useReceiptMutation(receiptId, async (body: ReceiveItemBody) =>
    unwrap(
      await api.POST('/api/receipts/{id}/items', { params: { path: { id: receiptId } }, body }),
    ),
  );
}

export function useApproveOverReceipt(receiptId: string) {
  const api = useApi();

  return useReceiptMutation<undefined>(receiptId, async () =>
    unwrap(
      await api.POST('/api/receipts/{id}/approve-over-receipt', {
        params: { path: { id: receiptId } },
      }),
    ),
  );
}

export function useCloseReceipt(receiptId: string) {
  const api = useApi();

  return useReceiptMutation<undefined>(receiptId, async () =>
    unwrap(await api.POST('/api/receipts/{id}/close', { params: { path: { id: receiptId } } })),
  );
}

/** Registers an expected delivery. The answer is the delivery, which goes into the cache. */
export function useRegisterAsn() {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (body: RegisterAsnBody): Promise<Asn> =>
      unwrap(await api.POST('/api/asns', { body })),
    onSuccess: async (asn) => {
      queries.setQueryData(inboundKeys.asn(asn.id), asn);
      await queries.invalidateQueries({ queryKey: inboundKeys.asns });
    },
  });
}

export type AsnAction = 'cancel' | 'close';

/** Cancels or closes a delivery; each answers with the delivery as it is now. */
export function useChangeAsn(id: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (action: AsnAction): Promise<Asn> => {
      const params = { params: { path: { id } } };

      return action === 'cancel'
        ? unwrap(await api.POST('/api/asns/{id}/cancel', params))
        : unwrap(await api.POST('/api/asns/{id}/close', params));
    },
    onSuccess: async (asn) => {
      queries.setQueryData(inboundKeys.asn(id), asn);
      await queries.invalidateQueries({ queryKey: inboundKeys.asns });
    },
  });
}
