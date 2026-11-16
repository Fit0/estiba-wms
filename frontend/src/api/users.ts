import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { unwrap, unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type User = Schema<'User'>;
export type UserSearch = NonNullable<paths['/api/users']['get']['parameters']['query']>;

type CreateBody = NonNullable<
  paths['/api/users']['post']['requestBody']
>['content']['application/json'];
type RolesBody = NonNullable<
  paths['/api/users/{id}/roles']['post']['requestBody']
>['content']['application/json'];

export const userKeys = {
  all: ['users'] as const,
  list: (search: UserSearch) => [...userKeys.all, 'list', search] as const,
};

/** The users of the tenant, a page at a time, ordered by email. Only an admin may ask. */
export function useUsers(search: UserSearch) {
  const api = useApi();

  return useQuery({
    queryKey: userKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<User>> =>
      unwrapPage(await api.GET('/api/users', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
  });
}

export function useCreateUser() {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (body: CreateBody): Promise<User> =>
      unwrap(await api.POST('/api/users', { body })),
    onSuccess: () => queries.invalidateQueries({ queryKey: userKeys.all }),
  });
}

/** Replaces the roles of a user with the ones given. */
export function useChangeUserRoles(id: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (body: RolesBody): Promise<User> =>
      unwrap(await api.POST('/api/users/{id}/roles', { params: { path: { id } }, body })),
    onSuccess: () => queries.invalidateQueries({ queryKey: userKeys.all }),
  });
}

/** Disables or enables a user: the same request without a body, to a different address. */
export function useSetUserEnabled(id: string) {
  const api = useApi();
  const queries = useQueryClient();

  return useMutation({
    mutationFn: async (enabled: boolean): Promise<User> =>
      unwrap(
        enabled
          ? await api.POST('/api/users/{id}/enable', { params: { path: { id } } })
          : await api.POST('/api/users/{id}/disable', { params: { path: { id } } }),
      ),
    onSuccess: () => queries.invalidateQueries({ queryKey: userKeys.all }),
  });
}
