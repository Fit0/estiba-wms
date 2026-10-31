import { useEffect, useRef, useState, type SubmitEvent } from 'react';
import { useProduct } from '../api/products';
import { useConfirmPickLine, type PickLine } from '../api/picking';
import type { PickList } from '../api/waves';
import { Button } from '../components/Button';
import { failureText } from '../components/FormDialog';
import { TextField } from '../components/TextField';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { ScanField } from '../scanner/ScanField';

const gtinPattern = /^\d{8,14}$/;

function same(a: string, b: string): boolean {
  return a.trim().toUpperCase() === b.trim().toUpperCase();
}

interface Errors {
  location?: string;
  gtin?: string;
  lot?: string;
  units?: string;
  reason?: string;
}

/**
 * One stop of the walk: where to go, what to take and how many, and the fields to confirm it by
 * scanning the location, the product and, if it has one, the lot. The scanner types the code and
 * presses Enter, which moves to the next field; Enter in the last one confirms. Fewer units than asked
 * is a short pick and asks why. The API checks the scans against the stop again.
 */
export function PickStopForm({
  list,
  line,
  index,
  total,
}: {
  list: PickList;
  line: PickLine;
  /** Which stop this is, from 1. */
  index: number;
  total: number;
}) {
  const { t } = useTranslation();
  const confirm = useConfirmPickLine(list.id);
  const product = useProduct(line.productId);
  const form = useRef<HTMLFormElement>(null);
  const [location, setLocation] = useState('');
  const [gtin, setGtin] = useState('');
  const [lot, setLot] = useState('');
  const [units, setUnits] = useState(String(line.quantityToPick));
  const [reason, setReason] = useState('');
  const [errors, setErrors] = useState<Errors>({});

  const picked = Number(units);
  const isShort = units.trim() !== '' && Number.isInteger(picked) && picked < line.quantityToPick;

  function validate(): Errors {
    const found: Errors = {};
    if (!same(location, line.locationCode)) {
      found.location = t('picking.error.location');
    }
    if (!gtinPattern.test(gtin.trim())) {
      found.gtin = t('receive.error.gtin');
    }
    if (line.lot !== null && !same(lot, line.lot)) {
      found.lot = t('picking.error.lot');
    }
    if (
      units.trim() === '' ||
      !Number.isInteger(picked) ||
      picked < 0 ||
      picked > line.quantityToPick
    ) {
      found.units = t('picking.error.units', { max: String(line.quantityToPick) });
    } else if (isShort && reason.trim() === '') {
      found.reason = t('picking.error.reason');
    }

    return found;
  }

  function submit(event: SubmitEvent<HTMLFormElement>): void {
    event.preventDefault();
    const found = validate();
    setErrors(found);
    if (Object.keys(found).length > 0) {
      return;
    }
    confirm.mutate(
      {
        position: line.position,
        location: location.trim(),
        gtin: gtin.trim(),
        units: picked,
        ...(line.lot === null ? {} : { lot: lot.trim() }),
        ...(isShort ? { reason: reason.trim() } : {}),
      },
      {
        onSuccess: () => {
          toast.success(t('picking.confirmed', { n: String(index) }));
        },
      },
    );
  }

  // A scanner presses Enter after the code: go to the next field, and confirm from the last one.
  useEffect(() => {
    const element = form.current;
    if (element === null) {
      return;
    }
    function onEnter(event: KeyboardEvent): void {
      if (
        event.key !== 'Enter' ||
        !(event.target instanceof HTMLInputElement) ||
        element === null
      ) {
        return;
      }
      event.preventDefault();
      const inputs = Array.from(element.querySelectorAll('input'));
      const next = inputs[inputs.indexOf(event.target) + 1];
      if (next === undefined) {
        element.requestSubmit();
      } else {
        next.focus();
      }
    }
    element.addEventListener('keydown', onEnter);

    return () => {
      element.removeEventListener('keydown', onEnter);
    };
  }, []);

  return (
    <form
      ref={form}
      noValidate
      aria-label={t('picking.stop', { n: String(index), total: String(total) })}
      className="grid gap-gutter"
      onSubmit={submit}
    >
      <div className="grid gap-1 rounded-panel border border-line-strong bg-surface-2 p-gutter">
        <p className="text-label-md text-fg-secondary">
          {t('picking.stop', { n: String(index), total: String(total) })}
        </p>
        <p className="font-display text-headline-lg tnum">{line.locationCode}</p>
        <p className="text-body-lg">
          {product.data === undefined ? '…' : `${product.data.sku} · ${product.data.name}`}
        </p>
        <p className="text-headline-md tnum">
          {t('picking.take', { units: String(line.quantityToPick) })}
        </p>
        {line.lot !== null && (
          <p className="text-body-md text-fg-secondary tnum">
            {t('picking.lot', { lot: line.lot })}
            {line.expiryDate !== null && ` · ${t('picking.expiry', { date: line.expiryDate })}`}
          </p>
        )}
      </div>

      <ScanField
        label={t('picking.scan.location')}
        value={location}
        onScan={setLocation}
        error={errors.location}
        // eslint-disable-next-line jsx-a11y/no-autofocus -- the next thing the operator does is scan
        autoFocus
      />
      <ScanField
        label={t('picking.scan.product')}
        value={gtin}
        onScan={setGtin}
        inputMode="numeric"
        error={errors.gtin}
      />
      {line.lot !== null && (
        <ScanField label={t('picking.scan.lot')} value={lot} onScan={setLot} error={errors.lot} />
      )}
      <TextField
        label={t('picking.unitsField')}
        type="number"
        inputMode="numeric"
        min={0}
        max={line.quantityToPick}
        value={units}
        onChange={(event) => {
          setUnits(event.target.value);
        }}
        error={errors.units}
      />
      {isShort && (
        <TextField
          label={t('picking.reason')}
          maxLength={200}
          value={reason}
          onChange={(event) => {
            setReason(event.target.value);
          }}
          error={errors.reason}
        />
      )}
      {confirm.isError && (
        <p role="alert" className="text-body-md text-bad">
          {failureText(confirm.error, t)}
        </p>
      )}
      <Button type="submit" variant="primary" disabled={confirm.isPending}>
        {confirm.isPending ? t('form.saving') : t('picking.confirm')}
      </Button>
    </form>
  );
}
