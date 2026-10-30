import { expect, signIn, test } from './support/session';

test('the app declares itself installable', async ({ page, request, api }) => {
  await api('operator');
  await page.goto('/login');
  await expect(page.locator('link[rel="manifest"]')).toHaveCount(1);

  const response = await request.get('/manifest.webmanifest');
  expect(response.ok()).toBe(true);
  const manifest = (await response.json()) as {
    name: string;
    start_url: string;
    display: string;
    icons: { src: string; sizes: string; purpose?: string }[];
  };

  expect(manifest.name).toBe('Estiba WMS');
  expect(manifest.start_url).toBe('/picking');
  expect(manifest.display).toBe('standalone');
  expect(manifest.icons.map((icon) => icon.sizes)).toEqual(
    expect.arrayContaining(['192x192', '512x512']),
  );
  expect(manifest.icons.some((icon) => icon.purpose === 'maskable')).toBe(true);
  for (const icon of manifest.icons) {
    expect((await request.get(`/${icon.src}`)).ok()).toBe(true);
  }
});

test('a service worker takes charge of the app', async ({ page, api }) => {
  await api('operator');

  await page.goto('/login');

  await expect
    .poll(() =>
      page.evaluate(async () => {
        const registration = await navigator.serviceWorker.ready;

        return registration.active?.state;
      }),
    )
    .toBe('activated');
});

test('the app opens again without a connection', async ({ page, context, api, browserName }) => {
  test.skip(
    browserName !== 'chromium',
    'Cutting the network under a service worker is only checked in Chromium.',
  );
  await api('operator');
  await page.goto('/login');
  await page.evaluate(async () => navigator.serviceWorker.ready);
  // Once the worker is active the page is served by it from the next load on.
  await page.reload();
  await expect
    .poll(() => page.evaluate(() => navigator.serviceWorker.controller !== null))
    .toBe(true);

  await context.setOffline(true);
  await page.goto('/login');

  await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible();
});

test.describe('on a phone', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: false });

  test('an operator sees the picking screen with big targets and no side navigation', async ({
    page,
    api,
  }) => {
    await api('operator');
    await signIn(page);

    await page.goto('/picking');

    await expect(page.getByRole('heading', { name: 'Picking', level: 1 })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Mis listas' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Navegación principal' })).toHaveCount(0);

    const back = await page.getByRole('link', { name: 'Ir al panel' }).boundingBox();
    expect(back?.height).toBeGreaterThanOrEqual(48);
    await expect(page.locator('html')).toHaveAttribute('data-density', 'touch');
    const overflows = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth,
    );
    expect(overflows).toBe(false);
  });
});
