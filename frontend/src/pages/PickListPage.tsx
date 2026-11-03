import { ArrowLeft } from 'lucide-react';
import { Link, useParams } from 'react-router';
import { describeError } from '../api/describeError';
import { nextStop, pickLines, usePickList, useStartPickList } from '../api/picking';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { failureText } from '../components/FormDialog';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { useTranslation } from '../i18n';
import { formatNumber } from '../i18n/format';
import { PickStopForm } from '../picking/PickStopForm';
import { pickListTone, waveName } from '../waves/display';

function BackLink() {
  const { t } = useTranslation();

  return (
    <Link
      to="/picking"
      className="mb-gutter inline-flex h-control items-center gap-2 text-body-md text-accent"
    >
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('picking.back')}
    </Link>
  );
}

/**
 * One pick list on the phone of the operator: start it, and then walk it stop by stop. Which step is
 * on screen follows the list: not started, being walked by this operator, being walked by someone else,
 * completed or cancelled.
 */
export function PickListPage() {
  const { id = '' } = useParams();
  const { session } = useAuth();
  const { t, language } = useTranslation();
  const list = usePickList(id);
  const start = useStartPickList(id);

  if (session.status !== 'signedIn') {
    return null;
  }
  if (list.isPending) {
    return (
      <p role="status" className="text-body-md text-fg-secondary">
        {t('common.loading')}
      </p>
    );
  }
  if (list.isError) {
    return (
      <>
        <BackLink />
        <PageHeader title={t('picking.notFound')}>{describeError(list.error)}</PageHeader>
      </>
    );
  }

  const data = list.data;
  const number = (value: number): string => formatNumber(language, value);
  const lines = pickLines(data);
  const stop = nextStop(lines);
  const mine = data.operatorId === session.user.id;
  const short = lines.filter((line) => line.confirmed && line.quantityPicked < line.quantityToPick);

  return (
    <div className="grid gap-gutter-lg">
      <div>
        <BackLink />
        <PageHeader title={t('picking.list', { name: waveName(data.id) })}>
          <StatusBadge tone={pickListTone[data.status]} dot>
            {t(`pickList.status.${data.status}`)}
          </StatusBadge>{' '}
          <span className="tnum">
            {t('picking.units', {
              picked: number(data.unitsPicked),
              total: number(data.unitsToPick),
            })}
          </span>
        </PageHeader>
      </div>

      {data.status === 'generated' && (
        <section aria-label={t('picking.start.title')} className="grid gap-gutter">
          <p className="text-body-lg">
            {t('picking.start.summary', {
              stops: number(lines.length),
              units: number(data.unitsToPick),
            })}
          </p>
          {start.isError && (
            <p role="alert" className="text-body-md text-bad">
              {failureText(start.error, t)}
            </p>
          )}
          <Button
            variant="primary"
            disabled={start.isPending}
            onClick={() => {
              start.mutate(undefined);
            }}
          >
            {start.isPending ? t('form.saving') : t('picking.start')}
          </Button>
        </section>
      )}

      {data.status === 'in_progress' && !mine && (
        <p role="status" className="text-body-lg">
          {t('picking.other')}
        </p>
      )}

      {data.status === 'in_progress' && mine && stop !== undefined && (
        <PickStopForm
          key={stop.position}
          list={data}
          line={stop}
          index={lines.indexOf(stop) + 1}
          total={lines.length}
        />
      )}

      {data.status === 'completed' && (
        <section aria-label={t('picking.done.title')} className="grid gap-gutter">
          <p role="status" className="text-body-lg">
            {t('picking.done')}
          </p>
          {short.length > 0 && (
            <p className="text-body-md text-warn">
              {t('picking.done.short', { stops: number(short.length) })}
            </p>
          )}
          <Link
            to="/picking"
            className="flex h-control items-center justify-center rounded-control bg-primary text-body-md font-semibold text-on-primary hover:bg-primary-hover"
          >
            {t('picking.back')}
          </Link>
        </section>
      )}

      {data.status === 'cancelled' && (
        <p role="status" className="text-body-lg">
          {t('picking.cancelled')}
        </p>
      )}
    </div>
  );
}
