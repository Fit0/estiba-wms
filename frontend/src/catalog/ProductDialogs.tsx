import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm, useWatch } from 'react-hook-form';
import {
  useAssignGtin,
  useClassifyProduct,
  useDefinePackagingLevel,
  useRegisterProduct,
  useUpdateProduct,
  type Product,
} from '../api/products';
import { CheckboxField } from '../components/CheckboxField';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import {
  baseUnits,
  classifyFormSchema,
  editFormSchema,
  gtinFormSchema,
  packagingFormSchema,
  productFormSchema,
  storageClasses,
  turnoverClasses,
  type ClassifyFormInput,
  type ClassifyFormOutput,
  type EditFormInput,
  type EditFormOutput,
  type GtinFormInput,
  type PackagingFormInput,
  type PackagingFormOutput,
  type ProductFormInput,
  type ProductFormOutput,
} from './forms';
import { packagingTypes } from './schemas';

/*
 * The forms of the catalogue. Each one is drawn only while it is open (the page mounts it and
 * unmounts it on close), so it always starts from the product as it is now and from no old error.
 */

interface DialogProps {
  onClose: () => void;
}

/** The form to register a product. On success the caller is told which one was made. */
export function ProductDialog({
  onClose,
  onCreated,
}: DialogProps & { onCreated: (product: Product) => void }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const register = useRegisterProduct();
  const form = useForm<ProductFormInput, unknown, ProductFormOutput>({
    resolver: zodResolver(productFormSchema),
    defaultValues: {
      sku: '',
      name: '',
      baseUnit: 'each',
      storageClass: 'ambient',
      lengthMm: '',
      widthMm: '',
      heightMm: '',
      weightGrams: '',
      trackedByLot: false,
      trackedByExpiry: false,
      minShelfLifeAtReceiptDays: '',
      minShelfLifeAtShipmentDays: '',
      family: '',
      turnoverClass: 'C',
    },
  });
  const {
    register: field,
    control,
    handleSubmit,
    formState: { errors },
  } = form;
  const expires = useWatch({ control, name: 'trackedByExpiry' });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('product.create.title')}
      description={t('product.create.description')}
      submitting={register.isPending}
      failure={register.isError ? failureText(register.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          register.mutateAsync(values).then(
            (product) => {
              toast.success(t('product.created', { sku: product.sku }));
              onCreated(product);
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField
          label={t('product.sku')}
          autoComplete="off"
          error={fieldError(errors.sku?.message)}
          {...field('sku')}
        />
        <TextField
          label={t('product.name')}
          autoComplete="off"
          error={fieldError(errors.name?.message)}
          {...field('name')}
        />
        <Controller
          control={control}
          name="baseUnit"
          render={({ field: f }) => (
            <Select
              label={t('product.baseUnit')}
              value={f.value}
              onValueChange={f.onChange}
              options={baseUnits.map((value) => ({ value, label: t(`product.unit.${value}`) }))}
            />
          )}
        />
        <Controller
          control={control}
          name="storageClass"
          render={({ field: f }) => (
            <Select
              label={t('product.storageClass')}
              value={f.value}
              onValueChange={f.onChange}
              options={storageClasses.map((value) => ({
                value,
                label: t(`zone.class.${value}`),
              }))}
            />
          )}
        />
      </div>
      <div className="grid gap-4 sm:grid-cols-4">
        <TextField
          label={t('product.field.length')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.lengthMm?.message)}
          {...field('lengthMm')}
        />
        <TextField
          label={t('product.field.width')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.widthMm?.message)}
          {...field('widthMm')}
        />
        <TextField
          label={t('product.field.height')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.heightMm?.message)}
          {...field('heightMm')}
        />
        <TextField
          label={t('product.field.weight')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.weightGrams?.message)}
          {...field('weightGrams')}
        />
      </div>
      <div className="grid gap-1">
        <CheckboxField label={t('product.trackedByLot')} {...field('trackedByLot')} />
        <CheckboxField
          label={t('product.trackedByExpiry')}
          error={fieldError(errors.trackedByExpiry?.message)}
          {...field('trackedByExpiry')}
        />
      </div>
      {expires && (
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            label={t('product.field.shelfLifeReceipt')}
            inputMode="numeric"
            autoComplete="off"
            error={fieldError(errors.minShelfLifeAtReceiptDays?.message)}
            {...field('minShelfLifeAtReceiptDays')}
          />
          <TextField
            label={t('product.field.shelfLifeShipment')}
            inputMode="numeric"
            autoComplete="off"
            error={fieldError(errors.minShelfLifeAtShipmentDays?.message)}
            {...field('minShelfLifeAtShipmentDays')}
          />
        </div>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField
          label={t('product.field.family')}
          autoComplete="off"
          error={fieldError(errors.family?.message)}
          {...field('family')}
        />
        <Controller
          control={control}
          name="turnoverClass"
          render={({ field: f }) => (
            <Select
              label={t('product.turnover')}
              value={f.value}
              onValueChange={f.onChange}
              options={turnoverClasses.map((value) => ({
                value,
                label: t(`product.turnover.${value}`),
              }))}
            />
          )}
        />
      </div>
    </FormDialog>
  );
}

