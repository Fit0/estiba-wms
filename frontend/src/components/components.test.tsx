import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DENSITY_KEY } from '../theme/density';
import { DataTable, nextSort, type Column, type Sort } from './DataTable';
import { DensityToggle } from './DensityToggle';
import { FilterTabs } from './FilterTabs';
import { KpiCard } from './KpiCard';
import { ScannerInput } from './ScannerInput';
import { StatusBadge } from './StatusBadge';

interface Row {
  sku: string;
  quantity: number;
}

const rows: Row[] = [
  { sku: 'MM-1001', quantity: 850 },
  { sku: 'MM-1002', quantity: 12 },
  { sku: 'MM-1003', quantity: 0 },
];

const columns: Column<Row>[] = [
  { key: 'sku', header: 'Referencia', cell: (row) => row.sku, code: true, sortable: true },
  { key: 'quantity', header: 'Cantidad', cell: (row) => row.quantity, numeric: true },
];

function Table({ initialSort = null }: { initialSort?: Sort | null }) {
  const [sort, setSort] = useState<Sort | null>(initialSort);
  const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());

  return (
    <DataTable
      caption="Existencias"
      columns={columns}
      rows={rows}
      rowKey={(row) => row.sku}
      sort={sort}
      onSortChange={setSort}
      selection={{ selected, onChange: setSelected, label: (row) => row.sku }}
      emptyText="Sin filas"
    />
  );
}

describe('StatusBadge', () => {
  it('says the state with words', () => {
    render(<StatusBadge tone="warn">Bajo mínimo</StatusBadge>);

    expect(screen.getByText('Bajo mínimo')).toBeInTheDocument();
  });

  it('adds a dot that a screen reader does not hear when the state is live', () => {
    const { container } = render(
      <StatusBadge tone="info" live>
        Recibiendo
      </StatusBadge>,
    );

    expect(container.querySelector('[aria-hidden="true"]')).not.toBeNull();
  });
});

describe('KpiCard', () => {
  it('shows what it measures, the figure and the change, with the direction in words', () => {
    render(
      <KpiCard
        label="Stock crítico"
        value="18"
        trend={{ direction: 'up', label: '+12,0 % frente al turno anterior', tone: 'bad' }}
      />,
    );

    expect(screen.getByText('Stock crítico')).toBeInTheDocument();
    expect(screen.getByText('18')).toHaveClass('tnum');
    expect(screen.getByText('Sube')).toBeInTheDocument();
    expect(screen.getByText(/\+12,0 %/)).toBeInTheDocument();
  });

  it('is only the figure when there is no change to show', () => {
    render(<KpiCard label="Entradas" value="340" />);

    expect(screen.queryByText('Sube')).not.toBeInTheDocument();
  });
});

