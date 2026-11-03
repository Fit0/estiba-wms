import { expect, signIn, test } from './support/session';

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

test('a stop is not confirmed with the wrong shelf or without a reason for missing units', async ({
  page,
  api,
}) => {
  const backend = await api('supervisor');
  backend.seedPickList({ units: 5, location: 'B-02-04' });
  await signIn(page);

  await page.goto('/picking');
  await page.getByRole('link', { name: /Abrir la lista/ }).click();
  await page.getByRole('button', { name: 'Empezar la lista' }).click();
  await expect(page.getByText('B-02-04', { exact: true })).toBeVisible();

  await page.getByLabel('Ubicación', { exact: true }).fill('A-01-01');
  await page.getByLabel('Código de barras del producto', { exact: true }).fill('8412345678905');
  await page.getByRole('button', { name: 'Confirmar parada' }).click();
  await expect(page.getByText('No es la ubicación de esta parada.')).toBeVisible();

  await page.getByLabel('Ubicación', { exact: true }).fill('b-02-04');
  await page.getByLabel('Unidades recogidas').fill('3');
  await page.getByRole('button', { name: 'Confirmar parada' }).click();
  await expect(page.getByText('Indica por qué faltan unidades.')).toBeVisible();

  await page.getByLabel('Motivo de las unidades que faltan').fill('Hueco vacío');
  await page.getByRole('button', { name: 'Confirmar parada' }).click();

  await expect(page.getByText('Lista completada. Buen trabajo.')).toBeVisible();
  await expect(page.getByText('Paradas con menos unidades de las pedidas: 1.')).toBeVisible();
});
