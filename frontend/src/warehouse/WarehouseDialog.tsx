import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useCreateWarehouse } from '../api/warehouses';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { warehouseFormSchema, type WarehouseFormInput, type WarehouseFormOutput } from './schemas';

/** The form to create a warehouse. The API refuses a code that is already taken (409). */
export function WarehouseDialog({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const create = useCreateWarehouse();
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<WarehouseFormInput, unknown, WarehouseFormOutput>({
    resolver: zodResolver(warehouseFormSchema),
    defaultValues: { code: '', name: '' },
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
      title={t('warehouses.new')}
      description={t('warehouses.create.description')}
      submitting={create.isPending}
      failure={create.isError ? failureText(create.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          create.mutateAsync(values).then(
            (warehouse) => {
              toast.success(t('warehouses.created', { code: warehouse.code }));
              close(false);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <TextField
        label={t('warehouses.code')}
        autoComplete="off"
        error={fieldError(errors.code?.message)}
        {...register('code')}
      />
      <TextField
        label={t('warehouses.name')}
        autoComplete="off"
        error={fieldError(errors.name?.message)}
        {...register('name')}
      />
    </FormDialog>
  );
}
