import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { useTranslation } from '../i18n';
import { Button } from './Button';
import { Pagination } from './Pagination';

export interface Column<Row> {
  id: string;
  header: string;
  cell: (row: Row) => ReactNode;
  /** `number` aligns right with tabular figures; `code` is for SKUs and locations. */
  kind?: 'text' | 'code' | 'number';
  sortable?: boolean;
}

export interface Sort {
  columnId: string;
  direction: 'asc' | 'desc';
}

export interface Selection<Row> {
  selected: ReadonlySet<string>;
  onChange: (selected: Set<string>) => void;
  /** What a screen reader calls the row: "Select {{row}}". */
  rowLabel: (row: Row) => string;
}

export interface PaginationState {
  page: number;
  pageSize: number;
  total: number;
  onPageChange: (page: number) => void;
  onPageSizeChange?: (pageSize: number) => void;
  pageSizes?: readonly number[];
}

const cellKind = {
  text: '',
  code: 'text-data tnum',
  number: 'text-right tnum',
} as const;

const skeletonRows = 5;

function SelectBox({
  label,
  checked,
  indeterminate = false,
  onChange,
}: {
  label: string;
  checked: boolean;
  indeterminate?: boolean;
  onChange: (checked: boolean) => void;
}) {
  const ref = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (ref.current !== null) {
      ref.current.indeterminate = indeterminate;
    }
  }, [indeterminate]);

  return (
    <input
      ref={ref}
      type="checkbox"
      aria-label={label}
      checked={checked}
      onChange={(event) => {
        onChange(event.target.checked);
      }}
      className="size-4 accent-primary"
    />
  );
}

/**
 * A table of records for the back office. It is controlled and keeps no state of its own, because the
 * rows come from the server: the sort, the selection and the page are the caller's, and a change is
 * a callback. It has a state for loading, for an error with a retry, and for no rows.
 */
