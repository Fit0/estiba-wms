import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { useActiveWarehouse } from '../warehouse/ActiveWarehouse';

/**
 * The warehouse the screens work on, in the top bar. With a single warehouse there is nothing to
 * choose, so it is only named; with none, the bar says so.
 */
export function WarehouseSelect() {
  const { t } = useTranslation();
  const { warehouses, active, choose, loading } = useActiveWarehouse();

  if (loading) {
    return null;
  }

  if (active === null) {
    return (
      <span className="hidden text-body-md text-fg-secondary md:block">
        {t('activeWarehouse.none')}
      </span>
    );
  }

  return (
    <div className="hidden md:block">
      <Select
        hideLabel
        label={t('activeWarehouse.label')}
        value={active.id}
        onValueChange={choose}
        options={warehouses.map((warehouse) => ({
          value: warehouse.id,
          label: `${warehouse.code} · ${warehouse.name}`,
        }))}
        placeholder={t('activeWarehouse.placeholder')}
      />
    </div>
  );
}
