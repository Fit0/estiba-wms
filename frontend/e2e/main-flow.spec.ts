import { expect, signIn, test } from './support/session';

const gtin = '8412345678905';

test('from a delivery to a released wave', async ({ page, api }) => {
  await api('supervisor');
  await signIn(page);

  // Catalogue: a product with its barcode.
  await page.goto('/products');
  await page.getByRole('button', { name: 'Nuevo producto' }).click();
  const productDialog = page.getByRole('dialog', { name: 'Nuevo producto' });
  await productDialog.getByLabel('SKU').fill('E2E-001');
  await productDialog.getByLabel('Nombre').fill('Camiseta de prueba');
  await productDialog.getByLabel('Largo (mm)').fill('300');
  await productDialog.getByLabel('Ancho (mm)').fill('200');
  await productDialog.getByLabel('Alto (mm)').fill('20');
  await productDialog.getByLabel('Peso (g)').fill('180');
  await productDialog.getByRole('button', { name: 'Guardar' }).click();
  await expect(page).toHaveURL(/\/products\/.+/);
  await expect(page.getByRole('heading', { name: /E2E-001/ }).first()).toBeVisible();

  await page.getByRole('button', { name: 'Añadir GTIN' }).click();
  const gtinDialog = page.getByRole('dialog', { name: /Añadir un GTIN/ });
  await gtinDialog
    .getByLabel(/GTIN|Código/)
    .first()
    .fill(gtin);
  await gtinDialog.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText(gtin).first()).toBeVisible();
  const productUrl = page.url();

  // Inbound: announce 10 units and receive them at the dock.
  await page.goto('/inbound');
  await page.getByRole('button', { name: 'Nueva entrega' }).click();
  const asnDialog = page.getByRole('dialog', { name: 'Nueva entrega esperada' });
  await asnDialog.getByLabel('Referencia').fill('ASN-E2E-1');
  await asnDialog.getByLabel('Proveedor').fill('Textiles del Ebro');
  await asnDialog.getByRole('group', { name: 'Línea 1' }).getByRole('searchbox').fill('E2E');
  await page.getByRole('button', { name: /E2E-001/ }).click();
  await asnDialog.getByRole('group', { name: 'Línea 1' }).getByLabel('Unidades').fill('10');
  await asnDialog.getByRole('button', { name: 'Guardar' }).click();
  await expect(page).toHaveURL(/\/inbound\/.+/);

  await page.getByRole('button', { name: 'Abrir recepción' }).click();
  await page
    .getByRole('dialog', { name: /Abrir recepción de/ })
    .getByRole('button', { name: 'Abrir recepción' })
    .click();
  await expect(page).toHaveURL(/\/receipts\/.+/);

  await page.getByLabel('Código de barras (GTIN)').fill(gtin);
  await page.getByLabel('Unidades').fill('10');
  await page.getByRole('button', { name: 'Contar' }).click();
  await expect(page.getByText(/Contadas 10 unidades/)).toBeVisible();

  await page.getByRole('button', { name: 'Cerrar la recepción' }).click();
  await page
    .getByRole('dialog', { name: /Cerrar la recepción de/ })
    .getByRole('button', { name: 'Cerrar recepción' })
    .click();
  await expect(page.getByText('Stock dado de alta')).toBeVisible();

  // The stock is now on the product.
  await page.goto(productUrl);
  await expect(page.getByRole('cell', { name: '10', exact: true }).first()).toBeVisible();

  // Outbound: an order for 4 units, reserved and sent to the warehouse in a wave.
  await page.goto('/orders');
  await page.getByRole('button', { name: 'Nuevo pedido' }).click();
  const orderDialog = page.getByRole('dialog', { name: 'Nuevo pedido' });
  await orderDialog.getByLabel('Referencia').fill('PED-E2E-1');
  await orderDialog.getByLabel('Cliente').fill('Moda Norte');
  await orderDialog.getByLabel('Calle').fill('Calle Mayor 1');
  await orderDialog.getByLabel('Código postal').fill('50001');
  await orderDialog.getByLabel('Ciudad').fill('Zaragoza');
  await orderDialog.getByLabel('País (2 letras)').fill('ES');
  await orderDialog.getByRole('group', { name: 'Línea 1' }).getByRole('searchbox').fill('E2E');
  await page.getByRole('button', { name: /E2E-001/ }).click();
  await orderDialog.getByRole('group', { name: 'Línea 1' }).getByLabel('Unidades').fill('4');
  await orderDialog.getByRole('button', { name: 'Guardar' }).click();
  await expect(page).toHaveURL(/\/orders\/.+/);

  await page.getByRole('button', { name: 'Reservar stock' }).click();
  await expect(page.getByText('Reservado', { exact: true }).first()).toBeVisible();

  await page.goto('/waves');
  await page.getByRole('button', { name: 'Nueva ola' }).click();
  const waveDialog = page.getByRole('dialog', { name: 'Nueva ola' });
  await waveDialog.getByRole('checkbox', { name: /PED-E2E-1/ }).check();
  await waveDialog.getByRole('button', { name: 'Guardar' }).click();
  await expect(page).toHaveURL(/\/waves\/.+/);

  await page.getByRole('button', { name: 'Lanzar al almacén' }).click();
  await page
    .getByRole('dialog', { name: 'Lanzar la ola al almacén' })
    .getByRole('button', { name: 'Lanzar' })
    .click();
  await expect(page.getByText('En el almacén').first()).toBeVisible();
  await expect(page.getByRole('table', { name: 'Listas de picking' })).toBeVisible();
  await expect(page.getByText('0 de 4')).toBeVisible();

  // On the floor: the list of the wave waits for an operator in the picking screen of the PWA.
  await page.goto('/picking');
  const free = page.getByRole('region', { name: 'Listas sin asignar' });
  await expect(free.getByText('0 de 4 unidades')).toBeVisible();
});
