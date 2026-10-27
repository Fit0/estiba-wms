import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DataTable, type Column } from './DataTable';

interface Item {
  id: string;
  sku: string;
  quantity: number;
}

const items: Item[] = [
  { id: '1', sku: 'CAM-001', quantity: 12 },
  { id: '2', sku: 'CAM-002', quantity: 1200 },
];

const columns: Column<Item>[] = [
  { id: 'sku', header: 'SKU', kind: 'code', sortable: true, cell: (item) => item.sku },
  { id: 'quantity', header: 'Cantidad', kind: 'number', cell: (item) => item.quantity },
];

function table(props: Partial<Parameters<typeof DataTable<Item>>[0]> = {}) {
  return (
    <DataTable
      columns={columns}
      rows={items}
      rowKey={(item) => item.id}
      caption="Existencias"
      {...props}
    />
  );
}

describe('DataTable', () => {
  it('draws a named table with its headers and rows', () => {
    render(table());

    expect(screen.getByRole('region', { name: 'Existencias' })).toHaveAttribute('tabindex', '0');
    expect(screen.getByRole('columnheader', { name: /SKU/ })).toBeInTheDocument();
    expect(screen.getAllByRole('row')).toHaveLength(3);
    expect(screen.getByText('CAM-002')).toBeInTheDocument();
  });

  it('marks a sortable column as not sorted, and the sorted one with its direction', () => {
    const { rerender } = render(table());

    expect(screen.getByRole('columnheader', { name: /SKU/ })).toHaveAttribute('aria-sort', 'none');
    expect(screen.getByRole('columnheader', { name: 'Cantidad' })).not.toHaveAttribute('aria-sort');

    rerender(table({ sort: { columnId: 'sku', direction: 'desc' } }));

    expect(screen.getByRole('columnheader', { name: /SKU/ })).toHaveAttribute(
      'aria-sort',
      'descending',
    );
  });

  it('asks for ascending first and then flips the direction', async () => {
    const user = userEvent.setup();
    const onSortChange = vi.fn();
    const { rerender } = render(table({ onSortChange }));

    await user.click(screen.getByRole('button', { name: 'Ordenar por SKU' }));
    rerender(table({ onSortChange, sort: { columnId: 'sku', direction: 'asc' } }));
    await user.click(screen.getByRole('button', { name: 'Ordenar por SKU' }));

    expect(onSortChange.mock.calls).toEqual([
      [{ columnId: 'sku', direction: 'asc' }],
      [{ columnId: 'sku', direction: 'desc' }],
    ]);
  });

  it('selects a row and all the rows of the page', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      table({
        selection: { selected: new Set<string>(), onChange, rowLabel: (item) => item.sku },
      }),
    );

    await user.click(screen.getByRole('checkbox', { name: 'Seleccionar CAM-001' }));
    await user.click(
      screen.getByRole('checkbox', { name: 'Seleccionar todas las filas de la página' }),
    );

    expect(onChange.mock.calls[0]?.[0]).toEqual(new Set(['1']));
    expect(onChange.mock.calls[1]?.[0]).toEqual(new Set(['1', '2']));
  });

  it('shows the selected rows and an indeterminate select-all', () => {
    render(
      table({
        selection: { selected: new Set(['1']), onChange: vi.fn(), rowLabel: (item) => item.sku },
      }),
    );

    const all = screen.getByRole<HTMLInputElement>('checkbox', {
      name: 'Seleccionar todas las filas de la página',
    });
    const row = screen.getByRole('row', { name: /CAM-001/ });

    expect(all.indeterminate).toBe(true);
    expect(row).toHaveAttribute('aria-selected', 'true');
    expect(within(row).getByRole('checkbox')).toBeChecked();
  });

  it('unselects the rows of the page and keeps the others', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      table({
        selection: {
          selected: new Set(['1', '2', 'other-page']),
          onChange,
          rowLabel: (item) => item.sku,
        },
      }),
    );

    await user.click(
      screen.getByRole('checkbox', { name: 'Seleccionar todas las filas de la página' }),
    );

    expect(onChange.mock.calls[0]?.[0]).toEqual(new Set(['other-page']));
  });

  it('says it is busy and shows no rows while loading', () => {
    render(table({ status: 'loading' }));

    expect(screen.getByRole('region')).toHaveAttribute('aria-busy', 'true');
    expect(screen.queryByText('CAM-001')).not.toBeInTheDocument();
  });

  it('shows an error with a retry', async () => {
    const user = userEvent.setup();
    const onRetry = vi.fn();
    render(table({ status: 'error', onRetry }));

    expect(screen.getByRole('alert')).toHaveTextContent('No se han podido cargar los datos.');
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(onRetry).toHaveBeenCalledOnce();
  });

  it('shows the empty message, the default or the caller’s', () => {
    const { rerender } = render(table({ rows: [] }));

    expect(screen.getByText('No hay nada que mostrar.')).toBeInTheDocument();

    rerender(table({ rows: [], empty: 'Aún no hay existencias.' }));

    expect(screen.getByText('Aún no hay existencias.')).toBeInTheDocument();
  });

  it('puts the pagination under the table', () => {
    render(table({ pagination: { page: 1, pageSize: 10, total: 2, onPageChange: vi.fn() } }));

    expect(screen.getByRole('navigation', { name: 'Paginación' })).toBeInTheDocument();
  });
});
