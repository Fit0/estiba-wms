import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { apiOf, open, withSession } from '../testing/renderApp';
import { DENSITY_KEY } from '../theme/density';

describe('the density button', () => {
  it('cycles compact, comfortable and touch, and says which one is on', async () => {
    const user = userEvent.setup();
    open('/', apiOf(['operator'], withSession(['operator'])));

    await user.click(
      await screen.findByRole('button', { name: 'Densidad compacta. Cambiar a cómoda' }),
    );
    await user.click(
      await screen.findByRole('button', { name: 'Densidad cómoda. Cambiar a táctil' }),
    );

    expect(document.documentElement.dataset.density).toBe('touch');
    expect(window.localStorage.getItem(DENSITY_KEY)).toBe('touch');

    await user.click(
      await screen.findByRole('button', { name: 'Densidad táctil. Cambiar a compacta' }),
    );
    expect(document.documentElement.dataset.density).toBe('compact');
  });
});
