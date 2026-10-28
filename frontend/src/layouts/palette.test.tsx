import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';
import { apiOf, open, withSession } from '../testing/renderApp';

describe('the command palette', () => {
  afterEach(() => {
    localStorage.clear();
    delete document.documentElement.dataset.theme;
  });

  async function signedIn(roles: Parameters<typeof withSession>[0]) {
    const user = userEvent.setup();
    const result = open('/', apiOf(roles, withSession(roles)));
    await screen.findByRole('heading', { name: 'Inicio' });

    return { user, ...result };
  }

  it('opens with Ctrl+K, names itself and focuses the search box', async () => {
    const { user } = await signedIn(['operator']);

    await user.keyboard('{Control>}k{/Control}');

    const dialog = await screen.findByRole('dialog', { name: 'Paleta de comandos' });
    expect(within(dialog).getByRole('combobox')).toHaveFocus();
  });

  it('opens from the button of the top bar and closes with Escape', async () => {
    const { user } = await signedIn(['operator']);

    await user.click(screen.getByRole('button', { name: 'Abrir la paleta de comandos' }));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    await user.keyboard('{Escape}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('offers only the pages the role can see', async () => {
    const operator = await signedIn(['operator']);
    await operator.user.keyboard('{Control>}k{/Control}');

    const dialog = await screen.findByRole('dialog');

    expect(within(dialog).getByRole('option', { name: 'Inicio' })).toBeInTheDocument();
    expect(within(dialog).queryByRole('option', { name: 'Auditoría' })).not.toBeInTheDocument();
  });

  it('goes to the page that is chosen', async () => {
    const { user, router } = await signedIn(['supervisor']);

    await user.keyboard('{Control>}k{/Control}');
    await user.type(await screen.findByRole('combobox'), 'audi');
    await user.keyboard('{Enter}');

    expect(router.state.location.pathname).toBe('/audit');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('says so when nothing matches', async () => {
    const { user } = await signedIn(['operator']);

    await user.keyboard('{Control>}k{/Control}');
    await user.type(await screen.findByRole('combobox'), 'zzzz');

    expect(screen.getByText('No hay resultados.')).toBeInTheDocument();
  });

  it('changes the theme, and the choice is remembered', async () => {
    const { user } = await signedIn(['operator']);
    expect(document.documentElement.dataset.theme ?? 'dark').toBe('dark');

    await user.keyboard('{Control>}k{/Control}');
    await user.click(await screen.findByRole('option', { name: 'Cambiar al tema claro' }));

    expect(document.documentElement.dataset.theme).toBe('light');
    expect(localStorage.getItem('estiba-theme')).toBe('light');
  });

  it('signs out', async () => {
    const { user } = await signedIn(['operator']);

    await user.keyboard('{Control>}k{/Control}');
    await user.click(await screen.findByRole('option', { name: 'Cerrar sesión' }));

    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument();
  });
});
