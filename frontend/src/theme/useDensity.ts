import { useSyncExternalStore } from 'react';
import { chooseDensity, currentDensity, subscribeDensity, type Density } from './density';

export function useDensity(): { density: Density; choose: typeof chooseDensity } {
  const density = useSyncExternalStore(subscribeDensity, () => currentDensity());

  return { density, choose: chooseDensity };
}
