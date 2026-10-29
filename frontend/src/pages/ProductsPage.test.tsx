import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { problem } from '../testing/fakeApi';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const product = (sku: string, name: string, status = 'active') => ({
  id: `p-${sku}`,
  sku,
  name,
  baseUnit: 'each',
  storageClass: 'ambient',
  status,
  lengthMm: 300,
  widthMm: 220,
  heightMm: 30,
  weightGrams: 250,
  trackedByLot: false,
  trackedByExpiry: false,
  turnoverClass: 'B',
  gtins: [],
  packagingLevels: [],
});

describe('the catalogue', () => {
  it('lists the products with their status and how many there are in all', async () => {
    open(
      '/products',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/products?page=1&itemsPerPage=30': () =>
          paged(
            [
              product('CAM-LIN', 'Camisa de lino'),
              product('PAN-AZ', 'Pantalón azul', 'discontinued'),
            ],
            42,
          ),
      }),
    );

    expect(await screen.findByRole('heading', { name: 'Productos' })).toBeInTheDocument();
    expect(await screen.findByRole('link', { name: 'Abrir CAM-LIN' })).toHaveAttribute(
      'href',
      '/products/p-CAM-LIN',
    );
    expect(screen.getByText('Camisa de lino')).toBeInTheDocument();
    expect(screen.getByText('Descatalogado')).toBeInTheDocument();
    expect(screen.getByText('Mostrando 1–30 de 42')).toBeInTheDocument();
  });

  it('asks the server for the text once the person stops typing', async () => {
    const user = userEvent.setup();
    const api = apiOf(['operator'], {
      ...withSession(['operator']),
      'GET /api/products?page=1&itemsPerPage=30': () => paged([product('CAM-LIN', 'Camisa')]),
      'GET /api/products?page=1&itemsPerPage=30&q=zapato': () =>
        paged([product('ZAP-01', 'Zapato de cuero')]),
    });
    open('/products', api);

    await screen.findByText('Camisa');
    await user.type(screen.getByRole('searchbox', { name: 'Buscar' }), 'zapato');

    expect(await screen.findByText('Zapato de cuero')).toBeInTheDocument();
    expect(api.calls.filter((call) => call.includes('q='))).toEqual([
      'GET /api/products?page=1&itemsPerPage=30&q=zapato',
    ]);
  });

  it('filters by status, and says so when nothing matches', async () => {
    const user = userEvent.setup();
    open(
      '/products',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/products?page=1&itemsPerPage=30': () => paged([product('CAM-LIN', 'Camisa')]),
        'GET /api/products?page=1&itemsPerPage=30&status=blocked': () => paged([]),
      }),
    );

    await screen.findByText('Camisa');
    await user.click(screen.getByRole('combobox', { name: 'Estado' }));
    await user.click(await screen.findByRole('option', { name: 'Bloqueado' }));

    expect(
      await screen.findByText('Ningún producto coincide con la búsqueda.'),
    ).toBeInTheDocument();
  });

  it('keeps the search in the address and asks for the page that is picked', async () => {
    const user = userEvent.setup();
    const api = apiOf(['operator'], {
      ...withSession(['operator']),
      'GET /api/products?page=1&itemsPerPage=30&q=lin': () =>
        paged([product('CAM-LIN', 'Camisa de lino')], 61),
      'GET /api/products?page=2&itemsPerPage=30&q=lin': () =>
        paged([product('PAN-LIN', 'Pantalón de lino')], 61),
    });
    const { router } = open('/products?q=lin', api);

    await screen.findByText('Camisa de lino');
    expect(screen.getByRole('searchbox', { name: 'Buscar' })).toHaveValue('lin');

    await user.click(screen.getByRole('button', { name: 'Página 2' }));

    expect(await screen.findByText('Pantalón de lino')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?q=lin&page=2');
  });

  it('says it when the list cannot be loaded, and tries again', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    open(
      '/products',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/products?page=1&itemsPerPage=30': () => {
          attempts += 1;

          return attempts === 1
            ? problem(403, 'shared.forbidden')
            : paged([product('CAM-LIN', 'Camisa')]);
        },
      }),
    );

    const alert = await screen.findByRole('alert');
    await user.click(within(alert).getByRole('button', { name: 'Reintentar' }));

    await waitFor(() => {
      expect(screen.getByText('Camisa')).toBeInTheDocument();
    });
  });
});
