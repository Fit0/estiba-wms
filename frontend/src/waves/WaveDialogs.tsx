import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useOrders, type Order } from '../api/orders';
import { useCreateWave, useReleaseWave, type Wave } from '../api/waves';
import { CheckboxField } from '../components/CheckboxField';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { formatDateTime } from '../i18n/format';
import { useActiveWarehouse } from '../warehouse/ActiveWarehouse';
import { isWaveable } from './display';
import { releaseFormSchema, type ReleaseFormInput, type ReleaseFormOutput } from './schemas';

/** The orders that could join a wave now, of the active warehouse: fully or backorder-accepted reserved. */
function useWaveableOrders(): { orders: Order[]; loading: boolean } {
  const { active } = useActiveWarehouse();
  const allocated = useOrders({ page: 1, itemsPerPage: 100, status: 'allocated' });
  const partial = useOrders({ page: 1, itemsPerPage: 100, status: 'partially_allocated' });
  const orders = [...(allocated.data?.items ?? []), ...(partial.data?.items ?? [])].filter(
    (order) => isWaveable(order) && (active === null || order.warehouseId === active.id),
  );

  return { orders, loading: allocated.isPending || partial.isPending };
}

/** Groups orders into a wave: the person ticks the ones that are ready, and the API checks the rest. */
export function CreateWaveDialog({
  onClose,
  onCreated,
}: {
  onClose: () => void;
  onCreated: (wave: Wave) => void;
}) {
  const { t, language } = useTranslation();
  const { orders, loading } = useWaveableOrders();
  const create = useCreateWave();
  const [chosen, setChosen] = useState<ReadonlySet<string>>(new Set());
  const [missing, setMissing] = useState(false);

  function toggle(id: string, on: boolean): void {
    setMissing(false);
    setChosen((current) => {
      const next = new Set(current);
      if (on) {
        next.add(id);
      } else {
        next.delete(id);
      }

      return next;
    });
  }

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('wave.create.title')}
      description={t('wave.create.description')}
      submitting={create.isPending}
      failure={create.isError ? failureText(create.error, t) : undefined}
      onSubmit={(event) => {
        event.preventDefault();
        if (chosen.size === 0) {
          setMissing(true);

          return;
        }
        create.mutate([...chosen], {
          onSuccess: (wave) => {
            toast.success(t('wave.created'));
            onCreated(wave);
          },
        });
      }}
    >
      {loading ? (
        <p role="status" className="text-body-md text-fg-secondary">
          {t('common.loading')}
        </p>
      ) : orders.length === 0 ? (
        <p role="status" className="text-body-md text-fg-secondary">
          {t('wave.create.none')}
        </p>
      ) : (
        <fieldset className="grid gap-1">
          <legend className="mb-1 text-label-md text-fg-secondary">
            {t('wave.create.orders')}
          </legend>
          {orders.map((order) => (
            <CheckboxField
              key={order.id}
              label={t('wave.create.order', {
                reference: order.reference,
                customer: order.customer,
                cutOff: formatDateTime(language, order.cutOffAt),
              })}
              checked={chosen.has(order.id)}
              onChange={(event) => {
                toggle(order.id, event.target.checked);
              }}
            />
          ))}
          {missing && (
            <p role="alert" className="text-label-md text-bad">
              {t('wave.error.orders')}
            </p>
          )}
        </fieldset>
      )}
    </FormDialog>
  );
}

/** Sends a planned wave to the floor, shared among one or more pick lists (one per operator). */
export function ReleaseWaveDialog({ wave, onClose }: { wave: Wave; onClose: () => void }) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const release = useReleaseWave(wave.id);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ReleaseFormInput, unknown, ReleaseFormOutput>({
    resolver: zodResolver(releaseFormSchema),
    defaultValues: { pickLists: '1' },
  });

  return (
    <FormDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title={t('wave.release.title')}
      description={t('wave.release.description')}
      submitLabel={t('wave.release.confirm')}
      submitting={release.isPending}
      failure={release.isError ? failureText(release.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) =>
          release.mutateAsync(values.pickLists).then(
            () => {
              toast.success(t('wave.release.done'));
              onClose();
            },
            () => undefined,
          ),
        )(event);
      }}
    >
      <TextField
        label={t('wave.release.pickLists')}
        inputMode="numeric"
        autoComplete="off"
        error={fieldError(errors.pickLists?.message)}
        {...register('pickLists')}
      />
    </FormDialog>
  );
}
