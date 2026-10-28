import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { useCreateZone } from '../api/warehouses';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { storageClasses, zoneFormSchema, type ZoneFormInput, type ZoneFormOutput } from './schemas';

/** The form to define a zone of a warehouse. */
export function ZoneDialog({
  warehouseId,
  open,
  onOpenChange,
}: {
  warehouseId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const create = useCreateZone(warehouseId);
  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ZoneFormInput, unknown, ZoneFormOutput>({
    resolver: zodResolver(zoneFormSchema),
    defaultValues: { code: '', name: '', storageClass: 'ambient', pickSequence: '10' },
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
      title={t('zone.create.title')}
      description={t('zone.create.description')}
      submitting={create.isPending}
      failure={create.isError ? failureText(create.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          create.mutateAsync(values).then(
            (zone) => {
              toast.success(t('zone.created', { code: zone.code }));
              close(false);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <TextField
        label={t('zone.code')}
        autoComplete="off"
        error={fieldError(errors.code?.message)}
        {...register('code')}
      />
      <TextField
        label={t('zone.name')}
        autoComplete="off"
        error={fieldError(errors.name?.message)}
        {...register('name')}
      />
      <Controller
        control={control}
        name="storageClass"
        render={({ field }) => (
          <Select
            label={t('zone.storageClass')}
            value={field.value}
            onValueChange={field.onChange}
            options={storageClasses.map((value) => ({
              value,
              label: t(`zone.class.${value}`),
            }))}
          />
        )}
      />
      <TextField
        label={t('zone.pickSequence')}
        inputMode="numeric"
        autoComplete="off"
        error={fieldError(errors.pickSequence?.message)}
        {...register('pickSequence')}
      />
    </FormDialog>
  );
}
