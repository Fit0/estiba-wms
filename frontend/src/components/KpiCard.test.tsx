import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { KpiCard } from './KpiCard';

describe('KpiCard', () => {
  it('shows the figure, what it measures and the trend as text', () => {
    render(
      <KpiCard
        label="Unidades en stock"
        value="12.480"
        trend={{ direction: 'up', label: '+4,2 % esta semana', tone: 'ok' }}
      />,
    );

    expect(screen.getByRole('heading', { name: 'Unidades en stock' })).toBeInTheDocument();
    expect(screen.getByText('12.480')).toBeInTheDocument();
    expect(screen.getByText('+4,2 % esta semana')).toHaveAttribute('data-tone', 'ok');
  });

  it('works without a trend', () => {
    render(<KpiCard label="Ubicaciones" value="320" />);

    expect(screen.getByText('320')).toBeInTheDocument();
  });

  it('hides the figure and the trend while loading, and says it is busy', () => {
    render(
      <KpiCard label="Pedidos" value="87" loading trend={{ direction: 'flat', label: 'igual' }} />,
    );

    expect(screen.queryByText('87')).not.toBeInTheDocument();
    expect(screen.queryByText('igual')).not.toBeInTheDocument();
    expect(screen.getByRole('article')).toHaveAttribute('aria-busy', 'true');
  });
});
