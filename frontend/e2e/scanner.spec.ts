import { expect, signIn, test } from './support/session';

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

test('the camera reads the barcode of the product into the field', async ({
  page,
  api,
  browserName,
}) => {
  test.skip(browserName !== 'chromium', 'The camera is faked with a video file only in Chromium.');
  const backend = await api('supervisor');
  backend.seedPickList({ units: 2, location: 'A-01-01' });
  await signIn(page);

  await page.goto('/picking');
  await page.getByRole('link', { name: /Abrir la lista/ }).click();
  await page.getByRole('button', { name: 'Empezar la lista' }).click();
  await page.getByLabel('Ubicación', { exact: true }).fill('A-01-01');

  await page.getByRole('button', { name: /Escanear Código de barras del producto/ }).click();
  await expect(page.getByRole('dialog', { name: 'Escanear con la cámara' })).toBeVisible();

  // The dialog closes by itself once the code is read.
  await expect(page.getByRole('dialog')).toHaveCount(0, { timeout: 15_000 });
  await expect(page.getByLabel('Código de barras del producto', { exact: true })).toHaveValue(
    '8412345678905',
  );

  await page.getByRole('button', { name: 'Confirmar parada' }).click();
  await expect(page.getByText('Lista completada. Buen trabajo.')).toBeVisible();
});