/** Name and measures. Only what changed is sent, as a merge patch; the SKU never changes. */
export function EditProductDialog({ product, onClose }: DialogProps & { product: Product }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const update = useUpdateProduct(product.id);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<EditFormInput, unknown, EditFormOutput>({
    resolver: zodResolver(editFormSchema),
    defaultValues: {
      name: product.name,
      lengthMm: String(product.lengthMm),
      widthMm: String(product.widthMm),
      heightMm: String(product.heightMm),
      weightGrams: String(product.weightGrams),
    },
  });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('product.edit.title', { sku: product.sku })}
      description={t('product.edit.description')}
      submitting={update.isPending}
      failure={update.isError ? failureText(update.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) => {
          const changes = Object.fromEntries(
            Object.entries(values).filter(
              ([key, value]) => value !== product[key as keyof EditFormOutput],
            ),
          );
          if (Object.keys(changes).length === 0) {
            onClose();

            return undefined;
          }

          return update.mutateAsync(changes).then(
            () => {
              toast.success(t('product.updated', { sku: product.sku }));
              onClose();
            },
            () => undefined,
          );
        })(event);
      }}
    >
      <TextField
        label={t('product.name')}
        autoComplete="off"
        error={fieldError(errors.name?.message)}
        {...register('name')}
      />
      <div className="grid gap-4 sm:grid-cols-4">
        <TextField
          label={t('product.field.length')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.lengthMm?.message)}
          {...register('lengthMm')}
        />
        <TextField
          label={t('product.field.width')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.widthMm?.message)}
          {...register('widthMm')}
        />
        <TextField
          label={t('product.field.height')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.heightMm?.message)}
          {...register('heightMm')}
        />
        <TextField
          label={t('product.field.weight')}
          inputMode="numeric"
          autoComplete="off"
          error={fieldError(errors.weightGrams?.message)}
          {...register('weightGrams')}
        />
      </div>
    </FormDialog>
  );
}

/** One more barcode for the product. The API checks the check digit and that no other product has it. */
export function GtinDialog({ product, onClose }: DialogProps & { product: Product }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const assign = useAssignGtin(product.id);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<GtinFormInput, unknown, GtinFormInput>({
    resolver: zodResolver(gtinFormSchema),
    defaultValues: { gtin: '' },
  });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('product.gtin.title', { sku: product.sku })}
      description={t('product.gtin.description')}
      submitting={assign.isPending}
      failure={assign.isError ? failureText(assign.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          assign.mutateAsync(values).then(
            () => {
              toast.success(t('product.gtin.added', { gtin: values.gtin }));
              onClose();
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <TextField
        label={t('receive.gtin')}
        inputMode="numeric"
        autoComplete="off"
        error={fieldError(errors.gtin?.message)}
        {...register('gtin')}
      />
    </FormDialog>
  );
}

/** How many base units go in an inner pack, a case or a pallet. */
export function PackagingDialog({ product, onClose }: DialogProps & { product: Product }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const define = useDefinePackagingLevel(product.id);
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<PackagingFormInput, unknown, PackagingFormOutput>({
    resolver: zodResolver(packagingFormSchema),
    defaultValues: { type: 'case', baseUnits: '' },
  });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('product.packaging.title', { sku: product.sku })}
      description={t('product.packaging.description')}
      submitting={define.isPending}
      failure={define.isError ? failureText(define.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          define.mutateAsync(values).then(
            () => {
              toast.success(t('product.packaging.saved'));
              onClose();
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <Controller
        control={control}
        name="type"
        render={({ field }) => (
          <Select
            label={t('product.packaging.type')}
            value={field.value}
            onValueChange={field.onChange}
            options={packagingTypes.map((value) => ({
              value,
              label: t(`product.packaging.${value}`),
            }))}
          />
        )}
      />
      <TextField
        label={t('product.packaging.units')}
        inputMode="numeric"
        autoComplete="off"
        error={fieldError(errors.baseUnits?.message)}
        {...register('baseUnits')}
      />
    </FormDialog>
  );
}

/** Turnover class (A, B or C) and family. An empty family takes the product out of its family. */
export function ClassifyDialog({ product, onClose }: DialogProps & { product: Product }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const classify = useClassifyProduct(product.id);
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<ClassifyFormInput, unknown, ClassifyFormOutput>({
    resolver: zodResolver(classifyFormSchema),
    defaultValues: { turnoverClass: product.turnoverClass, family: product.family ?? '' },
  });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('product.classify.title', { sku: product.sku })}
      description={t('product.classify.description')}
      submitting={classify.isPending}
      failure={classify.isError ? failureText(classify.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          classify.mutateAsync(values).then(
            () => {
              toast.success(t('product.classified', { sku: product.sku }));
              onClose();
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <Controller
        control={control}
        name="turnoverClass"
        render={({ field }) => (
          <Select
            label={t('product.turnover')}
            value={field.value}
            onValueChange={field.onChange}
            options={turnoverClasses.map((value) => ({
              value,
              label: t(`product.turnover.${value}`),
            }))}
          />
        )}
      />
      <TextField
        label={t('product.field.family')}
        autoComplete="off"
        error={fieldError(errors.family?.message)}
        {...register('family')}
      />
    </FormDialog>
  );
}
