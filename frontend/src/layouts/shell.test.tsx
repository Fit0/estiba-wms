import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { act } from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import { THEME_KEY } from '../theme/theme';
import { apiOf, noSession, open, withSession } from '../testing/renderApp';
import { visibleNavigation } from './navigation';

const SIDEBAR_KEY = 'estiba-sidebar';

describe('the app shell', () => {
  afterEach(() => {
    localStorage.clear();
    delete document.documentElement.dataset.theme;
  });

  it('shows the side navigation with the pages of the role', async () => {
    open('/', apiOf(['supervisor'], withSession(['supervisor'])));

    const navigation = await screen.findByRole('navigation', { name: 'Navegación principal' });

    expect(within(navigation).getByRole('link', { name: 'Inicio' })).toHaveAttribute('href', '/');
    expect(within(navigation).getByRole('link', { name: 'Auditoría' })).toBeInTheDocument();
  });

  it('marks the page that is open', async () => {
    open('/audit', apiOf(['supervisor'], withSession(['supervisor'])));

    const audit = await screen.findByRole('link', { name: 'Auditoría' });

    expect(audit).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Inicio' })).not.toHaveAttribute('aria-current');
  });

  it('starts as a rail of icons on a narrow screen, and the links keep their names', async () => {
    open('/', apiOf(['operator'], withSession(['operator'])));

    const toggle = await screen.findByRole('button', { name: 'Expandir el menú' });

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(toggle).toHaveAttribute('aria-controls', 'sidebar');
    expect(screen.getByRole('link', { name: 'Inicio' })).toHaveAttribute('title', 'Inicio');
  });

  it('opens and closes the sidebar, and remembers it for the next visit', async () => {
    const user = userEvent.setup();
    open('/', apiOf(['operator'], withSession(['operator'])));

    await user.click(await screen.findByRole('button', { name: 'Expandir el menú' }));

    expect(screen.getByRole('button', { name: 'Contraer el menú' })).toHaveAttribute(
      'aria-expanded',
      'true',
    );
    expect(localStorage.getItem(SIDEBAR_KEY)).toBe('expanded');

    await user.click(screen.getByRole('button', { name: 'Contraer el menú' }));

    expect(localStorage.getItem(SIDEBAR_KEY)).toBe('collapsed');
  });

  it('follows the choice of the last visit, not the width of the screen', async () => {
    localStorage.setItem(SIDEBAR_KEY, 'expanded');
    open('/', apiOf(['operator'], withSession(['operator'])));

    expect(await screen.findByRole('button', { name: 'Contraer el menú' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Inicio' })).not.toHaveAttribute('title');
  });

  it('switches the theme from the top bar and keeps the choice', async () => {
    const user = userEvent.setup();
    document.documentElement.dataset.theme = 'dark';
    open('/', apiOf(['operator'], withSession(['operator'])));

    await user.click(await screen.findByRole('button', { name: 'Cambiar al tema claro' }));

    expect(document.documentElement.dataset.theme).toBe('light');
    expect(localStorage.getItem(THEME_KEY)).toBe('light');
    expect(screen.getByRole('button', { name: 'Cambiar al tema oscuro' })).toBeInTheDocument();
  });

  it('says whether there is a connection, with words', async () => {
    open('/', apiOf(['operator'], withSession(['operator'])));
    await screen.findByRole('status');

    expect(screen.getByRole('status')).toHaveTextContent('En línea');

    act(() => {
      setOnline(false);
    });

    expect(await screen.findByText('Sin conexión')).toBeInTheDocument();
    act(() => {
      setOnline(true);
    });
  });

  it('has the theme switch on the sign-in page too', async () => {
    const user = userEvent.setup();
    document.documentElement.dataset.theme = 'light';
    open('/login', apiOf(['operator'], noSession));

    await user.click(await screen.findByRole('button', { name: 'Cambiar al tema oscuro' }));

    expect(document.documentElement.dataset.theme).toBe('dark');
  });
});

describe('the side navigation', () => {
  it('shows an operator no administration page, and no empty group', () => {
    const labels = visibleNavigation(['operator']).flatMap((group) =>
      group.items.map((item) => item.label),
    );

    expect(labels).toEqual(['nav.home']);
    expect(visibleNavigation(['operator'])).toHaveLength(1);
  });

  it('shows a supervisor the audit log', () => {
    const labels = visibleNavigation(['supervisor']).flatMap((group) =>
      group.items.map((item) => item.label),
    );

    expect(labels).toEqual(['nav.home', 'nav.audit']);
  });
});

/**
 * Pretends that the browser lost the connection, or found it again.
 */
function setOnline(online: boolean): void {
  Object.defineProperty(navigator, 'onLine', { value: online, configurable: true });
  window.dispatchEvent(new Event(online ? 'online' : 'offline'));
}
