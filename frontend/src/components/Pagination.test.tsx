import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Pagination, pageWindow } from './Pagination';

describe('pageWindow', () => {
  it('lists every page when there are few', () => {
    expect(pageWindow(1, 1)).toEqual([1]);
    expect(pageWindow(3, 7)).toEqual([1, 2, 3, 4, 5, 6, 7]);
  });

  it('leaves a gap at the end near the start', () => {
    expect(pageWindow(2, 20)).toEqual([1, 2, 3, 4, 5, 'gap', 20]);
  });

  it('leaves a gap at the start near the end', () => {
    expect(pageWindow(19, 20)).toEqual([1, 'gap', 16, 17, 18, 19, 20]);
  });

  it('leaves a gap on both sides in the middle', () => {
    expect(pageWindow(10, 20)).toEqual([1, 'gap', 9, 10, 11, 'gap', 20]);
  });
});

describe('Pagination', () => {
  it('says which rows are shown', () => {
    render(<Pagination page={2} pageSize={25} total={1240} onPageChange={vi.fn()} />);

    expect(screen.getByText('Mostrando 26–50 de 1240')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Página 2' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });

  it('says so when there are no results', () => {
    render(<Pagination page={1} pageSize={25} total={0} onPageChange={vi.fn()} />);

    expect(screen.getByText('Sin resultados')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Página anterior' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Página siguiente' })).toBeDisabled();
  });

  it('asks for the previous, the next and a numbered page', async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    render(<Pagination page={3} pageSize={10} total={100} onPageChange={onPageChange} />);

    await user.click(screen.getByRole('button', { name: 'Página anterior' }));
    await user.click(screen.getByRole('button', { name: 'Página siguiente' }));
    await user.click(screen.getByRole('button', { name: 'Página 10' }));

    expect(onPageChange.mock.calls).toEqual([[2], [4], [10]]);
  });

  it('disables next on the last page', () => {
    render(<Pagination page={4} pageSize={10} total={35} onPageChange={vi.fn()} />);

    expect(screen.getByRole('button', { name: 'Página siguiente' })).toBeDisabled();
  });

  it('offers the page size only when someone listens to it', async () => {
    const user = userEvent.setup();
    const onPageSizeChange = vi.fn();
    const { rerender } = render(
      <Pagination page={1} pageSize={10} total={100} onPageChange={vi.fn()} />,
    );

    expect(screen.queryByLabelText('Filas por página')).not.toBeInTheDocument();

    rerender(
      <Pagination
        page={1}
        pageSize={10}
        total={100}
        onPageChange={vi.fn()}
        onPageSizeChange={onPageSizeChange}
      />,
    );
    await user.selectOptions(screen.getByLabelText('Filas por página'), '50');

    expect(onPageSizeChange).toHaveBeenCalledWith(50);
  });
});