export function DataTable<Row>({
  columns,
  rows,
  rowKey,
  caption,
  sort,
  onSortChange,
  selection,
  status = 'ready',
  empty,
  errorMessage,
  onRetry,
  pagination,
}: {
  columns: readonly Column<Row>[];
  rows: readonly Row[];
  rowKey: (row: Row) => string;
  /** The name of the table for assistive technology. It is not drawn. */
  caption: string;
  sort?: Sort | undefined;
  onSortChange?: (sort: Sort) => void;
  selection?: Selection<Row>;
  status?: 'ready' | 'loading' | 'error';
  empty?: ReactNode;
  errorMessage?: string;
  onRetry?: () => void;
  pagination?: PaginationState;
}) {
  const { t } = useTranslation();
  const span = columns.length + (selection === undefined ? 0 : 1);
  const keys = rows.map(rowKey);
  const chosen =
    selection === undefined ? 0 : keys.filter((key) => selection.selected.has(key)).length;

  function toggleAll(checked: boolean): void {
    if (selection === undefined) {
      return;
    }

    const next = new Set(selection.selected);
    keys.forEach((key) => {
      if (checked) {
        next.add(key);
      } else {
        next.delete(key);
      }
    });
    selection.onChange(next);
  }

  function toggleRow(key: string, checked: boolean): void {
    if (selection === undefined) {
      return;
    }

    const next = new Set(selection.selected);
    if (checked) {
      next.add(key);
    } else {
      next.delete(key);
    }
    selection.onChange(next);
  }

  function sortBy(columnId: string): void {
    const direction = sort?.columnId === columnId && sort.direction === 'asc' ? 'desc' : 'asc';
    onSortChange?.({ columnId, direction });
  }

  function ariaSort(column: Column<Row>): 'ascending' | 'descending' | 'none' | undefined {
    if (column.sortable !== true) {
      return undefined;
    }

    if (sort?.columnId !== column.id) {
      return 'none';
    }

    return sort.direction === 'asc' ? 'ascending' : 'descending';
  }

  return (
    <div className="overflow-hidden rounded-panel border border-line bg-surface-1">
      <div
        role="region"
        aria-label={caption}
        // eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex -- a scroll region must be reachable by keyboard
        tabIndex={0}
        aria-busy={status === 'loading'}
        className="max-h-[32rem] overflow-auto"
      >
        <table className="w-full border-collapse text-left text-body-md">
          <caption className="sr-only">{caption}</caption>
          <thead className="sticky top-0 z-10 bg-surface-2 text-label-md text-fg-secondary">
            <tr>
              {selection !== undefined && (
                <th scope="col" className="h-row w-10 border-b border-line-strong px-3">
                  <SelectBox
                    label={t('table.selectAll')}
                    checked={rows.length > 0 && chosen === rows.length}
                    indeterminate={chosen > 0 && chosen < rows.length}
                    onChange={toggleAll}
                  />
                </th>
              )}
              {columns.map((column) => {
                const sorted = sort?.columnId === column.id;
                const Icon = !sorted
                  ? ChevronsUpDown
                  : sort.direction === 'asc'
                    ? ArrowUp
                    : ArrowDown;

                return (
                  <th
                    key={column.id}
                    scope="col"
                    aria-sort={ariaSort(column)}
                    className={`h-row whitespace-nowrap border-b border-line-strong px-3 font-medium ${column.kind === 'number' ? 'text-right' : ''}`}
                  >
                    {column.sortable === true ? (
                      <button
                        type="button"
                        aria-label={t('table.sortBy', { column: column.header })}
                        onClick={() => {
                          sortBy(column.id);
                        }}
                        className={`inline-flex items-center gap-1 rounded-control hover:text-fg ${sorted ? 'text-fg' : ''}`}
                      >
                        {column.header}
                        <Icon aria-hidden="true" className="size-3.5" />
                      </button>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>

          <tbody>
            {status === 'loading' &&
              Array.from({ length: skeletonRows }, (_, index) => (
                <tr key={index} aria-hidden="true">
                  <td colSpan={span} className="h-row border-b border-line px-3">
                    <div className="h-3 w-full animate-pulse rounded-control bg-surface-3" />
                  </td>
                </tr>
              ))}

            {status === 'error' && (
              <tr>
                <td colSpan={span} className="px-3 py-8">
                  <div role="alert" className="grid justify-items-center gap-3 text-center">
                    <p className="text-bad">{errorMessage ?? t('table.error')}</p>
                    {onRetry !== undefined && (
                      <Button onClick={onRetry}>{t('common.retry')}</Button>
                    )}
                  </div>
                </td>
              </tr>
            )}

            {status === 'ready' && rows.length === 0 && (
              <tr>
                <td colSpan={span} className="px-3 py-8 text-center text-fg-secondary">
                  {empty ?? t('table.empty')}
                </td>
              </tr>
            )}

            {status === 'ready' &&
              rows.map((row) => {
                const key = rowKey(row);
                const isSelected = selection?.selected.has(key) === true;

                return (
                  <tr
                    key={key}
                    aria-selected={selection === undefined ? undefined : isSelected}
                    className={`border-b border-line last:border-b-0 hover:bg-surface-2 ${isSelected ? 'bg-selected' : ''}`}
                  >
                    {selection !== undefined && (
                      <td className="h-row w-10 px-3">
                        <SelectBox
                          label={t('table.selectRow', { row: selection.rowLabel(row) })}
                          checked={isSelected}
                          onChange={(checked) => {
                            toggleRow(key, checked);
                          }}
                        />
                      </td>
                    )}
                    {columns.map((column) => (
                      <td
                        key={column.id}
                        className={`h-row px-3 ${cellKind[column.kind ?? 'text']}`}
                      >
                        {column.cell(row)}
                      </td>
                    ))}
                  </tr>
                );
              })}
          </tbody>
        </table>
      </div>

      {pagination !== undefined && (
        <div className="border-t border-line">
          <Pagination {...pagination} />
        </div>
      )}
    </div>
  );
}
