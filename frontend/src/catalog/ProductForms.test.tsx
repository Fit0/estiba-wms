import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const product = (overrides: Record<string, unknown> = {}) => ({
  id: 'p-1',
  sku: 'LAC-01',
  name: 'Yogur natural',
  baseUnit: 'each',
  storageClass: 'chilled',
  status: 'active',
  lengthMm: 120,
  widthMm: 80,
  heightMm: 60,
  weightGrams: 450,
  trackedByLot: false,
  trackedByExpiry: false,
  family: null,
  turnoverClass: 'C',
  gtins: [],
  packagingLevels: [],
  ...overrides,
});

const pageHandlers = {
  'GET /api/products/p-1': () => json(product()),
  'GET /api/products/p-1/stock': () => problem(404, 'shared.not_found'),
  'GET /api/stock-items?productId=p-1&page=1&itemsPerPage=30': () => paged([]),
};

describe('registering a product', () => {
  it('lets a supervisor register one and opens its page', async () => {
    const user = userEvent.setup();
    let sent: unknown;
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/products?page=1&itemsPerPage=30': () => paged([]),
      'POST /api/products': ({ body }) => {
        sent = body;

        return json(product({ id: 'p-new', sku: 'NUEVO-1', name: 'Producto nuevo' }), 201);
      },
      'GET /api/products/p-new': () => json(product({ id: 'p-new', sku: 'NUEVO-1' })),
    });
    open('/products', api);

    await user.click(await screen.findByRole('button', { name: 'Nuevo producto' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo producto' });
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText(/Usa letras, cifras y/)).toBeInTheDocument();
    expect(api.calls).not.toContain('POST /api/products');

    await user.type(within(dialog).getByLabelText('SKU'), 'nuevo-1');
    await user.type(within(dialog).getByLabelText('Nombre'), 'Producto nuevo');
    await user.type(within(dialog).getByLabelText('Largo (mm)'), '100');
    await user.type(within(dialog).getByLabelText('Ancho (mm)'), '50');
    await user.type(within(dialog).getByLabelText('Alto (mm)'), '20');
    await user.type(within(dialog).getByLabelText('Peso (g)'), '300');
    await user.click(within(dialog).getByRole('checkbox', { name: 'Control por caducidad' }));
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(
      await within(dialog).findByText('El control por caducidad exige control por lote.'),
    ).toBeInTheDocument();

    await user.click(within(dialog).getByRole('checkbox', { name: 'Control por lote' }));
    await user.type(within(dialog).getByLabelText(/en la recepción/), '60');
    await user.type(within(dialog).getByLabelText('Familia (opcional)'), 'lacteos');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await screen.findByRole('heading', { name: /NUEVO-1/ })).toBeInTheDocument();
    expect(sent).toEqual({
      sku: 'NUEVO-1',
      name: 'Producto nuevo',
      baseUnit: 'each',
      storageClass: 'ambient',
      lengthMm: 100,
      widthMm: 50,
      heightMm: 20,
      weightGrams: 300,
      trackedByLot: true,
      trackedByExpiry: true,
      minShelfLifeAtReceiptDays: 60,
      minShelfLifeAtShipmentDays: null,
      family: 'LACTEOS',
      turnoverClass: 'C',
    });
  });

  it('shows what the API says when the SKU already exists, inside the form', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/products?page=1&itemsPerPage=30': () => paged([]),
      'POST /api/products': () => problem(403, 'catalog.sku_taken'),
    });
    open('/products', api);

    await user.click(await screen.findByRole('button', { name: 'Nuevo producto' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo producto' });
    await user.type(within(dialog).getByLabelText('SKU'), 'A-1');
    await user.type(within(dialog).getByLabelText('Nombre'), 'Algo');
    for (const label of ['Largo (mm)', 'Ancho (mm)', 'Alto (mm)', 'Peso (g)']) {
      await user.type(within(dialog).getByLabelText(label), '1');
    }
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('catalog.sku_taken');
    expect(screen.getByRole('dialog', { name: 'Nuevo producto' })).toBeInTheDocument();
  });

  it('does not offer it to an operator', async () => {
    open(
      '/products',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/products?page=1&itemsPerPage=30': () => paged([]),
      }),
    );

    await screen.findByRole('heading', { name: 'Productos' });
    expect(screen.queryByRole('button', { name: 'Nuevo producto' })).not.toBeInTheDocument();
  });
});

