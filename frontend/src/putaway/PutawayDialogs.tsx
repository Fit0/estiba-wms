import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { ApiError } from '../api/ApiError';
import { useConfirmPutaway, useSplitPutaway, type PutawayTask } from '../api/putaway';
import type { Location } from '../api/warehouses';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { formatNumber } from '../i18n/format';
import {
  confirmPutawaySchema,
  splitPutawaySchema,
  type ConfirmPutawayInput,
  type SplitPutawayInput,
  type SplitPutawayOutput,
} from './schemas';

/** The types of location that keep goods (INB-11): the API decides which of them suits the product. */
const storing: readonly Location['type'][] = ['reserve', 'pick_face', 'quarantine'];

const destinationReasons = [
  'unknown',
  'blocked',
  'wrong_type',
  'storage_class',
  'no_room',
  'same_as_source',
] as const;

/**
 * Why a location was refused, in words an operator can act on. The API names the location by its id,
 * which means nothing on the floor, so a refusal of the destination is written here with its code.
 * Any other failure is the sentence of the API.
 */
function confirmFailure(
  error: unknown,
  locations: readonly Location[],
  t: ReturnType<typeof useTranslation>['t'],
): string {
  if (error instanceof ApiError && error.code === 'inbound.putaway_destination_invalid') {
    const reason = destinationReasons.find((value) => value === error.parameters.reason);
    const id = error.parameters.locationId;
    const code = locations.find((location) => location.id === id)?.code;
    if (reason !== undefined && code !== undefined) {
      return t(`putaway.error.destination.${reason}`, { code });
    }
  }

  return failureText(error, t);
}

/**
 * Puts the goods of a task in a location. It starts on the suggested one; choosing another asks for
 * the reason (INB-13). `task` is the one being confirmed, `null` while the dialog is closed.
 */
export function ConfirmPutawayDialog({
  task,
  product,
  locations,
  onClose,
}: {
  task: PutawayTask | null;
  product: string;
  locations: readonly Location[];
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const confirm = useConfirmPutaway();
  const suggested = task?.suggestedLocationId ?? null;
  const schema = useMemo(() => confirmPutawaySchema(suggested), [suggested]);
  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ConfirmPutawayInput>({
    resolver: zodResolver(schema),
    values: { locationId: suggested ?? '', reason: '' },
  });
  const chosen = useWatch({ control, name: 'locationId' });
  const needsReason = suggested !== null && chosen !== suggested;
  const options = locations
    .filter((location) => storing.includes(location.type))
    .map((location) => ({
      value: location.id,
      label: `${location.code} · ${t(`location.type.${location.type}`)}`,
    }));

  function close(): void {
    reset();
    confirm.reset();
    onClose();
  }

  return (
    <FormDialog
      open={task !== null}
      onOpenChange={(open) => {
        if (!open) {
          close();
        }
      }}
      title={t('putaway.confirm.title', { product })}
      description={t('putaway.confirm.description')}
      submitLabel={t('putaway.confirm.submit')}
      submitting={confirm.isPending}
      failure={confirm.isError ? confirmFailure(confirm.error, locations, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) => {
          if (task === null) {
            return undefined;
          }
          const reason = values.reason.trim();

          return confirm
            .mutateAsync({
              id: task.id,
              body: {
                locationId: values.locationId,
                ...(needsReason && reason !== '' ? { reason } : {}),
              },
            })
            .then(
              () => {
                toast.success(t('putaway.confirm.done'));
                close();
              },
              () => undefined,
            );
        })(event);
      }}
    >
      <Controller
        control={control}
        name="locationId"
        render={({ field }) => (
          <Select
            label={t('putaway.destination')}
            value={field.value === '' ? undefined : field.value}
            onValueChange={field.onChange}
            options={options}
            placeholder={t('putaway.destination.placeholder')}
            error={fieldError(errors.locationId?.message)}
          />
        )}
      />
      {needsReason && (
        <TextField
          label={t('putaway.reason')}
          autoComplete="off"
          error={fieldError(errors.reason?.message)}
          {...register('reason')}
        />
      )}
    </FormDialog>
  );
}

/** Gives some of the units of a task to a new one, so they can go to another location. */
export function SplitPutawayDialog({
  task,
  product,
  onClose,
}: {
  task: PutawayTask | null;
  product: string;
  onClose: () => void;
}) {
  const { t, language } = useTranslation();
  const fieldError = useFieldError();
  const split = useSplitPutaway();
  const units = task?.units ?? 2;
  const schema = useMemo(() => splitPutawaySchema(units), [units]);
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<SplitPutawayInput, unknown, SplitPutawayOutput>({
    resolver: zodResolver(schema),
    defaultValues: { units: '' },
  });

  function close(): void {
    reset();
    split.reset();
    onClose();
  }

  return (
    <FormDialog
      open={task !== null}
      onOpenChange={(open) => {
        if (!open) {
          close();
        }
      }}
      title={t('putaway.split.title', { product })}
      description={t('putaway.split.description', { units: formatNumber(language, units) })}
      submitLabel={t('putaway.split.submit')}
      submitting={split.isPending}
      failure={split.isError ? failureText(split.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) => {
          if (task === null) {
            return undefined;
          }

          return split.mutateAsync({ id: task.id, units: values.units }).then(
            () => {
              toast.success(t('putaway.split.done'));
              close();
            },
            () => undefined,
          );
        })(event);
      }}
    >
      <TextField
        label={t('putaway.split.units')}
        inputMode="numeric"
        autoComplete="off"
        error={fieldError(errors.units?.message)}
        {...register('units')}
      />
    </FormDialog>
  );
}
