import { defineConfig, devices } from '@playwright/test';

const ci = process.env.CI !== undefined;
// Lets a machine that cannot download the browsers run the tests with one that is already installed.
const chromiumPath = process.env.E2E_CHROMIUM_PATH;

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: ci,
  retries: ci ? 1 : 0,
  reporter: ci ? [['github'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL: 'http://localhost:4173',
    locale: 'es-ES',
    timezoneId: 'Europe/Madrid',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        ...(chromiumPath === undefined ? {} : { launchOptions: { executablePath: chromiumPath } }),
      },
    },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
  ],
  // The tests run the production build, the same files that are deployed.
  webServer: {
    command: 'npm run preview -- --port 4173 --strictPort',
    url: 'http://localhost:4173',
    reuseExistingServer: !ci,
    timeout: 60_000,
  },
});
