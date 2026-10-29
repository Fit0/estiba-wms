import { zodResolver } from '@hookform/resolvers/zod';
import { Plus, Trash2 } from 'lucide-react';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { useRegisterOrder, type Order } from '../api/orders';
import { Button } from '../components/Button';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { ProductFilter } from '../stock/ProductFilter';
import { useActiveWarehouse } from '../warehouse/ActiveWarehouse';
import { orderFormSchema, priorities, type OrderFormInput, type OrderFormOutput } from './schemas';

/** The next full hour in a week, as the `datetime-local` field writes it: a sensible cut-off to start from. */
function defaultCutOff(): string {
  const date = new Date(Date.now() + 24 * 3600 * 1000);
  date.setMinutes(0, 0, 0);
  const pad = (n: number): string => String(n).padStart(2, '0');

  return `${String(date.getFullYear())}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:00`;
}

/** The form to register an order for the active warehouse. Stock is reserved afterwards, from the order. */
export function OrderDialog({
  onClose,
  onCreated,
}: {
  onClose: () => void;
  onCreated: (order: Order) => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const { active } = useActiveWarehouse();
  const register = useRegisterOrder();
  const {
    register: field,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<OrderFormInput, unknown, OrderFormOutput>({
    resolver: zodResolver(orderFormSchema),
    defaultValues: {
      reference: '',
      customer: '',
      street: '',
      postalCode: '',
      city: '',
      countryCode: 'ES',
      priority: 'normal',
      cutOffAt: defaultCutOff(),
      lines: [{ productId: '', units: '' }],
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
      title={t('order.create.title')}
      description={t('order.create.description', { warehouse: active?.code ?? '—' })}
      submitting={register.isPending || active === null}
      failure={register.isError ? failureText(register.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) => {
          if (active === null) {
            return undefined;
          }

          const { street, postalCode, city, countryCode, ...rest } = values;

          return register
            .mutateAsync({
              ...rest,
              warehouseId: active.id,
              shipTo: { street, postalCode, city, countryCode },
            })
            .then(
              (order) => {
                toast.success(t('order.created', { reference: order.reference }));
                onCreated(order);
              },
              () => undefined,
            );
        })(event);
      }}
    >
      {active === null && (
        <p role="status" className="text-body-md text-fg-secondary">
          {t('order.create.noWarehouse')}
        </p>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField
          label={t('order.reference')}
          autoComplete="off"
          error={fieldError(errors.reference?.message)}
          {...field('reference')}
        />
        <TextField
          label={t('order.customer')}
          autoComplete="off"
          error={fieldError(errors.customer?.message)}
          {...field('customer')}
        />
        <Controller
          control={control}
          name="priority"
          render={({ field: f }) => (
            <Select
              label={t('order.priority')}
              value={f.value}
              onValueChange={f.onChange}
              options={priorities.map((value) => ({
                value,
                label: t(`order.priority.${value}`),
              }))}
            />
          )}
        />
        <TextField
          type="datetime-local"
          label={t('order.cutOff')}
          error={fieldError(errors.cutOffAt?.message)}
          {...field('cutOffAt')}
        />
      </div>

      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-label-md text-fg-secondary">{t('order.shipTo')}</legend>
        <TextField
          label={t('order.street')}
          autoComplete="off"
          error={fieldError(errors.street?.message)}
          {...field('street')}
        />
        <TextField
          label={t('order.postalCode')}
          autoComplete="off"
          error={fieldError(errors.postalCode?.message)}
          {...field('postalCode')}
        />
        <TextField
          label={t('order.city')}
          autoComplete="off"
          error={fieldError(errors.city?.message)}
          {...field('city')}
        />
        <TextField
          label={t('order.country')}
          autoComplete="off"
          maxLength={2}
          error={fieldError(errors.countryCode?.message)}
          {...field('countryCode')}
        />
      </fieldset>

      <fieldset className="grid gap-4">
        <legend className="mb-2 text-label-md text-fg-secondary">{t('order.section.lines')}</legend>
        {lines.fields.map((line, index) => (
          <div
            key={line.id}
            role="group"
            aria-label={t('order.line.n', { n: String(index + 1) })}
            className="grid gap-2 rounded-control border border-line p-3 sm:grid-cols-[1fr_8rem_auto] sm:items-start"
          >
            <Controller
              control={control}
              name={`lines.${String(index)}.productId` as `lines.${number}.productId`}
              render={({ field: f }) => (
                <div className="grid gap-1">
                  <ProductFilter
                    label={t('order.line.product')}
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
            <TextField
              label={t('order.line.units')}
              inputMode="numeric"
              autoComplete="off"
              error={fieldError(errors.lines?.[index]?.units?.message)}
              {...field(`lines.${String(index)}.units` as `lines.${number}.units`)}
            />
            <Button
              variant="ghost"
              icon
              aria-label={t('order.line.remove', { n: String(index + 1) })}
              disabled={lines.fields.length === 1}
              className="sm:mt-6"
              onClick={() => {
                lines.remove(index);
              }}
            >
              <Trash2 aria-hidden="true" className="size-4" />
            </Button>
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
              lines.append({ productId: '', units: '' });
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