describe('FilterTabs', () => {
  const options = [
    { value: 'all', label: 'Todos', count: 14280 },
    { value: 'low', label: 'Bajo mínimo', count: 240 },
  ] as const;

  it('shows which filter is on and how many rows each one has', () => {
    render(<FilterTabs label="Estado" options={options} value="low" onChange={() => undefined} />);

    expect(screen.getByRole('button', { name: /Bajo mínimo/ })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(screen.getByRole('button', { name: /Todos/ })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByText('14.280')).toBeInTheDocument();
  });

  it('tells which filter was picked', async () => {
    const onChange = vi.fn();
    render(<FilterTabs label="Estado" options={options} value="all" onChange={onChange} />);

    await userEvent.setup().click(screen.getByRole('button', { name: /Bajo mínimo/ }));

    expect(onChange).toHaveBeenCalledWith('low');
  });
});

describe('ScannerInput', () => {
  it('hands over the code when the scanner ends it with Enter, and empties itself', async () => {
    const onScan = vi.fn();
    render(<ScannerInput label="Escanear" onScan={onScan} />);
    const input = screen.getByLabelText('Escanear');

    await userEvent.setup().type(input, '8400000000017{Enter}');

    expect(onScan).toHaveBeenCalledExactlyOnceWith('8400000000017');
    expect(input).toHaveValue('');
  });

  it('ignores an Enter with nothing typed', async () => {
    const onScan = vi.fn();
    render(<ScannerInput label="Escanear" onScan={onScan} />);

    await userEvent.setup().type(screen.getByLabelText('Escanear'), '{Enter}');

    expect(onScan).not.toHaveBeenCalled();
  });

  it('flashes a ring for a moment and says the code to a screen reader', async () => {
    render(<ScannerInput label="Escanear" onScan={() => undefined} />);
    const input = screen.getByLabelText('Escanear');

    await userEvent.setup().type(input, 'MM-1001{Enter}');

    expect(input).toHaveClass('ring-ok');
    expect(screen.getByRole('status')).toHaveTextContent('Código leído: MM-1001');
    await vi.waitFor(() => {
      expect(input).not.toHaveClass('ring-ok');
    });
  });
});

describe('DataTable', () => {
  afterEach(() => {
    localStorage.clear();
  });

  it('has a caption, headers of columns and one row per record', () => {
    render(<Table />);

    expect(screen.getByRole('table', { name: 'Existencias' })).toBeInTheDocument();
    expect(screen.getAllByRole('columnheader')).toHaveLength(3);
    expect(screen.getAllByRole('row')).toHaveLength(4);
  });

  it('right aligns the quantities and gives them tabular figures', () => {
    render(<Table />);

    const cell = screen.getByRole('cell', { name: '850' });

    expect(cell).toHaveClass('text-right', 'tnum');
    expect(screen.getByRole('columnheader', { name: 'Cantidad' })).toHaveClass('text-right');
  });

  it('says in which direction a column is sorted', async () => {
    const user = userEvent.setup();
    render(<Table />);
    const header = screen.getByRole('columnheader', { name: 'Referencia' });

    expect(header).toHaveAttribute('aria-sort', 'none');
    expect(screen.getByRole('columnheader', { name: 'Cantidad' })).not.toHaveAttribute('aria-sort');

    await user.click(within(header).getByRole('button'));
    expect(header).toHaveAttribute('aria-sort', 'ascending');

    await user.click(within(header).getByRole('button'));
    expect(header).toHaveAttribute('aria-sort', 'descending');

    await user.click(within(header).getByRole('button'));
    expect(header).toHaveAttribute('aria-sort', 'none');
  });

  it('selects a row, marks it, and selects every row from the header', async () => {
    const user = userEvent.setup();
    render(<Table />);

    await user.click(screen.getByRole('checkbox', { name: 'Seleccionar MM-1002' }));

    expect(screen.getByRole('checkbox', { name: 'Seleccionar MM-1002' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Seleccionar todas las filas' })).toHaveProperty(
      'indeterminate',
      true,
    );
    expect(screen.getByRole('row', { name: /MM-1002/ })).toHaveAttribute('data-selected', 'true');

    await user.click(screen.getByRole('checkbox', { name: 'Seleccionar todas las filas' }));
    expect(screen.getAllByRole('checkbox', { checked: true })).toHaveLength(4);

    await user.click(screen.getByRole('checkbox', { name: 'Seleccionar todas las filas' }));
    expect(screen.queryAllByRole('checkbox', { checked: true })).toHaveLength(0);
  });

  it('says so when there is nothing to show', () => {
    render(
      <DataTable
        caption="Existencias"
        columns={columns}
        rows={[]}
        rowKey={(row) => row.sku}
        emptyText="Sin filas"
      />,
    );

    expect(screen.getByRole('cell', { name: 'Sin filas' })).toHaveAttribute('colspan', '2');
  });

  it('keeps the header in view while the rows scroll', () => {
    render(<Table />);

    expect(screen.getByRole('columnheader', { name: 'Cantidad' })).toHaveClass('sticky', 'top-0');
  });

  it('cycles the sort of a column: ascending, descending, none', () => {
    const asc = nextSort(null, 'sku');
    const desc = nextSort(asc, 'sku');

    expect(asc).toEqual({ key: 'sku', direction: 'asc' });
    expect(desc).toEqual({ key: 'sku', direction: 'desc' });
    expect(nextSort(desc, 'sku')).toBeNull();
    expect(nextSort(desc, 'quantity')).toEqual({ key: 'quantity', direction: 'asc' });
  });
});

describe('DensityToggle', () => {
  afterEach(() => {
    localStorage.clear();
    delete document.documentElement.dataset.density;
  });

  it('starts compact, switches to comfortable and keeps the choice', async () => {
    const user = userEvent.setup();
    render(<DensityToggle />);

    expect(screen.getByRole('button', { name: 'Compacta' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );

    await user.click(screen.getByRole('button', { name: 'Cómoda' }));

    expect(document.documentElement.dataset.density).toBe('comfortable');
    expect(localStorage.getItem(DENSITY_KEY)).toBe('comfortable');
    expect(screen.getByRole('button', { name: 'Cómoda' })).toHaveAttribute('aria-pressed', 'true');
  });
});
