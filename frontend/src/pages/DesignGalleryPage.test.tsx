import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { DesignGalleryPage } from './DesignGalleryPage';

describe('DesignGalleryPage', () => {
  it('shows the cards, the badges and the first page of the table', () => {
    render(<DesignGalleryPage />);

    expect(screen.getByRole('heading', { name: 'Galería de componentes' })).toBeInTheDocument();
    expect(screen.getAllByRole('article')).toHaveLength(4);
    expect(screen.getByText('Mostrando 1–10 de 60')).toBeInTheDocument();
    expect(screen.getAllByRole('row')).toHaveLength(11);
  });

  it('sorts the table and goes back to the first page', async () => {
    const user = userEvent.setup();
    render(<DesignGalleryPage />);

    await user.click(screen.getByRole('button', { name: 'Página 2' }));
    await user.click(screen.getByRole('button', { name: 'Ordenar por Cantidad' }));
    await user.click(screen.getByRole('button', { name: 'Ordenar por Cantidad' }));

    expect(screen.getByText('Mostrando 1–10 de 60')).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /Cantidad/ })).toHaveAttribute(
      'aria-sort',
      'descending',
    );
  });

  it('selects rows', async () => {
    const user = userEvent.setup();
    render(<DesignGalleryPage />);

    const first = screen.getByRole('row', { name: /SKU-1000/ });
    await user.click(within(first).getByRole('checkbox'));

    expect(first).toHaveAttribute('aria-selected', 'true');
  });

  it('switches between the states of the table', async () => {
    const user = userEvent.setup();
    render(<DesignGalleryPage />);

    await user.click(screen.getByRole('button', { name: 'Con error' }));
    expect(screen.getByRole('alert')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(screen.getByText('Mostrando 1–10 de 60')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Vacía' }));
    expect(screen.getByText('No hay nada que mostrar.')).toBeInTheDocument();
    expect(screen.getByText('Sin resultados')).toBeInTheDocument();
  });
});