describe('changing a product', () => {
  it('sends only the fields that changed, as a merge patch', async () => {
    const user = userEvent.setup();
    let sent: unknown;
    let contentType: string | null = null;
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...pageHandlers,
      'PATCH /api/products/p-1': ({ body, headers }) => {
        sent = body;
        contentType = headers.get('Content-Type');

        return json(product({ name: 'Yogur griego' }));
      },
    });
    open('/products/p-1', api);

    await user.click(await screen.findByRole('button', { name: 'Editar' }));
    const dialog = await screen.findByRole('dialog', { name: 'Editar LAC-01' });
    expect(within(dialog).getByLabelText('Largo (mm)')).toHaveValue('120');
    await user.clear(within(dialog).getByLabelText('Nombre'));
    await user.type(within(dialog).getByLabelText('Nombre'), 'Yogur griego');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent).toEqual({ name: 'Yogur griego' });
    expect(contentType).toBe('application/merge-patch+json');
    expect(await screen.findByRole('heading', { name: 'LAC-01 · Yogur griego' })).toBeVisible();
  });

  it('does not ask the API when nothing changed', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], { ...withSession(['supervisor']), ...pageHandlers });
    open('/products/p-1', api);

    await user.click(await screen.findByRole('button', { name: 'Editar' }));
    const dialog = await screen.findByRole('dialog', { name: 'Editar LAC-01' });
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(api.calls.some((call) => call.startsWith('PATCH'))).toBe(false);
  });

  it('adds a GTIN and shows the API refusal inside the form', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...pageHandlers,
      'POST /api/products/p-1/gtins': ({ body }) => {
        attempts += 1;

        return attempts === 1
          ? problem(403, 'catalog.gtin_invalid')
          : json(product({ gtins: [(body as { gtin: string }).gtin] }));
      },
    });
    open('/products/p-1', api);

    await user.click(await screen.findByRole('button', { name: 'Añadir GTIN' }));
    const dialog = await screen.findByRole('dialog', { name: 'Añadir un GTIN a LAC-01' });
    await user.type(within(dialog).getByLabelText('Código de barras (GTIN)'), '12');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText('Escribe un código de 8 a 14 cifras.')).toBeVisible();

    await user.clear(within(dialog).getByLabelText('Código de barras (GTIN)'));
    await user.type(within(dialog).getByLabelText('Código de barras (GTIN)'), '8412345678905');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('catalog.gtin_invalid');

    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(await screen.findByText('8412345678905')).toBeVisible();
  });

  it('defines a packaging level and classifies the product', async () => {
    const user = userEvent.setup();
    const sent: Record<string, unknown> = {};
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...pageHandlers,
      'POST /api/products/p-1/packaging-levels': ({ body }) => {
        sent.packaging = body;

        return json(product({ packagingLevels: [{ type: 'case', baseUnits: 12 }] }));
      },
      'POST /api/products/p-1/classification': ({ body }) => {
        sent.classification = body;

        return json(product({ family: 'LACTEOS', turnoverClass: 'C' }));
      },
    });
    open('/products/p-1', api);

    await user.click(await screen.findByRole('button', { name: 'Definir embalaje' }));
    let dialog = await screen.findByRole('dialog', { name: 'Embalaje de LAC-01' });
    await user.type(within(dialog).getByLabelText('Unidades base'), '12');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent.packaging).toEqual({ type: 'case', baseUnits: 12 });
    expect(await screen.findByText('Caja: 12 unidades')).toBeVisible();

    await user.click(screen.getByRole('button', { name: 'Clasificar' }));
    dialog = await screen.findByRole('dialog', { name: 'Clasificar LAC-01' });
    await user.type(within(dialog).getByLabelText('Familia (opcional)'), 'lacteos');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent.classification).toEqual({ turnoverClass: 'C', family: 'LACTEOS' });
  });

  it('offers no forms to an operator nor for a discontinued product', async () => {
    open('/products/p-1', apiOf(['operator'], { ...withSession(['operator']), ...pageHandlers }));
    await screen.findByRole('heading', { name: 'LAC-01 · Yogur natural' });
    expect(screen.queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument();
  });

  it('hides the forms of a discontinued product', async () => {
    open(
      '/products/p-1',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        ...pageHandlers,
        'GET /api/products/p-1': () => json(product({ status: 'discontinued' })),
      }),
    );
    await screen.findByRole('heading', { name: 'LAC-01 · Yogur natural' });
    expect(screen.queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument();
  });
});
