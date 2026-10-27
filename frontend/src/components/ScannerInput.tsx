import { ScanBarcode } from 'lucide-react';
import { useEffect, useRef, useState, type ComponentProps } from 'react';
import { useTranslation } from '../i18n';
import { TextField } from './TextField';

/** How long the field shows that it read a code. */
const FLASH_MS = 200;

/**
 * An input for a barcode scanner. A hardware scanner types the code like a keyboard and ends it with
 * Enter, so the field takes what was typed when Enter comes, hands it over, empties itself and
 * flashes a green ring for 200 ms. The icon says that it is ready for a scanner, and a screen reader
 * hears the code that was read, because a flash is not enough for everyone.
 */
export function ScannerInput({
  label,
  onScan,
  ...props
}: Omit<ComponentProps<'input'>, 'id' | 'value' | 'onChange' | 'onKeyDown'> & {
  label: string;
  onScan: (code: string) => void;
}) {
  const { t } = useTranslation();
  const [typed, setTyped] = useState('');
  const [lastCode, setLastCode] = useState<string | null>(null);
  const [flashing, setFlashing] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );

  function flash(): void {
    clearTimeout(timer.current);
    setFlashing(true);
    timer.current = setTimeout(() => {
      setFlashing(false);
    }, FLASH_MS);
  }

  return (
    <>
      <TextField
        {...props}
        label={label}
        value={typed}
        onChange={(event) => {
          setTyped(event.target.value);
        }}
        onKeyDown={(event) => {
          const code = typed.trim();
          if (event.key !== 'Enter' || code === '') {
            return;
          }
          event.preventDefault();
          setLastCode(code);
          setTyped('');
          flash();
          onScan(code);
        }}
        inputClassName={flashing ? 'ring-2 ring-ok' : ''}
        action={<ScanBarcode aria-hidden="true" className="mr-2 size-4 text-info" />}
      />
      <span role="status" className="sr-only">
        {lastCode === null ? '' : t('scanner.read', { code: lastCode })}
      </span>
    </>
  );
}
