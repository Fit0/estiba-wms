import { Warehouse } from 'lucide-react';
import { useTranslation } from '../i18n';

/**
 * The name of the app with its mark. With `compact` only the mark shows, and the name stays for
 * screen readers.
 */
export function BrandMark({ compact = false }: { compact?: boolean }) {
  const { t } = useTranslation();

  return (
    <span className="flex items-center gap-2">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-control bg-primary text-on-primary">
        <Warehouse aria-hidden="true" className="size-5" />
      </span>
      <span className={compact ? 'sr-only' : 'font-display text-headline-sm'}>{t('app.name')}</span>
    </span>
  );
}
