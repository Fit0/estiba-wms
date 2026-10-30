import { expect, test as base, type Page } from '@playwright/test';
import { Backend } from './backend';

type Role = 'operator' | 'supervisor';

/**
 * A test that starts with the fake API installed. `as(role)` gives the page of a user with that role
 * who is not signed in yet.
 */
export const test = base.extend<{ api: (role: Role) => Promise<Backend> }>({
  api: async ({ page }, use) => {
    await use(async (role) => {
      const backend = new Backend({ roles: [role] });
      await backend.install(page);

      return backend;
    });
  },
});

export { expect };

/** Signs in through the form, as a person does. */
export async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Correo electrónico').fill('ana@marea-moda.example');
  await page.getByLabel('Contraseña', { exact: true }).fill('secret');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page.getByRole('heading', { name: /Bienvenido|Inicio/ }).first()).toBeVisible();
}
