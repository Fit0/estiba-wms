import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useOpenReceipt, type Asn } from '../api/inbound';
import { useDockLocations } from '../api/warehouses';
import { failureText, FormDialog } from '../components/FormDialog';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { useActiveWarehouse } from '../warehouse/ActiveWarehouse';

/** Asks for the dock of the active warehouse, opens the receipt there and goes to it. */
export function OpenReceiptDialog({
  asn,
  open,
  onOpenChange,
}: {
  asn: Asn;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { active } = useActiveWarehouse();
  const docks = useDockLocations(active?.id);
  const openReceipt = useOpenReceipt(asn.id);
  const [chosen, setChosen] = useState<string | null>(null);

  const options = (docks.data ?? []).map((dock) => ({ value: dock.id, label: dock.code }));
  const dock = chosen ?? options[0]?.value;

  let notice: string | undefined;
  if (active === null) {
    notice = t('receipt.open.noWarehouse');
  } else if (docks.isSuccess && options.length === 0) {
    notice = t('receipt.open.noDock');
  }

  function close(next: boolean): void {
    if (!next) {
      openReceipt.reset();
    }
    onOpenChange(next);
  }

  return (
    <FormDialog
      open={open}
      onOpenChange={close}
      title={t('receipt.open.title', { reference: asn.reference })}
      description={t('receipt.open.description', { warehouse: active?.code ?? '—' })}
      submitLabel={t('receipt.open.confirm')}
      submitting={openReceipt.isPending || dock === undefined}
      failure={openReceipt.isError ? failureText(openReceipt.error, t) : undefined}
      onSubmit={(event) => {
        event.preventDefault();
        if (dock === undefined) {
          return;
        }
        openReceipt.mutate(dock, {
          onSuccess: (receipt) => {
            close(false);
            void navigate(`/receipts/${receipt.id}`);
          },
        });
      }}
    >
      {notice === undefined ? (
        <Select
          label={t('receipt.open.dock')}
          value={dock}
          onValueChange={setChosen}
          options={options}
        />
      ) : (
        <p role="status" className="text-body-md text-fg-secondary">
          {notice}
        </p>
      )}
    </FormDialog>
  );
}
