import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const product = (status = 'active') => ({
  id: 'p-1',
  sku: 'LAC-01',
  name: 'Yogur natural',
  baseUnit: 'each',
  storageClass: 'chilled',
  status,
  lengthMm: 120,
  widthMm: 80,
  heightMm: 60,
  weightGrams: 1450,
  trackedByLot: true,
  trackedByExpiry: true,
  minShelfLifeAtReceiptDays: 60,
  minShelfLifeAtShipmentDays: 20,
  family: 'lacteos',
  turnoverClass: 'A',
  gtins: ['8412345678905'],
  packagingLevels: [
    { type: 'case', baseUnits: 12 },
    { type: 'pallet', baseUnits: 480 },
  ],
});

const stock = {
  productId: 'p-1',
  sku: 'LAC-01',
  name: 'Yogur natural',
  onHand: 1200,
  available: 1000,
  quarantine: 120,
  damaged: 30,
  onHold: 50,
  warehouses: [
    {
      warehouseId: 'w-1',
      warehouseCode: 'ZGZ1',
      onHand: 1200,
      available: 1000,
      quarantine: 120,
      damaged: 30,
      onHold: 50,
      locations: 2,
    },
  ],
};

const line = (id: string, locationCode: string, status = 'available') => ({
  id,
  productId: 'p-1',
  sku: 'LAC-01',
  productName: 'Yogur natural',
  warehouseId: 'w-1',
  warehouseCode: 'ZGZ1',
  locationId: `l-${locationCode}`,
  locationCode,
  lot: 'L-2610',
  expiryDate: '2026-12-31',
  status,
  onHand: 600,
});

function api(roles: ('operator' | 'supervisor')[], status = 'active', extra = {}) {
  return apiOf(roles, {
    ...withSession(roles),
    'GET /api/products/p-1': () => json(product(status)),
    'GET /api/products/p-1/stock': () => json(stock),
    'GET /api/stock-items?productId=p-1&page=1&itemsPerPage=30': () =>
      paged([line('s-1', 'A-01-01'), line('s-2', 'A-01-02', 'quarantine')]),
    ...extra,
  });
}

describe('one product', () => {
  it('shows its data, size, tracking, codes, packaging and stock', async () => {
    open('/products/p-1', api(['operator']));

    expect(
      await screen.findByRole('heading', { name: 'LAC-01 · Yogur natural' }),
    ).toBeInTheDocument();
    const data = screen.getByRole('region', { name: 'Datos' });
    expect(within(data).getByText('Refrigerado')).toBeInTheDocument();
    expect(within(data).getByText('lacteos')).toBeInTheDocument();

    expect(screen.getByText('120 × 80 × 60 mm')).toBeInTheDocument();
    expect(screen.getByText('1450 g')).toBeInTheDocument();

    const tracking = screen.getByRole('region', { name: 'Trazabilidad' });
    expect(within(tracking).getByText('60 días')).toBeInTheDocument();
    expect(within(tracking).getByText('20 días')).toBeInTheDocument();

    const codes = screen.getByRole('region', { name: 'Códigos y embalajes' });
    expect(within(codes).getByText('8412345678905')).toBeInTheDocument();
    expect(within(codes).getByText('Caja: 12 unidades')).toBeInTheDocument();
    expect(within(codes).getByText('Palé: 480 unidades')).toBeInTheDocument();

    const warehouses = await screen.findByRole('region', { name: 'Stock por almacén' });
    expect(within(warehouses).getByText('ZGZ1')).toBeInTheDocument();
    expect(within(warehouses).getByText('1200')).toBeInTheDocument();
    const locations = await screen.findByRole('region', { name: 'Stock por ubicación' });
    expect(await within(locations).findByText('A-01-02')).toBeInTheDocument();
    expect(within(locations).getByText('Cuarentena')).toBeInTheDocument();
  });

  it('gives an operator no way to change the status', async () => {
    open('/products/p-1', api(['operator']));

    await screen.findByRole('heading', { name: 'LAC-01 · Yogur natural' });

    expect(screen.queryByRole('button', { name: 'Bloquear' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Descatalogar' })).not.toBeInTheDocument();
  });

  it('lets a supervisor block it after confirming, and unblock it', async () => {
    const user = userEvent.setup();
    let status = 'active';
    const requests = api(['supervisor'], 'active', {
      'GET /api/products/p-1': () => json(product(status)),
      'POST /api/products/p-1/block': () => {
        status = 'blocked';

        return json(product('blocked'));
      },
      'POST /api/products/p-1/unblock': () => {
        status = 'active';

        return json(product('active'));
      },
    });
    open('/products/p-1', requests);

    await user.click(await screen.findByRole('button', { name: 'Bloquear' }));
    const dialog = await screen.findByRole('dialog', { name: 'Bloquear LAC-01' });
    expect(requests.calls).not.toContain('POST /api/products/p-1/block');
    await user.click(within(dialog).getByRole('button', { name: 'Bloquear' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    await user.click(await screen.findByRole('button', { name: 'Desbloquear' }));

    await waitFor(() => {
      expect(requests.calls).toContain('POST /api/products/p-1/unblock');
    });
    expect(requests.calls).toContain('POST /api/products/p-1/block');
  });

  it('discontinues it only after the warning, and then offers nothing more', async () => {
    const user = userEvent.setup();
    const requests = api(['supervisor'], 'active', {
      'POST /api/products/p-1/discontinue': () => json(product('discontinued')),
    });
    open('/products/p-1', requests);

    await user.click(await screen.findByRole('button', { name: 'Descatalogar' }));
    const dialog = await screen.findByRole('dialog', { name: 'Descatalogar LAC-01' });
    expect(within(dialog).getByText(/No se puede deshacer/)).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Descatalogar' }));

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Descatalogar' })).not.toBeInTheDocument();
    });
    expect(screen.queryByRole('button', { name: 'Desbloquear' })).not.toBeInTheDocument();
    expect(requests.calls).toContain('POST /api/products/p-1/discontinue');
  });

  it('keeps the dialog open and says why when the API refuses', async () => {
    const user = userEvent.setup();
    open(
      '/products/p-1',
      api(['supervisor'], 'active', {
        'POST /api/products/p-1/block': () => problem(409, 'catalog.product_already_blocked'),
      }),
    );

    await user.click(await screen.findByRole('button', { name: 'Bloquear' }));
    const dialog = await screen.findByRole('dialog', { name: 'Bloquear LAC-01' });
    await user.click(within(dialog).getByRole('button', { name: 'Bloquear' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('No se ha podido guardar');
  });

  it('says so when the product does not exist', async () => {
    open(
      '/products/p-9',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/products/p-9': () => problem(404, 'catalog.product_not_found'),
        'GET /api/products/p-9/stock': () => problem(404, 'catalog.product_not_found'),
        'GET /api/stock-items?productId=p-9&page=1&itemsPerPage=30': () => paged([]),
      }),
    );

    expect(
      await screen.findByRole('heading', { name: 'No se ha encontrado el producto.' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Volver a los productos' })).toBeInTheDocument();
  });
});
