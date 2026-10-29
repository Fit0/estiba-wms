import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { TooltipProvider } from './components/ui/Tooltip';
import { DesignGalleryPage } from './pages/DesignGalleryPage';
import { expectNoViolations } from './testing/axe';
import { json, problem } from './testing/fakeApi';
import * as inboundFixtures from './testing/inboundFixtures';
import { paged } from './testing/paged';
import { apiOf, noSession, open, withSession } from './testing/renderApp';

/**
 * The accessibility audit of UI-04: axe over every screen that exists, in both languages and with
 * the dialogs open. What jsdom cannot judge (contrast, size of targets) is covered elsewhere; see
 * `testing/axe.ts`.
 */
const zone = {
  id: 'z-a',
  warehouseId: 'w-1',
  code: 'A',
  name: 'Moda mujer',
  storageClass: 'ambient',
  pickSequence: 10,
  families: [],
};
const warehouse = { id: 'w-1', code: 'ZGZ1', name: 'Zaragoza', zones: [zone] };
const location = {
  id: 'l-1',
  warehouseId: 'w-1',
  zoneId: 'z-a',
  code: 'A-01-01',
  type: 'reserve',
  pickSequence: 10,
  status: 'active',
};

const supervisor = apiOf(['supervisor'], {
  ...withSession(['supervisor']),
  'GET /api/warehouses': () => json([warehouse]),
  'GET /api/warehouses/w-1': () => json(warehouse),
  'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=30': () =>
    new Response(JSON.stringify([location]), {
      status: 200,
      headers: { 'Content-Type': 'application/json', 'X-Total-Count': '1' },
    }),
});

