import { Filter } from 'lucide-react';
import { useState, type SyntheticEvent } from 'react';
import { Link, useSearchParams } from 'react-router';
import { useAuditLog, type AuditEntry, type AuditSearch } from '../api/audit';
import { describeError } from '../api/describeError';
import {
  describeData,
  describeSubject,
  eventTypes,
  knownEventType,
  momentForApi,
  shortId,
} from '../audit/display';
import { Button } from '../components/Button';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { TextField } from '../components/TextField';
import { Select } from '../components/ui/Select';
import { useTranslation } from '../i18n';
import { formatDateTime } from '../i18n/format';

const pageSizes = [10, 30, 100] as const;

function positiveInt(value: string | null, fallback: number): number {
  const number = Number(value);

  return Number.isInteger(number) && number >= 1 ? number : fallback;
}

/**
 * The audit trail (ADR 0018), for supervisors and admins (`security.yaml`): every domain event of the
 * tenant, the latest first. The filters, the page and its size live in the address (ADR 0031), so a
 * search can be shared. A subject can be picked from a row to see the story of that one object.
 */
export function AuditPage() {
  const { t, language } = useTranslation();
  const [params, setParams] = useSearchParams();

  const eventType = knownEventType(params.get('eventType'));
  const subjectId = params.get('subjectId') ?? '';
  const from = params.get('from') ?? '';
  const until = params.get('until') ?? '';
  const page = positiveInt(params.get('page'), 1);
  const requestedSize = positiveInt(params.get('size'), pageSizes[1]);
  const pageSize = pageSizes.find((size) => size === requestedSize) ?? pageSizes[1];
  const filtered = eventType !== undefined || subjectId !== '' || from !== '' || until !== '';

  const [draft, setDraft] = useState({ subjectId, from, until });

  function change(values: Record<string, string | undefined>): void {
    setParams(
      (current) => {
        const next = new URLSearchParams(current);
        for (const [key, value] of Object.entries(values)) {
          if (value === undefined || value === '') {
            next.delete(key);
          } else {
            next.set(key, value);
          }
        }

        return next;
      },
      { replace: true },
    );
  }

  function apply(event: SyntheticEvent): void {
    event.preventDefault();
    change({ ...draft, page: undefined });
  }

  function clear(): void {
    setDraft({ subjectId: '', from: '', until: '' });
    setParams({}, { replace: true });
  }

  function showSubject(id: string): void {
    setDraft((current) => ({ ...current, subjectId: id }));
    change({ subjectId: id, page: undefined });
  }

  const fromMoment = momentForApi(from);
  const untilMoment = momentForApi(until);
  const search: AuditSearch = {
    page,
    itemsPerPage: pageSize,
    ...(eventType === undefined ? {} : { eventType }),
    ...(subjectId === '' ? {} : { subjectId }),
    ...(fromMoment === undefined ? {} : { from: fromMoment }),
    ...(untilMoment === undefined ? {} : { until: untilMoment }),
  };
  const entries = useAuditLog(search);

  const columns: Column<AuditEntry>[] = [
    {
      id: 'when',
      header: t('audit.column.when'),
      cell: (entry) => formatDateTime(language, entry.occurredAt),
    },
    {
      id: 'event',
      header: t('audit.column.event'),
      cell: (entry) => {
        const type = knownEventType(entry.eventType);

        return type === undefined ? entry.eventType : t(`audit.event.${type}`);
      },
    },
    {
      id: 'actor',
      header: t('audit.column.actor'),
      kind: 'code',
      cell: (entry) => {
        if (entry.actorId === undefined || entry.actorId === null) {
          return t('audit.system');
        }
        if (entry.actorEmail === undefined || entry.actorEmail === null) {
          return (
            <span title={entry.actorId}>
              {t('audit.unknownUser', { id: shortId(entry.actorId) })}
            </span>
          );
        }

        return entry.actorEmail;
      },
    },
    {
      id: 'subject',
      header: t('audit.column.subject'),
      cell: (entry) => <SubjectCell entry={entry} onPick={showSubject} />,
    },
    {
      id: 'data',
      header: t('audit.column.data'),
      cell: (entry) => (
        <details>
          <summary className="inline-flex min-h-control cursor-pointer items-center text-accent">
            {t('audit.details')}
          </summary>
          <pre className="max-w-xl overflow-x-auto rounded-control bg-surface-2 p-2 text-data">
            {describeData(entry.data)}
          </pre>
        </details>
      ),
    },
  ];

  return (
    <>
      <PageHeader title={t('audit.title')}>{t('audit.intro')}</PageHeader>

      <form onSubmit={apply} className="mb-gutter flex flex-wrap items-end gap-2">
        <Select
          label={t('audit.filter.event')}
          value={eventType ?? 'all'}
          onValueChange={(value) => {
            change({ eventType: knownEventType(value), page: undefined });
          }}
          options={[
            { value: 'all', label: t('audit.filter.all') },
            ...eventTypes.map((type) => ({ value: type, label: t(`audit.event.${type}`) })),
          ]}
        />
        <TextField
          label={t('audit.filter.subject')}
          value={draft.subjectId}
          onChange={(event) => {
            setDraft({ ...draft, subjectId: event.target.value.trim() });
          }}
        />
        <TextField
          label={t('audit.filter.from')}
          type="datetime-local"
          value={draft.from}
          onChange={(event) => {
            setDraft({ ...draft, from: event.target.value });
          }}
        />
        <TextField
          label={t('audit.filter.until')}
          type="datetime-local"
          value={draft.until}
          onChange={(event) => {
            setDraft({ ...draft, until: event.target.value });
          }}
        />
        <Button type="submit" variant="primary">
          {t('audit.filter.apply')}
        </Button>
        {filtered && <Button onClick={clear}>{t('audit.filter.clear')}</Button>}
      </form>

      <DataTable
        caption={t('audit.caption')}
        columns={columns}
        rows={entries.data?.items ?? []}
        rowKey={(entry) => entry.id}
        status={entries.isPending ? 'loading' : entries.isError ? 'error' : 'ready'}
        empty={filtered ? t('audit.empty.filtered') : t('audit.empty')}
        errorMessage={`${t('table.error')} (${describeError(entries.error)})`}
        onRetry={() => void entries.refetch()}
        pagination={{
          page,
          pageSize,
          total: entries.data?.total ?? 0,
          onPageChange: (next) => {
            change({ page: String(next) });
          },
          onPageSizeChange: (size) => {
            change({ size: String(size), page: undefined });
          },
          pageSizes,
        }}
      />
    </>
  );
}

/**
 * What the entry is about: "Order PED-0001" when the event says the reference and "Wave 5d3c1f0a"
 * when it does not, a link to the screen of the object when there is one, and a button that narrows the
 * log to the story of that object.
 */
function SubjectCell({ entry, onPick }: { entry: AuditEntry; onPick: (id: string) => void }) {
  const { t } = useTranslation();
  const subject = describeSubject(entry);
  if (subject === undefined) {
    return null;
  }
  const text =
    subject.kind === undefined
      ? subject.label
      : `${t(`audit.subject.kind.${subject.kind}`)} ${subject.label}`;
  const id = subject.id;

  return (
    <span className="inline-flex items-center gap-1">
      {subject.path === null ? (
        <span title={id ?? undefined}>{text}</span>
      ) : (
        <Link
          to={subject.path}
          title={id ?? undefined}
          className="inline-flex min-h-control items-center text-accent underline-offset-2 hover:underline"
        >
          {text}
        </Link>
      )}
      {id !== null && (
        <Button
          variant="ghost"
          icon
          aria-label={t('audit.subject.pick', { subject: text })}
          onClick={() => {
            onPick(id);
          }}
        >
          <Filter aria-hidden="true" className="size-4" />
        </Button>
      )}
    </span>
  );
}
