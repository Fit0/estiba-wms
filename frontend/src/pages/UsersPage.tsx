import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { describeError } from '../api/describeError';
import { useSetUserEnabled, useUsers, type User } from '../api/users';
import { failureText } from '../components/FormDialog';
import { isRole } from '../auth/roles';
import { useAuth } from '../auth/useAuth';
import { Button } from '../components/Button';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { DataTable, type Column } from '../components/DataTable';
import { PageHeader } from '../components/PageHeader';
import { StatusBadge } from '../components/StatusBadge';
import { toast } from '../components/ui/Toaster';
import { useTranslation } from '../i18n';
import { RolesDialog } from '../users/RolesDialog';
import { UserDialog } from '../users/UserDialog';

const pageSizes = [10, 25, 50] as const;

function positiveInt(value: string | null, fallback: number): number {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

/**
 * The users of the company, for admins. Add one, change what a user may do, and lock or unlock the
 * account. The API keeps the rules (IDN-11, IDN-12): this only hides what could never work.
 */
export function UsersPage() {
  const { t } = useTranslation();
  const { session } = useAuth();
  const [params, setParams] = useSearchParams();
  const page = positiveInt(params.get('page'), 1);
  const requestedSize = positiveInt(params.get('size'), pageSizes[1]);
  const pageSize = pageSizes.find((size) => size === requestedSize) ?? pageSizes[1];
  const users = useUsers({ page, itemsPerPage: pageSize });
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);
  const [toggling, setToggling] = useState<User | null>(null);
  const toggle = useSetUserEnabled(toggling?.id ?? '');
  const ownId = session.status === 'signedIn' ? session.user.id : null;

  const columns: Column<User>[] = [
    { id: 'email', header: t('users.email'), cell: (user) => user.email },
    {
      id: 'roles',
      header: t('users.roles'),
      cell: (user) =>
        user.roles
          .filter(isRole)
          .map((role) => t(`role.${role}`))
          .join(', '),
    },
    {
      id: 'status',
      header: t('users.status'),
      cell: (user) => (
        <StatusBadge tone={user.status === 'active' ? 'ok' : 'neutral'} dot>
          {t(user.status === 'active' ? 'users.status.active' : 'users.status.disabled')}
        </StatusBadge>
      ),
    },
    {
      id: 'actions',
      header: t('users.actions'),
      cell: (user) => (
        <span className="flex flex-wrap gap-2">
          <Button
            onClick={() => {
              setEditing(user);
            }}
            aria-label={t('users.roles.open', { email: user.email })}
          >
            {t('users.roles.action')}
          </Button>
          {user.id !== ownId && (
            <Button
              onClick={() => {
                toggle.reset();
                setToggling(user);
              }}
              aria-label={t(user.status === 'active' ? 'users.disable.open' : 'users.enable.open', {
                email: user.email,
              })}
            >
              {t(user.status === 'active' ? 'users.disable.action' : 'users.enable.action')}
            </Button>
          )}
        </span>
      ),
    },
  ];

  function change(next: Record<string, string | undefined>): void {
    const merged = new URLSearchParams(params);
    for (const [key, value] of Object.entries(next)) {
      if (value === undefined) {
        merged.delete(key);
      } else {
        merged.set(key, value);
      }
    }
    setParams(merged);
  }

  const disabling = toggling?.status === 'active';

  return (
    <>
      <PageHeader title={t('users.title')}>{t('users.intro')}</PageHeader>
      <div className="mb-gutter flex justify-end">
        <Button
          variant="primary"
          onClick={() => {
            setCreating(true);
          }}
        >
          <Plus aria-hidden="true" className="size-4" />
          {t('users.new')}
        </Button>
      </div>
      <DataTable
        caption={t('users.caption')}
        columns={columns}
        rows={users.data?.items ?? []}
        rowKey={(user) => user.id}
        status={users.isPending ? 'loading' : users.isError ? 'error' : 'ready'}
        empty={t('users.empty')}
        errorMessage={`${t('table.error')} (${describeError(users.error)})`}
        onRetry={() => void users.refetch()}
        pagination={{
          page,
          pageSize,
          total: users.data?.total ?? 0,
          onPageChange: (next) => {
            change({ page: String(next) });
          },
          onPageSizeChange: (size) => {
            change({ size: String(size), page: undefined });
          },
          pageSizes,
        }}
      />
      <UserDialog open={creating} onOpenChange={setCreating} />
      {editing !== null && (
        <RolesDialog
          key={editing.id}
          user={editing}
          onOpenChange={(open) => {
            if (!open) {
              setEditing(null);
            }
          }}
        />
      )}
      <ConfirmDialog
        open={toggling !== null}
        onOpenChange={(open) => {
          if (!open) {
            setToggling(null);
          }
        }}
        title={t(disabling ? 'users.disable.title' : 'users.enable.title', {
          email: toggling?.email ?? '',
        })}
        description={t(disabling ? 'users.disable.description' : 'users.enable.description')}
        confirmLabel={t(disabling ? 'users.disable.action' : 'users.enable.action')}
        pending={toggle.isPending}
        failure={toggle.isError ? failureText(toggle.error, t) : undefined}
        onConfirm={() => {
          if (toggling === null) {
            return;
          }
          toggle.mutate(!disabling, {
            onSuccess: () => {
              toast.success(
                t(disabling ? 'users.disable.done' : 'users.enable.done', {
                  email: toggling.email,
                }),
              );
              setToggling(null);
            },
          });
        }}
      />
    </>
  );
}
