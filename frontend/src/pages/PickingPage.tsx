import { describeError } from '../api/describeError';
import { useFloorPickLists } from '../api/picking';
import type { PickList } from '../api/waves';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { useTranslation } from '../i18n';
import { formatNumber } from '../i18n/format';
import { pickListTone, waveName } from '../waves/display';

function PickListCard({ list }: { list: PickList }) {
  const { t, language } = useTranslation();
  const number = (value: number) => formatNumber(language, value);
  const share =
    list.unitsToPick === 0 ? 0 : Math.round((list.unitsPicked / list.unitsToPick) * 100);

  return (
    <li className="grid gap-2 rounded-panel border border-line bg-surface-1 p-gutter">
      <div className="flex items-center justify-between gap-2">
        <span className="font-display text-headline-sm">
          {t('picking.list', { name: waveName(list.id) })}
        </span>
        <StatusBadge tone={pickListTone[list.status]} dot>
          {t(`pickList.status.${list.status}`)}
        </StatusBadge>
      </div>
      <p className="text-body-md text-fg-secondary tnum">
        {t('picking.units', { picked: number(list.unitsPicked), total: number(list.unitsToPick) })}
      </p>
      <div aria-hidden="true" className="h-2 overflow-hidden rounded-control bg-surface-3">
        <div className="h-full bg-primary" style={{ width: `${String(share)}%` }} />
      </div>
    </li>
  );
}

function Section({
  id,
  title,
  empty,
  lists,
}: {
  id: string;
  title: string;
  empty: string;
  lists: PickList[];
}) {
  return (
    <section aria-labelledby={id} className="grid gap-gutter">
      <h2 id={id} className="text-headline-md">
        {title}
      </h2>
      {lists.length === 0 ? (
        <p className="text-body-md text-fg-secondary">{empty}</p>
      ) : (
        <ul className="grid gap-gutter">
          {lists.map((list) => (
            <PickListCard key={list.id} list={list} />
          ))}
        </ul>
      )}
    </section>
  );
}

/**
 * The picking home of an operator: the lists assigned to them, and the ones nobody has taken. It only
 * reads; the guided picking of a list arrives with the scanner (E07-02).
 */
export function PickingPage() {
  const { session } = useAuth();
  const { t } = useTranslation();
  const lists = useFloorPickLists();

  if (session.status !== 'signedIn') {
    return null;
  }

  const userId = session.user.id;
  const mine = (lists.data ?? []).filter((list) => list.operatorId === userId);
  const free = (lists.data ?? []).filter(
    (list) => list.operatorId === null || list.operatorId === undefined,
  );

  return (
    <div className="grid gap-gutter-lg">
      <PageHeader title={t('picking.title')}>{t('picking.intro')}</PageHeader>
      {lists.isError ? (
        <div role="alert" className="grid gap-2 text-body-md text-bad">
          <p>{`${t('picking.error')} (${describeError(lists.error)})`}</p>
          <Button
            onClick={() => {
              void lists.refetch();
            }}
          >
            {t('common.retry')}
          </Button>
        </div>
      ) : lists.isPending ? (
        <p role="status" className="text-body-md text-fg-secondary">
          {t('common.loading')}
        </p>
      ) : (
        <>
          <Section
            id="picking-mine"
            title={t('picking.mine')}
            empty={t('picking.mine.empty')}
            lists={mine}
          />
          <Section
            id="picking-free"
            title={t('picking.free')}
            empty={t('picking.free.empty')}
            lists={free}
          />
        </>
      )}
    </div>
  );
}
