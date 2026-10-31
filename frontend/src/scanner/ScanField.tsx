import { Camera } from 'lucide-react';
import { useCallback, useState, type ComponentProps } from 'react';
import { Button } from '../components/Button';
import { TextField } from '../components/TextField';
import { useTranslation } from '../i18n';
import { CameraScanner } from './CameraScanner';
import { canUseCamera } from './detector';

/**
 * A field for something that is scanned: typed, pasted, read by a scanner that acts as a keyboard
 * (it types the code and presses Enter) or read with the camera. The camera button is there only
 * where the browser can open one.
 */
export function ScanField({
  label,
  onScan,
  ...props
}: Omit<ComponentProps<typeof TextField>, 'action' | 'onChange' | 'value'> & {
  value: string;
  /** Receives the text, typed or read. */
  onScan: (code: string) => void;
}) {
  const { t } = useTranslation();
  const [scanning, setScanning] = useState(false);
  const close = useCallback(() => {
    setScanning(false);
  }, []);
  const detected = useCallback(
    (code: string) => {
      setScanning(false);
      onScan(code);
    },
    [onScan],
  );

  return (
    <>
      <TextField
        label={label}
        autoComplete="off"
        autoCapitalize="characters"
        spellCheck={false}
        placeholder={t('scan.placeholder')}
        onChange={(event) => {
          onScan(event.target.value);
        }}
        action={
          canUseCamera() ? (
            <Button
              variant="ghost"
              icon
              aria-label={t('scan.camera.open', { field: label })}
              onClick={() => {
                setScanning(true);
              }}
            >
              <Camera aria-hidden="true" className="size-4" />
            </Button>
          ) : undefined
        }
        {...props}
      />
      {scanning && <CameraScanner onDetect={detected} onClose={close} />}
    </>
  );
}
