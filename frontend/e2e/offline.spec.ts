import type { Page } from '@playwright/test';
import type { Backend } from './support/backend';
import { expect, signIn, test } from './support/session';

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

const gtin = '8412345678905';

async function startList(page: Page, backend: Backend): Promise<void> {
  backend.seedPickList({
    units: 0,
    location: '',
    stops: [
      { units: 4, location: 'A-01-01' },
      { units: 2, location: 'A-02-03' },
    ],
  });
  await signIn(page);
  await page.goto('/picking');
  await page.getByRole('link', { name: /Abrir la lista/ }).click();
  await page.getByRole('button', { name: 'Empezar la lista' }).click();
  await expect(page.getByText('A-01-01', { exact: true })).toBeVisible();
}

async function goOffline(page: Page, backend: Backend): Promise<void> {
  backend.setDown(true);
  await page.context().setOffline(true);
}

async function goOnline(page: Page, backend: Backend): Promise<void> {
  backend.setDown(false);
  await page.context().setOffline(false);
}

async function confirmStop(page: Page, location: string): Promise<void> {
  await page.getByLabel('Ubicación', { exact: true }).fill(location);
  await page.getByLabel('Código de barras del producto', { exact: true }).fill(gtin);
  await page.getByRole('button', { name: 'Confirmar parada' }).click();
}

test('stops confirmed without a connection are sent, in order, when it comes back', async ({
  page,
  api,
}) => {
  const backend = await api('operator');
  await startList(page, backend);

  await goOffline(page, backend);
  await confirmStop(page, 'A-01-01');
  await expect(page.getByText('A-02-03', { exact: true })).toBeVisible();
  await confirmStop(page, 'A-02-03');
  await expect(page.getByText('Lista completada. Buen trabajo.')).toBeVisible();
  await expect(page.getByText(/Confirmaciones guardadas sin conexión: 2/)).toBeVisible();
  expect(backend.pickListsNow()[0]?.unitsPicked).toBe(0);

  await goOnline(page, backend);

  await expect(page.getByText(/Confirmaciones guardadas sin conexión/)).toHaveCount(0, {
    timeout: 20_000,
  });
  const list = backend.pickListsNow()[0];
  expect(list?.status).toBe('completed');
  expect(list?.unitsPicked).toBe(6);
});

test('the app reopens without a connection with the list and what was queued', async ({
  page,
  api,
  browserName,
}) => {
  test.skip(
    browserName !== 'chromium',
    'Cutting the network under a service worker is only checked in Chromium.',
  );
  const backend = await api('operator');
  await startList(page, backend);
  // The service worker takes charge of the page, and the device saves the list a moment after it loads.
  await page.evaluate(async () => navigator.serviceWorker.ready);
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          new Promise<boolean>((resolve) => {
            const request = indexedDB.open('estiba-offline');
            request.onsuccess = () => {
              const store = request.result
                .transaction('cache')
                .objectStore('cache')
                .get('estiba-query-cache');
              store.onsuccess = () => {
                resolve(typeof store.result === 'string' && store.result.includes('A-02-03'));
              };
            };
            request.onerror = () => {
              resolve(false);
            };
          }),
      ),
    )
    .toBe(true);

  await goOffline(page, backend);
  await confirmStop(page, 'A-01-01');
  await expect(page.getByText(/Confirmaciones guardadas sin conexión: 1/)).toBeVisible();

  // Closing and opening the app in the dead zone: the shell, the session, the list and the queue are there.
  await page.reload();
  await expect(page.getByText('A-02-03', { exact: true })).toBeVisible();
  await expect(page.getByText(/Confirmaciones guardadas sin conexión: 1/)).toBeVisible();

  await confirmStop(page, 'A-02-03');
  await expect(page.getByText('Lista completada. Buen trabajo.')).toBeVisible();
  await goOnline(page, backend);

  await expect(page.getByText(/Confirmaciones guardadas sin conexión/)).toHaveCount(0, {
    timeout: 20_000,
  });
  expect(backend.pickListsNow()[0]?.unitsPicked).toBe(6);
});
