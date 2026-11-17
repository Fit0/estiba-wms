import { lazy, type ComponentType, type LazyExoticComponent } from 'react';
import type { RouteObject } from 'react-router';
import { RequireAuth } from './auth/RequireAuth';
import { RequireRole } from './auth/RequireRole';
import { AppLayout } from './layouts/AppLayout';
import { PickingLayout } from './layouts/PickingLayout';
import { LoginPage } from './pages/LoginPage';
import { NotFoundPage } from './pages/NotFoundPage';

/**
 * A screen that the browser downloads when someone goes to it, not with the app. Each page is its own
 * file, so signing in and the first screen do not carry the ones the person never opens (E10-07). The
 * frame around the routes (`AppLayout`, `PickingLayout`) shows a loading status while the file arrives.
 * Only the screens of signing in and "not found" are written into the main file: a page that is not
 * lazy fails the test of `routes.test.tsx`.
 */
function page<Name extends string>(
  load: () => Promise<Record<Name, ComponentType>>,
  name: Name,
): LazyExoticComponent<ComponentType> {
  return lazy(async () => ({ default: (await load())[name] }));
}

const AsnPage = page(() => import('./pages/AsnPage'), 'AsnPage');
const AsnsPage = page(() => import('./pages/AsnsPage'), 'AsnsPage');
const AuditPage = page(() => import('./pages/AuditPage'), 'AuditPage');
const UsersPage = page(() => import('./pages/UsersPage'), 'UsersPage');
const ErpPage = page(() => import('./pages/ErpPage'), 'ErpPage');
const HomePage = page(() => import('./pages/HomePage'), 'HomePage');
const OrderPage = page(() => import('./pages/OrderPage'), 'OrderPage');
const OrdersPage = page(() => import('./pages/OrdersPage'), 'OrdersPage');
const PickListPage = page(() => import('./pages/PickListPage'), 'PickListPage');
const PickingPage = page(() => import('./pages/PickingPage'), 'PickingPage');
const ProductPage = page(() => import('./pages/ProductPage'), 'ProductPage');
const PutawayPage = page(() => import('./pages/PutawayPage'), 'PutawayPage');
const ProductsPage = page(() => import('./pages/ProductsPage'), 'ProductsPage');
const ReceiptPage = page(() => import('./pages/ReceiptPage'), 'ReceiptPage');
const StockPage = page(() => import('./pages/StockPage'), 'StockPage');
const WavePage = page(() => import('./pages/WavePage'), 'WavePage');
const WavesPage = page(() => import('./pages/WavesPage'), 'WavesPage');
const WarehousePage = page(() => import('./pages/WarehousePage'), 'WarehousePage');
const WarehousesPage = page(() => import('./pages/WarehousesPage'), 'WarehousesPage');

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
      // The picking screens of the PWA have a frame of their own, made for a phone (E07-01).
      {
        element: <PickingLayout />,
        children: [
          { path: 'picking', element: <PickingPage /> },
          { path: 'picking/:id', element: <PickListPage /> },
        ],
      },
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
          { path: 'putaway', element: <PutawayPage /> },
          { path: 'orders', element: <OrdersPage /> },
          { path: 'orders/:id', element: <OrderPage /> },
          { path: 'waves', element: <WavesPage /> },
          { path: 'waves/:id', element: <WavePage /> },
          { path: 'stock', element: <StockPage /> },
          {
            element: <RequireRole minimum="supervisor" />,
            children: [
              { path: 'audit', element: <AuditPage /> },
              { path: 'erp', element: <ErpPage /> },
            ],
          },
          {
            element: <RequireRole minimum="admin" />,
            children: [{ path: 'users', element: <UsersPage /> }],
          },
          ...designRoutes,
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
];
