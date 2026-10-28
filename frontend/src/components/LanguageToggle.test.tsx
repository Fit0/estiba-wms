import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { LANGUAGE_KEY } from '../i18n';
import { apiOf, noSession, open, withSession } from '../testing/renderApp';

describe('the language button', () => {
  it('switches the shell to English and remembers it', async () => {
    const user = userEvent.setup();
    open('/', apiOf(['operator'], withSession(['operator'])));

    await user.click(await screen.findByRole('button', { name: 'Cambiar a inglés' }));

    expect(await screen.findByRole('button', { name: 'Switch to Spanish' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Home' })).toBeInTheDocument();
    expect(window.localStorage.getItem(LANGUAGE_KEY)).toBe('en');
    expect(document.documentElement.lang).toBe('en');
  });

  it('is also on the sign-in page', async () => {
    const user = userEvent.setup();
    open('/login', apiOf(['operator'], noSession));

    await user.click(await screen.findByRole('button', { name: 'Cambiar a inglés' }));

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });
});
