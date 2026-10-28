import type { SubmitEventHandler, ReactNode } from 'react';
import { ApiError } from '../api/ApiError';
import { isMessageKey, useTranslation } from '../i18n';
import { Button } from './Button';
import { Dialog, DialogContent } from './ui/Dialog';

type Translate = ReturnType<typeof useTranslation>['t'];

/**
 * The text of an error of a field. The schemas of the forms (Zod) give keys of the catalog, so the
 * text is in the language that is on now.
 */
export function useFieldError(): (message: string | undefined) => string | undefined {
  const { t } = useTranslation();

  return (message) => (isMessageKey(message) ? t(message) : message);
}

/** What went wrong with a request that failed: the sentence of the API, in the language asked for. */
export function failureText(error: unknown, t: Translate): string {
  return error instanceof ApiError && error.detail !== ''
    ? t('form.failed', { detail: error.detail })
    : t('form.failedUnknown');
}

/**
 * A dialog around a form: title, the fields, what the API said if it refused, and Cancel / Save.
 * The form is validated by the caller (React Hook Form); this only draws it and keeps the button from
 * being pressed twice while the request is on its way.
 */
export function FormDialog({
  open,
  onOpenChange,
  title,
  description,
  onSubmit,
  submitting,
  failure,
  submitLabel,
  children,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  onSubmit: SubmitEventHandler<HTMLFormElement>;
  submitting: boolean;
  failure?: string | undefined;
  submitLabel?: string;
  children: ReactNode;
}) {
  const { t } = useTranslation();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title={title} {...(description === undefined ? {} : { description })}>
        <form onSubmit={onSubmit} noValidate className="grid gap-4">
          {children}
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
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? t('form.saving') : (submitLabel ?? t('form.save'))}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
