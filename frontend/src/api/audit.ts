import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { unwrapPage, type Page } from './ApiError';
import { useApi } from './ApiProvider';
import type { Schema } from './client';
import type { paths } from './schema';

export type AuditEntry = Schema<'AuditEntry'>;
export type AuditSearch = NonNullable<paths['/api/audit-log']['get']['parameters']['query']>;

export const auditKeys = {
  all: ['audit'] as const,
  list: (search: AuditSearch) => [...auditKeys.all, 'list', search] as const,
};

/**
 * A page of the audit trail, the latest event first, narrowed by the filters. The trail only grows,
 * so it is not asked again by itself: what was on the screen when it opened stays until it is changed.
 */
export function useAuditLog(search: AuditSearch) {
  const api = useApi();

  return useQuery({
    queryKey: auditKeys.list(search),
    queryFn: async ({ signal }): Promise<Page<AuditEntry>> =>
      unwrapPage(await api.GET('/api/audit-log', { params: { query: search }, signal })),
    placeholderData: keepPreviousData,
  });
}
