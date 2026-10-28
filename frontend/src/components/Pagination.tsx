import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useId } from 'react';
import { formatNumber } from '../i18n/format';
import { useTranslation } from '../i18n';
import { Button } from './Button';

export type PageItem = number | 'gap';

/**
 * The page buttons to show: always the first and the last, the current one with its neighbours, and a
 * gap where pages are left out. Seven slots at most, so the bar does not jump while paging.
 */
export function pageWindow(page: number, pageCount: number): PageItem[] {
  if (pageCount <= 7) {
    return Array.from({ length: pageCount }, (_, index) => index + 1);
  }

  if (page <= 4) {
    return [1, 2, 3, 4, 5, 'gap', pageCount];
  }

  if (page >= pageCount - 3) {
    return [1, 'gap', pageCount - 4, pageCount - 3, pageCount - 2, pageCount - 1, pageCount];
  }

  return [1, 'gap', page - 1, page, page + 1, 'gap', pageCount];
}

/**
 * Page controls for a list the server pages. It owns no state: `page` counts from 1, and the caller
 * decides what a change means (usually a new request).
 */
export function Pagination({
  page,
  pageSize,
  total,
  onPageChange,
  onPageSizeChange,
  pageSizes = [10, 25, 50],
}: {
  page: number;
  pageSize: number;
  total: number;
  onPageChange: (page: number) => void;
  onPageSizeChange?: (pageSize: number) => void;
  pageSizes?: readonly number[];
}) {
  const { t, language } = useTranslation();
  const selectId = useId();
  const pageCount = Math.max(1, Math.ceil(total / pageSize));
  const current = Math.min(Math.max(page, 1), pageCount);
  const from = total === 0 ? 0 : (current - 1) * pageSize + 1;
  const to = Math.min(current * pageSize, total);

  return (
    <nav
      aria-label={t('pagination.label')}
      className="flex flex-wrap items-center justify-between gap-gutter px-3 py-2 text-body-md text-fg-secondary"
    >
      <p aria-live="polite" className="tnum">
        {total === 0
          ? t('pagination.none')
          : t('pagination.summary', {
              from: formatNumber(language, from),
              to: formatNumber(language, to),
              total: formatNumber(language, total),
            })}
      </p>

      <div className="flex flex-wrap items-center gap-gutter">
        {onPageSizeChange !== undefined && (
          <div className="flex items-center gap-2">
            <label htmlFor={selectId}>{t('pagination.pageSize')}</label>
            <select
              id={selectId}
              value={pageSize}
              onChange={(event) => {
                onPageSizeChange(Number(event.target.value));
              }}
              className="h-control rounded-control border border-input-border bg-surface-1 px-2 text-fg"
            >
              {pageSizes.map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        )}

        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            icon
            aria-label={t('pagination.previous')}
            disabled={current <= 1}
            onClick={() => {
              onPageChange(current - 1);
            }}
          >
            <ChevronLeft aria-hidden="true" className="size-4" />
          </Button>

          {pageWindow(current, pageCount).map((item, index) =>
            item === 'gap' ? (
              <span key={`gap-${String(index)}`} aria-hidden="true" className="px-1">
                …
              </span>
            ) : (
              <Button
                key={item}
                variant={item === current ? 'primary' : 'ghost'}
                icon
                aria-label={t('pagination.page', { page: String(item) })}
                aria-current={item === current ? 'page' : undefined}
                onClick={() => {
                  onPageChange(item);
                }}
              >
                <span className="tnum">{item}</span>
              </Button>
            ),
          )}

          <Button
            variant="ghost"
            icon
            aria-label={t('pagination.next')}
            disabled={current >= pageCount}
            onClick={() => {
              onPageChange(current + 1);
            }}
          >
            <ChevronRight aria-hidden="true" className="size-4" />
          </Button>
        </div>
      </div>
    </nav>
  );
}
