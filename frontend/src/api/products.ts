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
export function useProducts(search: ProductSearch = {}) {
  const api = useApi();

  return useQuery({
    queryKey: productKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<Product>> =>
      unwrapPage(await api.GET('/api/products', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
  });
}

export function useProduct(id: string) {
  const api = useApi();

  return useQuery({
    queryKey: productKeys.detail(id),
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
