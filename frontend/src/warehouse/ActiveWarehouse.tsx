import { createContext, use, useCallback, useMemo, useState, type ReactNode } from 'react';
import { useWarehouses, type Warehouse } from '../api/warehouses';

const WAREHOUSE_KEY = 'estiba-warehouse';

function readStored(): string | null {
  try {
    return window.localStorage.getItem(WAREHOUSE_KEY);
  } catch {
    return null;
  }
}

export interface ActiveWarehouse {
  readonly warehouses: readonly Warehouse[];
  /** The warehouse the screens work on, or `null` while the list loads or when there are none. */
  readonly active: Warehouse | null;
  readonly choose: (id: string) => void;
  readonly loading: boolean;
}

const ActiveWarehouseContext = createContext<ActiveWarehouse | null>(null);

/**
 * The warehouse the person works in, which the screens that depend on one read from here instead of
 * asking each time (E05-07). The choice is kept in the browser; if that warehouse is gone, or none
 * was ever chosen, it is the first of the list.
 */
export function ActiveWarehouseProvider({ children }: { children: ReactNode }) {
  const { data, isPending } = useWarehouses();
  const [chosen, setChosen] = useState<string | null>(readStored);

  const choose = useCallback((id: string) => {
    setChosen(id);
    try {
      window.localStorage.setItem(WAREHOUSE_KEY, id);
    } catch {
      // The choice still holds for this visit.
    }
  }, []);

  const value = useMemo<ActiveWarehouse>(() => {
    const warehouses = data ?? [];

    return {
      warehouses,
      active: warehouses.find((warehouse) => warehouse.id === chosen) ?? warehouses[0] ?? null,
      choose,
      loading: isPending,
    };
  }, [data, chosen, choose, isPending]);

  return <ActiveWarehouseContext value={value}>{children}</ActiveWarehouseContext>;
}

export function useActiveWarehouse(): ActiveWarehouse {
  const value = use(ActiveWarehouseContext);
  if (value === null) {
    throw new Error('useActiveWarehouse needs an ActiveWarehouseProvider above it.');
  }

  return value;
}
