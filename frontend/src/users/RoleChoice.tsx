import type { ComponentProps } from 'react';
import { roles } from '../auth/roles';
import { CheckboxField } from '../components/CheckboxField';
import { useTranslation } from '../i18n';

/**
 * The roles a user can hold, one checkbox each. Roles are not cumulative here: the API stores the
 * ones chosen, and the highest decides what the user may do (ADR 0008).
 */
export function RoleChoice({
  error,
  register,
}: {
  error?: string | undefined;
  register: () => ComponentProps<'input'>;
}) {
  const { t } = useTranslation();

  return (
    <fieldset className="grid gap-1">
      <legend className="text-label-md text-fg-secondary">{t('users.roles')}</legend>
      {roles.map((role) => (
        <CheckboxField key={role} label={t(`role.${role}`)} value={role} {...register()} />
      ))}
      {error !== undefined && (
        <p role="alert" className="text-label-md text-bad">
          {error}
        </p>
      )}
    </fieldset>
  );
}
