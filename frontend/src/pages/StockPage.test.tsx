import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json } from '../testing/fakeApi';
import { location, movement, product, stockLine, warehouse } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const items = 'GET /api/stock-items?page=1&itemsPerPage=30&warehouseId=w-1';
const moves = 'GET /api/stock-movements?page=1&itemsPerPage=30';

function api(extra = {}) {
  return apiOf(['operator'], {
    ...withSession(['operator']),
    'GET /api/warehouses': () => json([warehouse]),
    'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=100': () =>
      paged([location('A-01-01', 'reserve'), location('A-01-02', 'reserve')]),
    'GET /api/products/p-1': () => json(product),
    [items]: () =>
      paged(
        [
          stockLine('s-1', 'A-01-01'),
          stockLine('s-2', 'A-01-02', { status: 'quarantine', lot: 'L9' }),
        ],
        2,
      ),
    [moves]: () =>
      paged(
        [
          movement('m-2', { type: 'issue', change: -30, balanceAfter: 70, reference: 'ORD-7' }),
          movement('m-1'),
        ],
        2,
      ),
    ...extra,
  });
}

describe('the stock', () => {
  it('shows the lines of the active warehouse with their status', async () => {
    open('/stock', api());

    expect(await screen.findByRole('heading', { name: 'Stock' })).toBeInTheDocument();
    const table = await screen.findByRole('region', { name: 'Existencias' });
    expect(await within(table).findByText('A-01-02')).toBeInTheDocument();
    expect(within(table).getByText('Cuarentena')).toBeInTheDocument();
    expect(within(table).getByText('L9')).toBeInTheDocument();
    expect(within(table).getAllByRole('link', { name: /^CAM-LIN/ })[0]).toHaveAttribute(
      'href',
      '/products/p-1',
    );
  });

  it('filters by a product found by typing, and lets it go again', async () => {
    const user = userEvent.setup();
    const requests = api({
      'GET /api/products?page=1&itemsPerPage=8&q=camisa': () => paged([product]),
      'GET /api/stock-items?page=1&itemsPerPage=30&productId=p-1&warehouseId=w-1': () =>
        paged([stockLine('s-1', 'A-01-01')], 1),
    });
    const { router } = open('/stock', requests);

    await screen.findByText('A-01-02');
    await user.type(screen.getByRole('searchbox', { name: 'Producto' }), 'camisa');
    await user.click(await screen.findByRole('button', { name: /CAM-LIN/ }));

    expect(await screen.findByText('CAM-LIN · Camisa de lino')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?product=p-1');
    await screen.findByRole('region', { name: 'Existencias' });
    expect(requests.calls).toContain(
      'GET /api/stock-items?page=1&itemsPerPage=30&productId=p-1&warehouseId=w-1',
    );

    await user.click(screen.getByRole('button', { name: 'Quitar el producto' }));
    expect(await screen.findByRole('searchbox', { name: 'Producto' })).toBeInTheDocument();
    expect(router.state.location.search).toBe('');
  });

  it('filters by location and by status', async () => {
    const user = userEvent.setup();
    const requests = api({
      'GET /api/stock-items?page=1&itemsPerPage=30&locationId=l-A-01-02&warehouseId=w-1': () =>
        paged([stockLine('s-2', 'A-01-02')]),
      'GET /api/stock-items?page=1&itemsPerPage=30&locationId=l-A-01-02&warehouseId=w-1&status=damaged':
        () => paged([]),
    });
    open('/stock', requests);

    await screen.findByText('A-01-01');
    await user.click(screen.getByRole('combobox', { name: 'Ubicación' }));
    await user.click(await screen.findByRole('option', { name: 'A-01-02' }));
    await screen.findByRole('region', { name: 'Existencias' });
    await user.click(screen.getByRole('combobox', { name: 'Estado' }));
    await user.click(await screen.findByRole('option', { name: 'Dañado' }));

    expect(await screen.findByText('No hay existencias con estos filtros.')).toBeInTheDocument();
  });

  it('shows the ledger on the movements tab, the latest first', async () => {
    const user = userEvent.setup();
    const { router } = open('/stock', api());

    await screen.findByText('A-01-02');
    await user.click(screen.getByRole('tab', { name: 'Movimientos' }));

    const table = await screen.findByRole('region', { name: 'Movimientos del stock' });
    expect(await within(table).findByText('Salida')).toBeInTheDocument();
    expect(within(table).getByText('-30')).toBeInTheDocument();
    expect(within(table).getByText('+100')).toBeInTheDocument();
    expect(within(table).getByText('ORD-7')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?tab=movements');
  });

  it('goes from a line to its own movements, and back to all of them', async () => {
    const user = userEvent.setup();
    const requests = api({
      'GET /api/stock-movements?page=1&itemsPerPage=30&stockItemId=s-1': () =>
        paged([movement('m-1')], 1),
    });
    open('/stock', requests);

    await user.click(
      await screen.findByRole('link', { name: 'Ver los movimientos de CAM-LIN en A-01-01' }),
    );

    expect(
      await screen.findByText('Solo los movimientos de una línea de stock.'),
    ).toBeInTheDocument();
    expect(await screen.findByText('ASN-2026-0042')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Ver todos' }));
    expect(await screen.findByText('ORD-7')).toBeInTheDocument();
  });

  it('opens on the movements when the address says so', async () => {
    open('/stock?tab=movements', api());

    expect(await screen.findByRole('tab', { name: 'Movimientos' })).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(await screen.findByText('ORD-7')).toBeInTheDocument();
  });
});
