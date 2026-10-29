import type { RouteObject } from 'react-router';
import { RequireAuth } from './auth/RequireAuth';
import { RequireRole } from './auth/RequireRole';
import { AppLayout } from './layouts/AppLayout';
import { AsnPage } from './pages/AsnPage';
import { AsnsPage } from './pages/AsnsPage';
import { AuditPage } from './pages/AuditPage';
import { HomePage } from './pages/HomePage';
import { LoginPage } from './pages/LoginPage';
import { NotFoundPage } from './pages/NotFoundPage';
import { OrderPage } from './pages/OrderPage';
import { OrdersPage } from './pages/OrdersPage';
import { ProductPage } from './pages/ProductPage';
import { ProductsPage } from './pages/ProductsPage';
import { ReceiptPage } from './pages/ReceiptPage';
import { StockPage } from './pages/StockPage';
import { WarehousePage } from './pages/WarehousePage';
import { WarehousesPage } from './pages/WarehousesPage';

/**
 * The component gallery exists only while developing (UI-03): it is not in the build and not in the
 * navigation, and you reach it by its address, `/design`.
 */
const designRoutes: RouteObject[] = import.meta.env.DEV
  ? [
      {
        path: 'design',
        lazy: async () => ({
          Component: (await import('./pages/DesignGalleryPage')).DesignGalleryPage,
        }),
      },
    ]
  : [];

/**
 * Every route of the app. Signing in is the only public one. The rest sit inside `RequireAuth`, and the
 * ones that need more than any user sit inside `RequireRole` as well.
 */
export const routes: RouteObject[] = [
  { path: '/login', element: <LoginPage /> },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <HomePage /> },
          { path: 'warehouses', element: <WarehousesPage /> },
          { path: 'warehouses/:id', element: <WarehousePage /> },
          { path: 'products', element: <ProductsPage /> },
          { path: 'products/:id', element: <ProductPage /> },
          { path: 'inbound', element: <AsnsPage /> },
          { path: 'inbound/:id', element: <AsnPage /> },
          { path: 'receipts/:id', element: <ReceiptPage /> },
          { path: 'orders', element: <OrdersPage /> },
          { path: 'orders/:id', element: <OrderPage /> },
          { path: 'stock', element: <StockPage /> },
          {
            element: <RequireRole minimum="supervisor" />,
            children: [{ path: 'audit', element: <AuditPage /> }],
          },
          ...designRoutes,
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
];