describe('accessibility (axe)', () => {
  it('the sign-in page, in Spanish and in English', async () => {
    const user = userEvent.setup();
    open('/login', apiOf(['operator'], noSession));
    await screen.findByRole('heading', { name: 'Iniciar sesión' });
    await expectNoViolations();

    await user.click(screen.getByRole('button', { name: 'Cambiar a inglés' }));
    await screen.findByRole('heading', { name: 'Sign in' });
    await expectNoViolations();
  });

  it('the home page of the shell, with the sidebar open and in the touch density', async () => {
    const user = userEvent.setup();
    open('/', supervisor);
    await screen.findByRole('navigation', { name: 'Navegación principal' });
    await expectNoViolations();

    await user.click(screen.getByRole('button', { name: /Densidad compacta/ }));
    await user.click(screen.getByRole('button', { name: /Densidad cómoda/ }));
    await expectNoViolations();
  });

  it('the list of warehouses and its form', async () => {
    const user = userEvent.setup();
    open('/warehouses', supervisor);
    await screen.findByRole('link', { name: 'Abrir Zaragoza' });
    await expectNoViolations();

    await user.click(screen.getByRole('button', { name: 'Nuevo almacén' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo almacén' });
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    await within(dialog).findAllByText('Escribe un valor.');
    await expectNoViolations();
  });

  it('one warehouse, with its zones, its locations and the dialog to block one', async () => {
    const user = userEvent.setup();
    open('/warehouses/w-1', supervisor);
    await screen.findByText('A-01-01');
    await expectNoViolations();

    await user.click(screen.getByRole('button', { name: 'Bloquear A-01-01' }));
    await screen.findByRole('dialog', { name: 'Bloquear la ubicación A-01-01' });
    await expectNoViolations();
  });

  it('the catalogue and one product, with the dialog to discontinue it', async () => {
    const user = userEvent.setup();
    const product = {
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
      trackedByLot: true,
      trackedByExpiry: true,
      minShelfLifeAtReceiptDays: 60,
      minShelfLifeAtShipmentDays: 20,
      turnoverClass: 'A',
      gtins: ['8412345678905'],
      packagingLevels: [{ type: 'case', baseUnits: 12 }],
    };
    const catalogue = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/products?page=1&itemsPerPage=30': () => paged([product]),
      'GET /api/products/p-1': () => json(product),
      'GET /api/products/p-1/stock': () =>
        json({
          productId: 'p-1',
          sku: 'LAC-01',
          name: 'Yogur natural',
          onHand: 0,
          available: 0,
          quarantine: 0,
          damaged: 0,
          onHold: 0,
          warehouses: [],
        }),
      'GET /api/stock-items?productId=p-1&page=1&itemsPerPage=30': () => paged([]),
    });
    open('/products', catalogue);
    await user.click(await screen.findByRole('link', { name: 'Abrir LAC-01' }));
    await screen.findByRole('heading', { name: 'LAC-01 · Yogur natural' });
    await screen.findAllByText('Este producto no tiene stock.');
    await expectNoViolations();

    await user.click(screen.getByRole('button', { name: 'Descatalogar' }));
    await screen.findByRole('dialog', { name: 'Descatalogar LAC-01' });
    await expectNoViolations();
  });

  it('the forms of the catalogue: new product with its errors, and the ones of one product', async () => {
    const user = userEvent.setup();
    const product = { ...inboundFixtures.product, family: null };
    const forms = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/products?page=1&itemsPerPage=30': () => paged([product]),
      'GET /api/products/p-1': () => json(product),
      'GET /api/products/p-1/stock': () => problem(404, 'shared.not_found'),
      'GET /api/stock-items?productId=p-1&page=1&itemsPerPage=30': () => paged([]),
    });
    open('/products', forms);
    await user.click(await screen.findByRole('button', { name: 'Nuevo producto' }));
    let dialog = await screen.findByRole('dialog', { name: 'Nuevo producto' });
    await user.click(within(dialog).getByRole('checkbox', { name: 'Control por caducidad' }));
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    await within(dialog).findAllByText('Escribe un número entero, sin signo.');
    await expectNoViolations();
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }));

    await user.click(await screen.findByRole('link', { name: 'Abrir CAM-LIN' }));
    await screen.findByRole('heading', { name: 'CAM-LIN · Camisa de lino' });
    for (const [button, title] of [
      ['Editar', 'Editar CAM-LIN'],
      ['Añadir GTIN', 'Añadir un GTIN a CAM-LIN'],
      ['Definir embalaje', 'Embalaje de CAM-LIN'],
      ['Clasificar', 'Clasificar CAM-LIN'],
    ] as const) {
      await user.click(screen.getByRole('button', { name: button }));
      dialog = await screen.findByRole('dialog', { name: title });
      await expectNoViolations();
      await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
    }
  });

  it('the deliveries, one delivery with the dialog to open a receipt, and the receipt', async () => {
    const user = userEvent.setup();
    const delivery = inboundFixtures.asn();
    const counted = {
      lineNumber: 1,
      productId: 'p-1',
      units: 250,
      lot: null,
      expiryDate: null,
      condition: 'good',
      receivedAt: '2026-10-28T08:20:00+00:00',
    };
    const inbound = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/warehouses': () => json([warehouse]),
      'GET /api/asns?page=1&itemsPerPage=30': () => paged([delivery]),
      'GET /api/asns/a-1': () => json(delivery),
      'GET /api/products/p-1': () => json(inboundFixtures.product),
      'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=100&status=active': () =>
        paged([{ ...location, id: 'l-D-01', code: 'D-01', type: 'dock' }]),
      'GET /api/receipts/r-1': () =>
        json(
          inboundFixtures.receipt({
            overReceivedUnits: 10,
            lines: [counted],
            discrepancies: [{ type: 'excess', productId: 'p-1', units: 10 }],
          }),
        ),
    });
    const { router } = open('/inbound', inbound);
    await user.click(await screen.findByRole('link', { name: 'Abrir ASN-2026-0042' }));
    await screen.findByRole('heading', { name: 'ASN-2026-0042 · Textiles del Ebro' });
    await screen.findByText('CAM-LIN · Camisa de lino');
    await expectNoViolations();

    await user.click(screen.getByRole('button', { name: 'Abrir recepción' }));
    const dialog = await screen.findByRole('dialog', { name: 'Abrir recepción de ASN-2026-0042' });
    await within(dialog).findByRole('combobox', { name: 'Muelle' });
    await expectNoViolations();
    await user.keyboard('{Escape}');

    await router.navigate('/receipts/r-1');
    await screen.findByRole('heading', { name: 'Recepción de ASN-2026-0042' });
    await screen.findAllByText('CAM-LIN · Camisa de lino');
    await user.click(screen.getByRole('button', { name: 'Contar' }));
    await screen.findByText('Escribe un código de 8 a 14 cifras.');
    await expectNoViolations();
  });

  it('the stock, on hand and as a ledger of movements', async () => {
    const user = userEvent.setup();
    const stock = apiOf(['operator'], {
      ...withSession(['operator']),
      'GET /api/warehouses': () => json([warehouse]),
      'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=100': () => paged([location]),
      'GET /api/stock-items?page=1&itemsPerPage=30&warehouseId=w-1': () =>
        paged([inboundFixtures.stockLine('s-1', 'A-01-01')]),
      'GET /api/stock-movements?page=1&itemsPerPage=30': () =>
        paged([inboundFixtures.movement('m-1')]),
    });
    open('/stock', stock);
    await screen.findByText('A-01-01', { selector: 'td' });
    await expectNoViolations();

    await user.click(screen.getByRole('tab', { name: 'Movimientos' }));
    await screen.findByText('ASN-2026-0042');
    await expectNoViolations();
  });

  it('the command palette', async () => {
    const user = userEvent.setup();
    open('/', apiOf(['operator'], withSession(['operator'])));
    await user.click(await screen.findByRole('button', { name: 'Abrir la paleta de comandos' }));
    await screen.findByRole('dialog', { name: 'Paleta de comandos' });
    await expectNoViolations();
  });

  it('the component gallery', async () => {
    render(
      <TooltipProvider>
        <DesignGalleryPage />
      </TooltipProvider>,
    );
    await expectNoViolations();
  });
});

describe('the audit itself', () => {
  it('fails on a page that is not accessible', async () => {
    render(<input type="text" />);

    await expect(expectNoViolations()).rejects.toThrow(/label/);
  });
});
