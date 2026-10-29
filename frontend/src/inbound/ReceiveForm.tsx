import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useReceiveItem } from '../api/inbound';
import { Button } from '../components/Button';
import { failureText, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import {
  conditions,
  receiveFormSchema,
  type ReceiveFormInput,
  type ReceiveFormOutput,
} from './schemas';

/**
 * Counts one item at the dock: the barcode (typed, pasted or read by a scanner that acts as a
 * keyboard), the units and, where the product needs them, the lot and the expiry date. After each
 * count the form clears and the cursor goes back to the barcode, ready for the next one.
 */
export function ReceiveForm({ receiptId }: { receiptId: string }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const receive = useReceiveItem(receiptId);
  const [counted, setCounted] = useState(0);
  const {
    register,
    control,
    handleSubmit,
    reset,
    setFocus,
    formState: { errors },
  } = useForm<ReceiveFormInput, unknown, ReceiveFormOutput>({
    resolver: zodResolver(receiveFormSchema),
    defaultValues: { gtin: '', units: '', lot: '', expiryDate: '', condition: 'good' },
  });

  // `reset` registers the fields again on the next render, so the cursor goes back after it.
  useEffect(() => {
    if (counted > 0) {
      setFocus('gtin');
    }
  }, [counted, setFocus]);

  return (
    <form
      noValidate
      aria-label={t('receipt.section.count')}
      className="grid gap-4"
      onSubmit={(event) => {
        void handleSubmit((values) =>
          receive.mutateAsync(values).then(
            () => {
              toast.success(
                t('receive.counted', { units: String(values.units), gtin: values.gtin }),
              );
              reset();
              setCounted((count) => count + 1);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <TextField
          label={t('receive.gtin')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.gtin?.message)}
          {...register('gtin')}
        />
        <TextField
          label={t('receive.units')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.units?.message)}
          {...register('units')}
        />
        <TextField
          label={t('receive.lot')}
          autoComplete="off"
          error={fieldError(errors.lot?.message)}
          {...register('lot')}
        />
        <TextField
          label={t('receive.expiry')}
          inputMode="numeric"
          placeholder="2026-12-31"
          autoComplete="off"
          error={fieldError(errors.expiryDate?.message)}
          {...register('expiryDate')}
        />
        <Controller
          control={control}
          name="condition"
          render={({ field }) => (
            <Select
              label={t('receive.condition')}
              value={field.value}
              onValueChange={field.onChange}
              options={conditions.map((value) => ({
                value,
                label: t(`receive.condition.${value}`),
              }))}
            />
          )}
        />
      </div>
      {receive.isError && (
        <p role="alert" className="text-body-md text-bad">
          {failureText(receive.error, t)}
        </p>
      )}
      <div className="flex justify-end">
        <Button type="submit" variant="primary" disabled={receive.isPending}>
          {receive.isPending ? t('form.saving') : t('receive.submit')}
        </Button>
      </div>
    </form>
  );
}
