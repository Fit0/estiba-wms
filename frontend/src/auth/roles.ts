/**
 * The roles of the API. Each includes the ones below it, as in `security.yaml` (ADR 0008).
 */
export const roles = ['operator', 'supervisor', 'admin'] as const;

export type Role = (typeof roles)[number];

export function isRole(value: unknown): value is Role {
  return roles.some((role) => role === value);
}

/**
 * Whether someone with these roles may do what `required` allows. The server has the last word: this
 * only decides what to show.
 */
export function hasRole(held: readonly Role[], required: Role): boolean {
  const needed = roles.indexOf(required);

  return held.some((role) => roles.indexOf(role) >= needed);
}

/**
 * The highest of the roles, the one a person is shown with.
 */
export function highestRole(held: readonly Role[]): Role | null {
  return roles.findLast((role) => held.includes(role)) ?? null;
}
