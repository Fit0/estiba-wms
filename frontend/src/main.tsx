import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';
import { App } from './App';
import { AuthClient } from './auth/AuthClient';
import { detectLanguage } from './i18n';
import { routes } from './routes';
import './styles.css';

const root = document.getElementById('root');

if (root === null) {
  throw new Error('index.html has no #root element.');
}

document.documentElement.lang = detectLanguage(navigator.languages);

createRoot(root).render(
  <StrictMode>
    <App client={new AuthClient()} router={createBrowserRouter(routes)} />
  </StrictMode>,
);
