import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';
import { App } from './App';
import { AuthClient } from './auth/AuthClient';
import { detectLanguage } from './i18n';
import './index.css';
import { routes } from './routes';
import { applyDensity, readStoredDensity } from './theme/density';
import { applyTheme, readStoredTheme, resolveTheme } from './theme/theme';

const root = document.getElementById('root');

if (root === null) {
  throw new Error('index.html has no #root element.');
}

document.documentElement.lang = detectLanguage(navigator.languages);
applyTheme(
  resolveTheme(readStoredTheme(), window.matchMedia('(prefers-color-scheme: light)').matches),
);

applyDensity(readStoredDensity() ?? 'compact');

createRoot(root).render(
  <StrictMode>
    <App client={new AuthClient()} router={createBrowserRouter(routes)} />
  </StrictMode>,
);
