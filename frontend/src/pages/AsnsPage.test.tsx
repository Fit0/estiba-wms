import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { asn } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

describe('the expected deliveries', () => {
  it('lists them with their status and a link to each', async () => {
    open(
      '/inbound',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/asns?page=1&itemsPerPage=30': () =>
          paged(
            [asn(), asn({ id: 'a-2', reference: 'ASN-2026-0043', status: 'partially_received' })],
            2,
          ),
      }),
    );

    expect(await screen.findByRole('heading', { name: 'Entregas esperadas' })).toBeInTheDocument();
    expect(await screen.findByRole('link', { name: 'Abrir ASN-2026-0042' })).toHaveAttribute(
      'href',
      '/inbound/a-1',
    );
    expect(screen.getAllByText('Textiles del Ebro')).toHaveLength(2);
    expect(screen.getByText('Esperada')).toBeInTheDocument();
    expect(screen.getByText('Recibida en parte')).toBeInTheDocument();
  });

  it('asks for the status the person filters by', async () => {
    const user = userEvent.setup();
    const { router } = open(
      '/inbound',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/asns?page=1&itemsPerPage=30': () => paged([asn()]),
        'GET /api/asns?page=1&itemsPerPage=30&status=closed': () => paged([]),
      }),
    );

    await screen.findByText('ASN-2026-0042');
    await user.click(screen.getByRole('combobox', { name: 'Estado' }));
    await user.click(await screen.findByRole('option', { name: 'Cerrada' }));

    expect(await screen.findByText('Ninguna entrega tiene este estado.')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?status=closed');
  });
});
