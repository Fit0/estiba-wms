import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useSetLocationBlocked, type Location } from '../api/warehouses';
import { failureText, FormDialog, useFieldError } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { blockFormSchema, type BlockFormInput } from './schemas';

/** Asks for the reason before a location is blocked. `location` is the one being blocked. */
export function BlockDialog({
  warehouseId,
  location,
  onClose,
}: {
  warehouseId: string;
  location: Location | null;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const fieldError = useFieldError();
  const block = useSetLocationBlocked(warehouseId);
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<BlockFormInput>({
    resolver: zodResolver(blockFormSchema),
    defaultValues: { reason: '' },
  });

  function close(): void {
    reset();
    block.reset();
    onClose();
  }

  return (
    <FormDialog
      open={location !== null}
      onOpenChange={(open) => {
        if (!open) {
          close();
        }
      }}
      title={t('location.block.title', { code: location?.code ?? '' })}
      description={t('location.block.description')}
      submitLabel={t('location.block.confirm')}
      submitting={block.isPending}
      failure={block.isError ? failureText(block.error, t) : undefined}
      onSubmit={(event) => {
        void handleSubmit((values) => {
          if (location === null) {
            return undefined;
          }

          return block.mutateAsync({ id: location.id, reason: values.reason }).then(
            () => {
              toast.success(t('location.blocked', { code: location.code }));
              close();
            },
            () => undefined,
          );
        })(event);
      }}
    >
      <TextField
        label={t('location.block.reason')}
        autoComplete="off"
        error={fieldError(errors.reason?.message)}
        {...register('reason')}
      />
    </FormDialog>
  );
}
