import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { useCreateLocation } from '../api/warehouses';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import {
  locationFormSchema,
  locationTypes,
  type LocationFormInput,
  type LocationFormOutput,
  type Zone,
} from './schemas';

/** The form to define a location in a zone of the warehouse. */
export function LocationDialog({
  warehouseId,
  zones,
  open,
  onOpenChange,
}: {
  warehouseId: string;
  zones: readonly Zone[];
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const create = useCreateLocation(warehouseId);
  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<LocationFormInput, unknown, LocationFormOutput>({
    resolver: zodResolver(locationFormSchema),
    defaultValues: {
      zoneId: zones[0]?.id ?? '',
      code: '',
      type: 'reserve',
      pickSequence: '10',
      maxWeightKg: '',
      maxVolumeLitres: '',
      maxPalletPositions: '',
    },
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
      title={t('location.create.title')}
      description={t('location.create.description')}
      submitting={create.isPending}
      failure={create.isError ? failureText(create.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          create.mutateAsync(values).then(
            (location) => {
              toast.success(t('location.created', { code: location.code }));
              close(false);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <Controller
        control={control}
        name="zoneId"
        render={({ field }) => (
          <Select
            label={t('location.zone')}
            value={field.value}
            onValueChange={field.onChange}
            options={zones.map((zone) => ({
              value: zone.id,
              label: `${zone.code} · ${zone.name}`,
            }))}
            error={fieldError(errors.zoneId?.message)}
          />
        )}
      />
      <TextField
        label={t('location.code')}
        autoComplete="off"
        error={fieldError(errors.code?.message)}
        {...register('code')}
      />
      <Controller
        control={control}
        name="type"
        render={({ field }) => (
          <Select
            label={t('location.type')}
            value={field.value}
            onValueChange={field.onChange}
            options={locationTypes.map((value) => ({ value, label: t(`location.type.${value}`) }))}
          />
        )}
      />
      <TextField
        label={t('location.pickSequence')}
        inputMode="numeric"
        autoComplete="off"
        error={fieldError(errors.pickSequence?.message)}
        {...register('pickSequence')}
      />
      <div className="grid gap-4 sm:grid-cols-3">
        <TextField
          label={t('location.maxWeightKg')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.maxWeightKg?.message)}
          {...register('maxWeightKg')}
        />
        <TextField
          label={t('location.maxVolumeLitres')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.maxVolumeLitres?.message)}
          {...register('maxVolumeLitres')}
        />
        <TextField
          label={t('location.maxPalletPositions')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.maxPalletPositions?.message)}
          {...register('maxPalletPositions')}
        />
      </div>
    </FormDialog>
  );
}
