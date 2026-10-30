import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';
import { App } from './App';
import { AuthClient } from './auth/AuthClient';
import './i18n';
import './index.css';
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

createRoot(root).render(
  <StrictMode>
    <App client={new AuthClient()} router={createBrowserRouter(routes)} />
    <UpdatePrompt />
  </StrictMode>,
);
