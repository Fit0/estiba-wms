import { Boxes, PackageCheck, TriangleAlert } from 'lucide-react';
import { useId, useState, type ReactNode } from 'react';
import { Link } from 'react-router';
import { BrandMark } from '../components/BrandMark';
import { Button } from '../components/Button';
import { DataTable, type Column, type Sort } from '../components/DataTable';
import { DensityToggle } from '../components/DensityToggle';
import { FilterTabs, type FilterOption } from '../components/FilterTabs';
import { KpiCard } from '../components/KpiCard';
import { ScannerInput } from '../components/ScannerInput';
import { StatusBadge, type Tone } from '../components/StatusBadge';
import { ThemeToggle } from '../components/ThemeToggle';
import { useTranslation, type MessageKey } from '../i18n';

type StockState = 'ok' | 'low' | 'out' | 'receiving' | 'quarantine';

interface Sample {
  readonly sku: string;
  readonly description: string;
  readonly location: string;
  readonly onHand: number;
  readonly available: number;
  readonly status: StockState;
}

// Sample data, as a person would type it: it is not translated.
const samples: readonly Sample[] = [
  {
    sku: 'MM-1001',
    description: 'Camiseta de lino, blanca, M',
    location: 'A-03-02',
    onHand: 850,
    available: 720,
    status: 'ok',
  },
  {
    sku: 'MM-1002',
    description: 'Camiseta de lino, blanca, L',
    location: 'A-03-03',
    onHand: 42,
    available: 12,
    status: 'low',
  },
  {
    sku: 'MM-2040',
    description: 'Vaquero recto, azul, 40',
    location: 'B-01-01',
    onHand: 1450,
    available: 1390,
    status: 'ok',
  },
  {
    sku: 'MM-3310',
    description: 'Zapatilla de lona, negra, 42',
    location: 'C-02-04',
    onHand: 0,
    available: 0,
    status: 'out',
  },
  {
    sku: 'MM-4015',
    description: 'Chaqueta de punto, gris, S',
    location: 'DOCK-02',
    onHand: 320,
    available: 0,
    status: 'receiving',
  },
  {
    sku: 'MM-5120',
    description: 'Bufanda de lana, granate',
    location: 'Q-01-01',
    onHand: 24,
    available: 0,
    status: 'quarantine',
  },
];

const tones: Record<StockState, Tone> = {
  ok: 'ok',
  low: 'warn',
  out: 'bad',
  receiving: 'info',
  quarantine: 'neutral',
};

const statusNames: Record<StockState, MessageKey> = {
  ok: 'design.status.ok',
  low: 'design.status.low',
  out: 'design.status.out',
  receiving: 'design.status.receiving',
  quarantine: 'design.status.quarantine',
};

// Each swatch spells its class out, so that Tailwind can find it.
const swatches: readonly { name: string; className: string }[] = [
  { name: 'canvas', className: 'bg-canvas' },
  { name: 'surface-1', className: 'bg-surface-1' },
  { name: 'surface-2', className: 'bg-surface-2' },
  { name: 'surface-3', className: 'bg-surface-3' },
  { name: 'fg', className: 'bg-fg' },
  { name: 'fg-secondary', className: 'bg-fg-secondary' },
  { name: 'fg-muted', className: 'bg-fg-muted' },
  { name: 'primary', className: 'bg-primary' },
  { name: 'accent', className: 'bg-accent' },
  { name: 'ok', className: 'bg-ok' },
  { name: 'warn', className: 'bg-warn' },
  { name: 'bad', className: 'bg-bad' },
  { name: 'info', className: 'bg-info' },
];

function Section({ title, children }: { title: string; children: ReactNode }) {
  const id = useId();

  return (
    <section aria-labelledby={id} className="grid gap-gutter">
      <h2 id={id} className="text-headline-md">
        {title}
      </h2>
      {children}
    </section>
  );
}

function compare(a: Sample, b: Sample, key: string): number {
  const x = key in a ? a[key as keyof Sample] : '';
  const y = key in b ? b[key as keyof Sample] : '';

  return typeof x === 'number' && typeof y === 'number'
    ? x - y
    : String(x).localeCompare(String(y));
}

/**
 * Every component of the design system, in the theme that is on, with the controls to change it. It
 * needs no sign-in and no data, so anyone can look at the design.
 */
