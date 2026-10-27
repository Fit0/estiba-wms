import { useQueryClient } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it } from 'vitest';
import { AuthClient } from '../auth/AuthClient';
import { fakeApi, json, me, problem, type Handler } from '../testing/fakeApi';
import { ApiError } from './ApiError';
import { ApiProvider } from './ApiProvider';
import { useStockItem, useStockItems } from './stock';

const line = { id: 'a', sku: 'CAM-001', onHand: 12 };

async function setup(extra: Record<string, Handler>) {
  const api = fakeApi({
    'POST /api/auth/login': () => json({ token: 'token-1' }),
    'POST /api/auth/logout': () => new Response(null, { status: 204 }),
    'GET /api/me': () => json(me(['operator'])),
    ...extra,
  });
  const session = new AuthClient(api.send);
  await session.signIn('ana@marea-moda.example', 'secret');
  const wrapper = ({ children }: { children: ReactNode }) => (
    <ApiProvider session={session}>{children}</ApiProvider>
  );

  return { api, session, wrapper };
}

describe('the stock hooks', () => {
  it('load the lines that match the search', async () => {
    const { api, wrapper } = await setup({
      'GET /api/stock-items?status=quarantine': () =>
        new Response(JSON.stringify([line]), {
          status: 200,
          headers: { 'Content-Type': 'application/json', 'X-Total-Count': '41' },
        }),
    });

    const { result } = renderHook(() => useStockItems({ status: 'quarantine' }), { wrapper });

    await waitFor(() => {
      expect(result.current.data).toEqual({ items: [line], total: 41 });
    });
    expect(api.calls.at(-1)).toBe('GET /api/stock-items?status=quarantine');
  });

  it('load a line by its id', async () => {
    const { wrapper } = await setup({ 'GET /api/stock-items/a': () => json(line) });

    const { result } = renderHook(() => useStockItem('a'), { wrapper });

    await waitFor(() => {
      expect(result.current.data).toEqual(line);
    });
  });

  it('fail with the problem of the API, and do not ask again for a refusal', async () => {
    const { api, wrapper } = await setup({
      'GET /api/stock-items': () => problem(403, 'shared.forbidden'),
    });

    const { result } = renderHook(() => useStockItems(), { wrapper });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
    expect(result.current.error).toBeInstanceOf(ApiError);
    expect(result.current.error).toMatchObject({ status: 403, code: 'shared.forbidden' });
    expect(api.calls.filter((call) => call === 'GET /api/stock-items')).toHaveLength(1);
  });

  it('are emptied from the cache when the session ends', async () => {
    const { session, wrapper } = await setup({ 'GET /api/stock-items': () => json([line]) });

    const { result } = renderHook(() => ({ stock: useStockItems(), cache: useQueryClient() }), {
      wrapper,
    });
    await waitFor(() => {
      expect(result.current.stock.data).toEqual({ items: [line], total: 1 });
    });
    expect(result.current.cache.getQueryCache().getAll()).toHaveLength(1);

    await act(async () => {
      await session.signOut();
    });

    expect(result.current.cache.getQueryCache().getAll()).toHaveLength(0);
  });
});
