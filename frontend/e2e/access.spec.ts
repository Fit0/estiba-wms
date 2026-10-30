import { expect, signIn, test } from './support/session';

test('a visit without a session goes to the sign-in page', async ({ page, api }) => {
  await api('supervisor');

  await page.goto('/products');

  await expect(page).toHaveURL(/\/login/);
  await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible();
});

test('a wrong password is explained and the person stays on the form', async ({ page, api }) => {
  await api('supervisor');

  await page.goto('/login');
  await page.getByLabel('Correo electrónico').fill('ana@marea-moda.example');
  await page.getByLabel('Contraseña', { exact: true }).fill('wrong');
  await page.getByRole('button', { name: 'Entrar' }).click();

  await expect(page.getByRole('alert')).toBeVisible();
  await expect(page).toHaveURL(/\/login/);
});

test('an operator can read the catalogue but sees no way to change it', async ({ page, api }) => {
  await api('operator');
  await signIn(page);

  await page.goto('/products');

  await expect(page.getByRole('heading', { name: 'Productos', level: 1 })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Nuevo producto' })).toHaveCount(0);
});
