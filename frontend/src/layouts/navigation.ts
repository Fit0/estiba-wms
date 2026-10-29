import {
  ClipboardList,
  LayoutDashboard,
  Boxes,
  Package,
  Truck,
  Warehouse,
  type LucideIcon,
} from 'lucide-react';
import { hasRole, type Role } from '../auth/roles';
import type { MessageKey } from '../i18n';

export interface NavItem {
  readonly to: string;
  readonly label: MessageKey;
  readonly icon: LucideIcon;
  /** The route is only this one, not the ones below it. */
  readonly end?: boolean;
  /** The lowest role that sees the item. The API is what refuses; this only decides what to show. */
  readonly minimum?: Role;
}

export interface NavGroup {
  readonly label: MessageKey;
  readonly items: readonly NavItem[];
}

/**
 * The side navigation. A screen that arrives later adds one line here and its route in `routes.tsx`.
 */
export const navigation: readonly NavGroup[] = [
  {
    label: 'nav.group.main',
    items: [
      { to: '/', label: 'nav.home', icon: LayoutDashboard, end: true },
      { to: '/warehouses', label: 'nav.warehouses', icon: Warehouse },
      { to: '/products', label: 'nav.products', icon: Package },
      { to: '/inbound', label: 'nav.inbound', icon: Truck },
      { to: '/stock', label: 'nav.stock', icon: Boxes },
    ],
  },
  {
    label: 'nav.group.administration',
    items: [{ to: '/audit', label: 'nav.audit', icon: ClipboardList, minimum: 'supervisor' }],
  },
];

/**
 * What this user sees: the items their role allows, and no group that is left empty.
 */
export function visibleNavigation(roles: readonly Role[]): NavGroup[] {
  return navigation
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) => item.minimum === undefined || hasRole(roles, item.minimum),
      ),
    }))
    .filter((group) => group.items.length > 0);
}
