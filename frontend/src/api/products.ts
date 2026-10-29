import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type Product = Schema<'Product'>;
export type ProductStock = Schema<'ProductStock'>;
export type ProductSearch = NonNullable<paths['/api/products']['get']['parameters']['query']>;
export type ProductStatus = Product['status'];

/** What can be done to the status of a product. */
export type ProductAction = 'block' | 'unblock' | 'discontinue';

/** The keys of the products in the cache, from the widest to the narrowest. */
export const productKeys = {
  all: ['products'] as const,
  lists: () => [...productKeys.all, 'list'] as const,
  list: (search: ProductSearch) => [...productKeys.lists(), search] as const,
  detail: (id: string) => [...productKeys.all, 'detail', id] as const,
  stock: (id: string) => [...productKeys.all, 'stock', id] as const,
};

/**
 * The products of a page and how many there are in all. While the next page loads, the one on the
 * screen stays, so the table does not flash empty between pages or between two letters of a search.
 */
export function useProducts(search: ProductSearch = {}, options: { enabled?: boolean } = {}) {
  const api = useApi();

  return useQuery({
    queryKey: productKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<Product>> =>
      unwrapPage(await api.GET('/api/products', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
    enabled: options.enabled ?? true,
  });
}

export function useProduct(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: productKeys.detail(id),
    enabled: id !== '',
    queryFn: async ({ signal }): Promise<Product> =>
      unwrap(await api.GET('/api/products/{id}', { params: { path: { id } }, signal })),
  });
}

/** What the product has on hand and how much of it can be used, in total and by warehouse. */
export function useProductStock(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: productKeys.stock(id),
    queryFn: async ({ signal }): Promise<ProductStock> =>
      unwrap(
        await api.GET('/api/products/{productId}/stock', {
          params: { path: { productId: id } },
          signal,
        }),
      ),
  });
}

/** Blocks, unblocks or discontinues a product. The answer is the product as it is now. */
export function useChangeProductStatus(id: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (action: ProductAction): Promise<Product> => {
      const params = { params: { path: { id } } };

      switch (action) {
        case 'block':
          return unwrap(await api.POST('/api/products/{id}/block', params));
        case 'unblock':
          return unwrap(await api.POST('/api/products/{id}/unblock', params));
        case 'discontinue':
          return unwrap(await api.POST('/api/products/{id}/discontinue', params));
      }
    },
    onSuccess: (product) => {
      queries.setQueryData(productKeys.detail(id), product);
      return queries.invalidateQueries({ queryKey: productKeys.lists() });
    },
  });
}

type RegisterBody = NonNullable<
  paths['/api/products']['post']['requestBody']
>['content']['application/json'];
type UpdateBody = NonNullable<
  paths['/api/products/{id}']['patch']['requestBody']
>['content']['application/merge-patch+json'];
type GtinBody = NonNullable<
  paths['/api/products/{id}/gtins']['post']['requestBody']
>['content']['application/json'];
type PackagingBody = NonNullable<
  paths['/api/products/{id}/packaging-levels']['post']['requestBody']
>['content']['application/json'];
type ClassifyBody = NonNullable<
  paths['/api/products/{id}/classification']['post']['requestBody']
>['content']['application/json'];

/**
 * Every change to a product answers with the product as it is now: it goes into the cache of the
 * product and the lists are asked again, so the page and the catalogue show it without a reload.
 */
function useProductMutation<Variables>(
  id: string | undefined,
  call: (variables: Variables) => Promise<Product>,
) {
  const queries = useQueryClient();

  return useMutation({
    mutationFn: call,
    onSuccess: async (product) => {
      queries.setQueryData(productKeys.detail(id ?? product.id), product);
      await queries.invalidateQueries({ queryKey: productKeys.lists() });
    },
  });
}

export function useRegisterProduct() {
  const api = useApi();

  return useProductMutation(undefined, async (body: RegisterBody) =>
    unwrap(await api.POST('/api/products', { body })),
  );
}

/** A merge patch: only the fields that are in `body` change. */
export function useUpdateProduct(id: string) {
  const api = useApi();

  return useProductMutation(id, async (body: UpdateBody) =>
    unwrap(
      await api.PATCH('/api/products/{id}', {
        params: { path: { id } },
        body,
        headers: { 'Content-Type': 'application/merge-patch+json' },
      }),
    ),
  );
}

export function useAssignGtin(id: string) {
  const api = useApi();

  return useProductMutation(id, async (body: GtinBody) =>
    unwrap(await api.POST('/api/products/{id}/gtins', { params: { path: { id } }, body })),
  );
}

export function useDefinePackagingLevel(id: string) {
  const api = useApi();

  return useProductMutation(id, async (body: PackagingBody) =>
    unwrap(
      await api.POST('/api/products/{id}/packaging-levels', { params: { path: { id } }, body }),
    ),
  );
}

export function useClassifyProduct(id: string) {
  const api = useApi();

  return useProductMutation(id, async (body: ClassifyBody) =>
    unwrap(await api.POST('/api/products/{id}/classification', { params: { path: { id } }, body })),
  );
}
