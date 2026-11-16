import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useCreateUser } from '../api/users';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { RoleChoice } from './RoleChoice';
import { userFormSchema, type UserFormInput, type UserFormOutput } from './schemas';

/**
 * The form to add a user. The admin types the first password and tells the person; it is sent once
 * and never shown again. The API refuses an email that is already taken (409).
 */
export function UserDialog({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const create = useCreateUser();
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<UserFormInput, unknown, UserFormOutput>({
    resolver: zodResolver(userFormSchema),
    defaultValues: { email: '', password: '', roles: ['operator'] },
  });

  function close(next: boolean): void {
    if (!next) {
      reset();
      create.reset();
    }
    onOpenChange(next);
  }

  return (
    <FormDialog
      open={open}
      onOpenChange={close}
      title={t('users.new')}
      description={t('users.create.description')}
      submitting={create.isPending}
      failure={create.isError ? failureText(create.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          create.mutateAsync(values).then(
            (user) => {
              toast.success(t('users.created', { email: user.email }));
              close(false);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <TextField
        label={t('users.email')}
        type="email"
        autoComplete="off"
        error={fieldError(errors.email?.message)}
        {...register('email')}
      />
      <TextField
        label={t('users.password')}
        type="password"
        autoComplete="new-password"
        error={fieldError(errors.password?.message)}
        {...register('password')}
      />
      <RoleChoice error={fieldError(errors.roles?.message)} register={() => register('roles')} />
    </FormDialog>
  );
}
