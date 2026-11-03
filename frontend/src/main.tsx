import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';
import { App } from './App';
import { AuthClient, storedSessionHint } from './auth/AuthClient';
import './i18n';
import './index.css';
import { ConfirmationQueue } from './offline/ConfirmationQueue';
import { canUseDatabase, openOfflineDatabase } from './offline/database';
import { UpdatePrompt } from './pwa/UpdatePrompt';
import { routes } from './routes';
import { applyDensity, readStoredDensity, resolveDensity } from './theme/density';
import { applyTheme, readStoredTheme, resolveTheme } from './theme/theme';

const root = document.getElementById('root');

if (root === null) {
  throw new Error('index.html has no #root element.');
}

applyTheme(
  resolveTheme(readStoredTheme(), window.matchMedia('(prefers-color-scheme: light)').matches),
);

applyDensity(resolveDensity(readStoredDensity(), window.matchMedia('(pointer: coarse)').matches));

const offline = canUseDatabase() ? openOfflineDatabase() : undefined;

createRoot(root).render(
  <StrictMode>
    <App
      client={new AuthClient(undefined, storedSessionHint())}
      router={createBrowserRouter(routes)}
      {...(offline === undefined
        ? {}
        : { queue: new ConfirmationQueue(offline.queue), cache: offline.cache })}
    />
    <UpdatePrompt />
  </StrictMode>,
);
