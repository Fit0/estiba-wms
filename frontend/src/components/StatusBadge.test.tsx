import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { StatusBadge } from './StatusBadge';

describe('StatusBadge', () => {
  it('shows the text of the status with its tone', () => {
    render(<StatusBadge tone="warn">Cuarentena</StatusBadge>);

    expect(screen.getByText('Cuarentena')).toHaveAttribute('data-tone', 'warn');
  });

  it('is neutral unless told otherwise', () => {
    render(<StatusBadge>Borrador</StatusBadge>);

    expect(screen.getByText('Borrador')).toHaveAttribute('data-tone', 'neutral');
  });

  it('hides the dot from assistive technology', () => {
    const { container } = render(
      <StatusBadge tone="ok" dot>
        Disponible
      </StatusBadge>,
    );

    expect(container.querySelector('[aria-hidden="true"]')).not.toBeNull();
    expect(screen.getByText('Disponible')).toBeInTheDocument();
  });
});
