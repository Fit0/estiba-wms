import { zodResolver } from '@hookform/resolvers/zod';
import { Plus, Trash2 } from 'lucide-react';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { useRegisterAsn, type Asn } from '../api/inbound';
import { Button } from '../components/Button';
import { tomorrowForInput } from '../components/dateTimeInput';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { ProductFilter } from '../stock/ProductFilter';
import { asnFormSchema, type AsnFormInput, type AsnFormOutput } from './schemas';

/** The form to register an expected delivery: who sends it, when, and what is coming. */
export function AsnDialog({
  onClose,
  onCreated,
}: {
  onClose: () => void;
  onCreated: (asn: Asn) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const register = useRegisterAsn();
  const {
    register: field,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<AsnFormInput, unknown, AsnFormOutput>({
    resolver: zodResolver(asnFormSchema),
    defaultValues: {
      reference: '',
      supplier: '',
      expectedDate: tomorrowForInput(),
      lines: [{ productId: '', units: '', lot: '', expiryDate: '' }],
    },
  });
  const lines = useFieldArray({ control, name: 'lines' });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('asn.create.title')}
      description={t('asn.create.description')}
      submitting={register.isPending}
      failure={register.isError ? failureText(register.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          register.mutateAsync(values).then(
            (asn) => {
              toast.success(t('asn.created', { reference: asn.reference }));
              onCreated(asn);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField
          label={t('asn.reference')}
          autoComplete="off"
          error={fieldError(errors.reference?.message)}
          {...field('reference')}
        />
        <TextField
          label={t('asn.supplier')}
          autoComplete="off"
          error={fieldError(errors.supplier?.message)}
          {...field('supplier')}
        />
        <TextField
          type="datetime-local"
          label={t('asn.expectedDate')}
          error={fieldError(errors.expectedDate?.message)}
          {...field('expectedDate')}
        />
      </div>

      <fieldset className="grid gap-4">
        <legend className="mb-2 text-label-md text-fg-secondary">{t('asn.section.lines')}</legend>
        {lines.fields.map((line, index) => (
          <div
            key={line.id}
            role="group"
            aria-label={t('order.line.n', { n: String(index + 1) })}
            className="grid gap-2 rounded-control border border-line p-3 sm:grid-cols-2"
          >
            <div className="sm:col-span-2">
              <Controller
                control={control}
                name={`lines.${String(index)}.productId` as `lines.${number}.productId`}
                render={({ field: f }) => (
                  <div className="grid gap-1">
                    <ProductFilter
                      label={t('asn.line.product')}
                      productId={f.value === '' ? undefined : f.value}
                      onChange={(id) => {
                        f.onChange(id ?? '');
                      }}
                    />
                    {fieldError(errors.lines?.[index]?.productId?.message) !== undefined && (
                      <p className="text-label-md text-bad">
                        {fieldError(errors.lines?.[index]?.productId?.message)}
                      </p>
                    )}
                  </div>
                )}
              />
            </div>
            <TextField
              label={t('order.line.units')}
              inputMode="numeric"
              autoComplete="off"
              error={fieldError(errors.lines?.[index]?.units?.message)}
              {...field(`lines.${String(index)}.units` as `lines.${number}.units`)}
            />
            <TextField
              label={t('asn.line.lotOptional')}
              autoComplete="off"
              error={fieldError(errors.lines?.[index]?.lot?.message)}
              {...field(`lines.${String(index)}.lot` as `lines.${number}.lot`)}
            />
            <TextField
              type="date"
              label={t('asn.line.expiryOptional')}
              error={fieldError(errors.lines?.[index]?.expiryDate?.message)}
              {...field(`lines.${String(index)}.expiryDate` as `lines.${number}.expiryDate`)}
            />
            <div className="flex items-end justify-end">
              <Button
                variant="ghost"
                icon
                aria-label={t('order.line.remove', { n: String(index + 1) })}
                disabled={lines.fields.length === 1}
                onClick={() => {
                  lines.remove(index);
                }}
              >
                <Trash2 aria-hidden="true" className="size-4" />
              </Button>
            </div>
          </div>
        ))}
        {fieldError(errors.lines?.message ?? errors.lines?.root?.message) !== undefined && (
          <p role="alert" className="text-label-md text-bad">
            {fieldError(errors.lines?.message ?? errors.lines?.root?.message)}
          </p>
        )}
        <div>
          <Button
            onClick={() => {
              lines.append({ productId: '', units: '', lot: '', expiryDate: '' });
            }}
          >
            <Plus aria-hidden="true" className="size-4" />
            {t('order.line.add')}
          </Button>
        </div>
      </fieldset>
    </FormDialog>
  );
}
