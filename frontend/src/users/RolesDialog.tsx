import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { isRole } from '../auth/roles';
import { useChangeUserRoles, type User } from '../api/users';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { RoleChoice } from './RoleChoice';
import { rolesFormSchema, type RolesFormInput, type RolesFormOutput } from './schemas';

/** Replaces the roles of a user. The API refuses to leave the tenant without an active admin. */
export function RolesDialog({
  user,
  onOpenChange,
}: {
  user: User;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const change = useChangeUserRoles(user.id);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RolesFormInput, unknown, RolesFormOutput>({
    resolver: zodResolver(rolesFormSchema),
    defaultValues: { roles: user.roles.filter(isRole) },
  });

  return (
    <FormDialog
      open
      onOpenChange={onOpenChange}
      title={t('users.roles.title', { email: user.email })}
      description={t('users.roles.description')}
      submitting={change.isPending}
      failure={change.isError ? failureText(change.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          change.mutateAsync(values).then(
            () => {
              toast.success(t('users.roles.done', { email: user.email }));
              onOpenChange(false);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <RoleChoice error={fieldError(errors.roles?.message)} register={() => register('roles')} />
    </FormDialog>
  );
}
