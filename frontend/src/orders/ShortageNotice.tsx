import { Link } from 'react-router';
import type { OrderShortage } from '../api/orders';
import type { Product } from '../api/products';
import { StatusBadge } from '../components/StatusBadge';
import { useTranslation } from '../i18n';
import { formatNumber } from '../i18n/format';
import { productLabel } from '../inbound/display';
import { parseObstacles, type ShortageObstacle } from './schemas';

/**
 * Why the lines of an order that are short have not been reserved: what there is of the product and
 * what keeps it from being used. It replaces a silent `0` with a sentence a person can act on, such as
 * "there are 40 units, but they are at a dock and not put away". The API says the reason as a code and
 * the units, and this writes it in the language that is on (ADR 0058).
 */
export function ShortageNotice({
  shortages,
  products,
}: {
  shortages: readonly OrderShortage[];
  products: ReadonlyMap<string, Product>;
}) {
  const { t, language } = useTranslation();

  if (shortages.length === 0) {
    return null;
  }

  function sentence(obstacle: ShortageObstacle): string {
    return t(`shortage.reason.${obstacle.reason}`, {
      units: formatNumber(language, obstacle.units),
      codes: obstacle.locationCodes.join(', '),
    });
  }

  return (
    <section aria-labelledby="order-shortage-title" className="grid gap-gutter">
      <h2 id="order-shortage-title" className="text-headline-md">
        {t('shortage.title')}
      </h2>
      <p className="text-body-md text-fg-secondary">{t('shortage.intro')}</p>
      <ul className="grid gap-gutter">
        {shortages.map((shortage) => {
          const obstacles = parseObstacles(shortage.obstacles);

          return (
            <li
              key={shortage.lineNumber}
              className="grid gap-2 rounded-panel border border-line bg-surface-1 p-4"
            >
              <p className="flex flex-wrap items-center gap-2 text-body-md font-semibold text-fg">
                {productLabel(products, shortage.productId)}
                <StatusBadge tone="warn">
                  {t('shortage.missing', {
                    units: formatNumber(language, shortage.shortageUnits),
                  })}
                </StatusBadge>
              </p>
              {obstacles.length === 0 ? (
                <p className="text-body-md text-fg">{t('shortage.noStock')}</p>
              ) : (
                <ul className="grid list-disc gap-1 pl-5 text-body-md text-fg">
                  {obstacles.map((obstacle) => (
                    <li key={obstacle.reason}>
                      {sentence(obstacle)}
                      {obstacle.reason === 'at_dock' && (
                        <>
                          {' '}
                          <Link
                            to="/putaway"
                            className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
                          >
                            {t('shortage.putaway')}
                          </Link>
                        </>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </li>
          );
        })}
      </ul>
    </section>
  );
}
