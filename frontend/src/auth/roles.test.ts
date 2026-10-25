import { describe, expect, it } from 'vitest';
import { hasRole, highestRole, isRole } from './roles';

describe('roles', () => {
  it('lets each role do what the ones below it can', () => {
    expect(hasRole(['operator'], 'operator')).toBe(true);
    expect(hasRole(['supervisor'], 'operator')).toBe(true);
    expect(hasRole(['admin'], 'supervisor')).toBe(true);
  });

  it('keeps a role from doing what the ones above it can', () => {
    expect(hasRole(['operator'], 'supervisor')).toBe(false);
    expect(hasRole(['supervisor'], 'admin')).toBe(false);
  });

  it('takes the highest of several roles', () => {
    expect(hasRole(['operator', 'admin'], 'supervisor')).toBe(true);
  });

  it('gives nothing to someone without roles', () => {
    expect(hasRole([], 'operator')).toBe(false);
  });

  it('recognizes only the roles of the API', () => {
    expect(isRole('supervisor')).toBe(true);
    expect(isRole('root')).toBe(false);
    expect(isRole(undefined)).toBe(false);
  });

  it('shows someone with their highest role', () => {
    expect(highestRole(['operator', 'supervisor'])).toBe('supervisor');
    expect(highestRole([])).toBeNull();
  });
});
