import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { useTranslation } from '../i18n';

export type SortDirection = 'asc' | 'desc';

export interface Sort {
  readonly key: string;
  readonly direction: SortDirection;
}

export interface Column<Row> {
  readonly key: string;
  readonly header: string;
  readonly cell: (row: Row) => ReactNode;
  /** A quantity: right aligned, with tabular figures. */
  readonly numeric?: boolean;
  /** A code (SKU, location): medium weight with tabular figures. */
  readonly code?: boolean;
  readonly sortable?: boolean;
}

export interface Selection<Row> {
  readonly selected: ReadonlySet<string>;
  readonly onChange: (selected: Set<string>) => void;
  /** What a screen reader says for the checkbox of a row: its SKU, its code. */
  readonly label: (row: Row) => string;
}

/**
 * Cycles the sort of a column: ascending, then descending, then none. Another column starts again.
 */
export function nextSort(current: Sort | null, key: string): Sort | null {
  if (current?.key !== key) {
    return { key, direction: 'asc' };
  }

  return current.direction === 'asc' ? { key, direction: 'desc' } : null;
}

function ariaSort(sort: Sort | null | undefined, key: string): 'ascending' | 'descending' | 'none' {
  if (sort?.key !== key) {
    return 'none';
  }

  return sort.direction === 'asc' ? 'ascending' : 'descending';
}

const cellBase = 'px-3 text-left text-body-md';

/**
 * The grid of the system (DESIGN.md): a header that stays while the rows scroll, rows as tall as the
 * density asks, figures right aligned with tabular numbers, and a bar on the edge of the row that is
 * under the pointer or selected. It shows the rows it is given: sorting and filtering are the job of
 * whoever has the data, which for a long list is the server.
 */
export function DataTable<Row>({
  caption,
  columns,
  rows,
  rowKey,
  sort,
  onSortChange,
  selection,
  emptyText,
  className = '',
}: {
  caption: string;
  columns: readonly Column<Row>[];
  rows: readonly Row[];
  rowKey: (row: Row) => string;
  sort?: Sort | null;
  onSortChange?: (sort: Sort | null) => void;
  selection?: Selection<Row>;
  emptyText: string;
  /** For the box that scrolls, such as `max-h-96`. */
  className?: string;
}) {
  const { t } = useTranslation();
  const selectAll = useRef<HTMLInputElement>(null);
  const keys = rows.map(rowKey);
  const chosen = keys.filter((key) => selection?.selected.has(key) === true).length;
  const everyRow = rows.length > 0 && chosen === rows.length;

  useEffect(() => {
    if (selectAll.current !== null) {
      selectAll.current.indeterminate = chosen > 0 && !everyRow;
    }
  }, [chosen, everyRow]);

  function toggleAll(): void {
    selection?.onChange(everyRow ? new Set() : new Set(keys));
  }

  function toggle(key: string): void {
    if (selection === undefined) {
      return;
    }
    const next = new Set(selection.selected);
    if (next.has(key)) {
      next.delete(key);
    } else {
      next.add(key);
    }
    selection.onChange(next);
  }

  const columnCount = columns.length + (selection === undefined ? 0 : 1);

  return (
    <div className={`overflow-auto rounded-panel border border-line bg-surface-1 ${className}`}>
      <table className="w-full border-collapse">
        <caption className="sr-only">{caption}</caption>
        <thead>
          <tr>
            {selection !== undefined && (
              <th
                scope="col"
                className="sticky top-0 z-[1] h-control w-10 border-b border-line bg-surface-2 px-3"
              >
                <input
                  ref={selectAll}
                  type="checkbox"
                  className="size-4 accent-primary"
                  aria-label={t('table.selectAll')}
                  checked={everyRow}
                  onChange={toggleAll}
                />
              </th>
            )}
            {columns.map((column) => {
              const direction = ariaSort(sort, column.key);
              const SortIcon =
                direction === 'ascending'
                  ? ArrowUp
                  : direction === 'descending'
                    ? ArrowDown
                    : ChevronsUpDown;

              return (
                <th
                  key={column.key}
                  scope="col"
                  aria-sort={column.sortable === true ? direction : undefined}
                  className={`sticky top-0 z-[1] h-control border-b border-line bg-surface-2 px-3 text-label-sm whitespace-nowrap text-fg-muted uppercase ${column.numeric === true ? 'text-right' : 'text-left'}`}
                >
                  {column.sortable === true ? (
                    <button
                      type="button"
                      onClick={() => onSortChange?.(nextSort(sort ?? null, column.key))}
                      className="inline-flex items-center gap-1 uppercase hover:text-fg"
                    >
                      {column.header}
                      <SortIcon aria-hidden="true" className="size-3" />
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
          {rows.length === 0 && (
            <tr>
              <td colSpan={columnCount} className="h-row px-3 text-center text-fg-secondary">
                {emptyText}
              </td>
            </tr>
          )}
          {rows.map((row) => {
            const key = rowKey(row);
            const isSelected = selection?.selected.has(key) === true;

            return (
              <tr
                key={key}
                data-selected={isSelected}
                className="group h-row border-b border-line last:border-b-0 hover:bg-surface-2 data-[selected=true]:bg-selected"
              >
                {selection !== undefined && (
                  <td className="relative px-3">
                    <span
                      aria-hidden="true"
                      className="absolute inset-y-0 left-0 w-0.5 bg-primary opacity-0 group-hover:opacity-100 group-data-[selected=true]:opacity-100"
                    />
                    <input
                      type="checkbox"
                      className="size-4 accent-primary"
                      aria-label={t('table.selectRow', { row: selection.label(row) })}
                      checked={isSelected}
                      onChange={() => {
                        toggle(key);
                      }}
                    />
                  </td>
                )}
                {columns.map((column, index) => (
                  <td
                    key={column.key}
                    className={`${cellBase} ${column.numeric === true ? 'tnum text-right' : ''} ${column.code === true ? 'tnum font-medium' : ''} ${index === 0 && selection === undefined ? 'relative' : ''}`}
                  >
                    {index === 0 && selection === undefined && (
                      <span
                        aria-hidden="true"
                        className="absolute inset-y-0 left-0 w-0.5 bg-primary opacity-0 group-hover:opacity-100"
                      />
                    )}
                    {column.cell(row)}
                  </td>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
