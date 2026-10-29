import { X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { useProduct, useProducts } from '../api/products';
import { Button } from '../components/Button';
import { TextField } from '../components/TextField';
import { useTranslation } from '../i18n';

const searchDelay = 300;
const maxResults = 8;

/**
 * Chooses one product by typing part of its SKU or name. The API filters by id, so the text is
 * searched in the catalogue (`q`, ADR 0031), the person picks from what is found, and the choice is the
 * id. Once chosen it shows as a label with a button to remove it.
 */
export function ProductFilter({
  productId,
  onChange,
  label,
}: {
  productId: string | undefined;
  onChange: (productId: string | undefined) => void;
  /** The name of the field; the stock filter's by default. */
  label?: string;
}) {
  const { t } = useTranslation();
  const name = label ?? t('stock.filter.product');
  const resultsId = useId();
  const chosen = useProduct(productId ?? '');
  const [typed, setTyped] = useState('');
  const [text, setText] = useState('');
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );
  const found = useProducts(
    { page: 1, itemsPerPage: maxResults, q: text },
    { enabled: productId === undefined && text !== '' },
  );

  function type(value: string): void {
    setTyped(value);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      setText(value.trim());
    }, searchDelay);
  }

  if (productId !== undefined) {
    const shown = chosen.data === undefined ? '…' : `${chosen.data.sku} · ${chosen.data.name}`;

    return (
      <div className="grid gap-1">
        <span className="text-label-md text-fg-secondary">{name}</span>
        <div className="flex h-control items-center gap-1 rounded-control border border-input-border bg-surface-1 pl-3 text-body-md">
          <span className="truncate">{shown}</span>
          <Button
            variant="ghost"
            icon
            aria-label={t('stock.filter.product.clear')}
            onClick={() => {
              setTyped('');
              setText('');
              onChange(undefined);
            }}
          >
            <X aria-hidden="true" className="size-4" />
          </Button>
        </div>
      </div>
    );
  }

  const items = text === '' ? [] : (found.data?.items ?? []);

  return (
    <div className="grid gap-1">
      <TextField
        type="search"
        label={name}
        placeholder={t('stock.filter.product.placeholder')}
        autoComplete="off"
        aria-controls={resultsId}
        value={typed}
        onChange={(event) => {
          type(event.target.value);
        }}
      />
      <div id={resultsId} aria-live="polite">
        {text !== '' && found.isSuccess && items.length === 0 && (
          <p className="text-body-md text-fg-secondary">{t('stock.filter.product.none')}</p>
        )}
        {items.length > 0 && (
          <ul
            aria-label={t('stock.filter.product.results')}
            className="grid rounded-control border border-line bg-surface-1"
          >
            {items.map((product) => (
              <li key={product.id}>
                <button
                  type="button"
                  onClick={() => {
                    onChange(product.id);
                  }}
                  className="flex min-h-control w-full items-center gap-2 px-3 text-left text-body-md hover:bg-surface-2"
                >
                  <span className="text-data tnum">{product.sku}</span>
                  <span className="truncate text-fg-secondary">{product.name}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