export function DesignPage() {
  const { t, language } = useTranslation();
  const [filter, setFilter] = useState<'all' | StockState>('all');
  const [sort, setSort] = useState<Sort | null>(null);
  const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());
  const [lastCode, setLastCode] = useState<string | null>(null);

  const percent = new Intl.NumberFormat(language, {
    style: 'percent',
    minimumFractionDigits: 1,
    signDisplay: 'always',
  });
  const count = new Intl.NumberFormat(language);

  const filters: FilterOption<'all' | StockState>[] = [
    { value: 'all', label: t('design.filter.all'), count: samples.length },
    ...(['ok', 'low', 'out'] as const).map((state) => ({
      value: state,
      label: t(statusNames[state]),
      count: samples.filter((sample) => sample.status === state).length,
    })),
  ];

  const visible = samples
    .filter((sample) => filter === 'all' || sample.status === filter)
    .toSorted((a, b) =>
      sort === null ? 0 : compare(a, b, sort.key) * (sort.direction === 'asc' ? 1 : -1),
    );

  const columns: Column<Sample>[] = [
    { key: 'sku', header: t('design.col.sku'), cell: (row) => row.sku, code: true, sortable: true },
    {
      key: 'description',
      header: t('design.col.description'),
      cell: (row) => row.description,
      sortable: true,
    },
    { key: 'location', header: t('design.col.location'), cell: (row) => row.location, code: true },
    {
      key: 'onHand',
      header: t('design.col.onHand'),
      cell: (row) => count.format(row.onHand),
      numeric: true,
      sortable: true,
    },
    {
      key: 'available',
      header: t('design.col.available'),
      cell: (row) => count.format(row.available),
      numeric: true,
      sortable: true,
    },
    {
      key: 'status',
      header: t('design.col.status'),
      cell: (row) => (
        <StatusBadge tone={tones[row.status]} live={row.status === 'receiving'}>
          {t(statusNames[row.status])}
        </StatusBadge>
      ),
    },
  ];

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 flex h-14 items-center gap-gutter border-b border-line bg-surface-1 px-margin">
        <BrandMark />
        <div className="ml-auto flex items-center gap-2">
          <DensityToggle />
          <ThemeToggle />
          <Link to="/" className="text-body-md font-medium text-accent underline">
            {t('design.back')}
          </Link>
        </div>
      </header>
      <main className="mx-auto grid max-w-6xl gap-gutter-lg p-margin lg:p-margin-lg">
        <div className="grid gap-1">
          <h1 className="text-headline-lg-mobile lg:text-headline-lg">{t('design.title')}</h1>
          <p className="text-body-lg text-fg-secondary">{t('design.intro')}</p>
        </div>

        <Section title={t('design.colors')}>
          <ul className="grid grid-cols-2 gap-gutter sm:grid-cols-4 lg:grid-cols-6">
            {swatches.map(({ name, className }) => (
              <li key={name} className="grid gap-1">
                <span className={`h-10 rounded-control border border-line-strong ${className}`} />
                <span className="tnum text-label-sm text-fg-secondary">{name}</span>
              </li>
            ))}
          </ul>
        </Section>

        <Section title={t('design.typography')}>
          <div className="grid gap-2 rounded-panel border border-line bg-surface-1 p-4">
            <p className="font-display text-display">{t('design.sample.heading')}</p>
            <p className="font-display text-headline-lg">{t('design.sample.heading')}</p>
            <p className="font-display text-headline-md">{t('design.sample.heading')}</p>
            <p className="font-display text-headline-sm">{t('design.sample.heading')}</p>
            <p className="text-body-lg">{t('design.sample.body')}</p>
            <p className="text-body-md">{t('design.sample.body')}</p>
            <p className="text-body-sm text-fg-secondary">{t('design.sample.body')}</p>
            <p className="tnum text-data">MM-1001 · A-03-02 · 1.450,00</p>
            <p className="text-label-sm text-fg-muted uppercase">{t('design.sample.label')}</p>
          </div>
        </Section>

        <Section title={t('design.buttons')}>
          <div className="flex flex-wrap gap-2">
            <Button variant="primary">{t('design.button.primary')}</Button>
            <Button variant="secondary">{t('design.button.secondary')}</Button>
            <Button variant="ghost">{t('design.button.ghost')}</Button>
            <Button variant="primary" disabled>
              {t('design.button.disabled')}
            </Button>
          </div>
        </Section>

        <Section title={t('design.badges')}>
          <div className="flex flex-wrap gap-2">
            {(['ok', 'low', 'out', 'receiving', 'quarantine'] as const).map((state) => (
              <StatusBadge key={state} tone={tones[state]} live={state === 'receiving'}>
                {t(statusNames[state])}
              </StatusBadge>
            ))}
          </div>
        </Section>

        <Section title={t('design.kpis')}>
          <div className="grid gap-gutter sm:grid-cols-3">
            <KpiCard
              label={t('design.kpi.skus')}
              value={count.format(14280)}
              icon={Boxes}
              trend={{
                direction: 'up',
                label: t('design.kpi.versus', { change: percent.format(0.024) }),
                tone: 'ok',
              }}
            />
            <KpiCard
              label={t('design.kpi.critical')}
              value={count.format(18)}
              icon={TriangleAlert}
              trend={{
                direction: 'up',
                label: t('design.kpi.versus', { change: percent.format(0.12) }),
                tone: 'bad',
              }}
            />
            <KpiCard
              label={t('design.kpi.inbound')}
              value={count.format(340)}
              icon={PackageCheck}
              trend={{
                direction: 'flat',
                label: t('design.kpi.versus', { change: percent.format(0) }),
              }}
            />
          </div>
        </Section>

        <Section title={t('design.scanner')}>
          <div className="grid max-w-md gap-2">
            <ScannerInput label={t('design.scanner.label')} onScan={setLastCode} />
            <p className="text-body-md text-fg-secondary">
              {lastCode === null
                ? t('design.scanner.none')
                : t('design.scanner.last', { code: lastCode })}
            </p>
          </div>
        </Section>

        <Section title={t('design.table')}>
          <FilterTabs
            label={t('design.filter.label')}
            options={filters}
            value={filter}
            onChange={setFilter}
          />
          <DataTable
            caption={t('design.table.caption')}
            columns={columns}
            rows={visible}
            rowKey={(row) => row.sku}
            sort={sort}
            onSortChange={setSort}
            selection={{
              selected,
              onChange: setSelected,
              label: (row) => row.sku,
            }}
            emptyText={t('design.table.empty')}
            className="max-h-96"
          />
        </Section>
      </main>
    </div>
  );
}
