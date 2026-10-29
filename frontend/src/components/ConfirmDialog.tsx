import { Button } from './Button';
import { Dialog, DialogContent } from './ui/Dialog';
import { useTranslation } from '../i18n';

/**
 * Asks "are you sure?" before something that is hard to undo. The caller says what the question is
 * and what confirming does; `failure` is what the API said if it refused.
 */
export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  onConfirm,
  pending,
  failure,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  confirmLabel: string;
  onConfirm: () => void;
  pending: boolean;
  failure?: string | undefined;
}) {
  const { t } = useTranslation();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title={title} description={description}>
        {failure !== undefined && (
          <p role="alert" className="text-body-md text-bad">
            {failure}
          </p>
        )}
        <div className="flex justify-end gap-2">
          <Button
            onClick={() => {
              onOpenChange(false);
            }}
          >
            {t('form.cancel')}
          </Button>
          <Button variant="primary" disabled={pending} onClick={onConfirm}>
            {pending ? t('form.saving') : confirmLabel}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
