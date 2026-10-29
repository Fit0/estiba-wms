import type { ReactNode } from 'react';

/** A list of facts as terms and values: what a record is made of, read by a screen reader as pairs. */
export function Facts({ children }: { children: ReactNode }) {
  return <dl className="grid gap-x-gutter gap-y-3 sm:grid-cols-2 lg:grid-cols-3">{children}</dl>;
}

export function Fact({ term, children }: { term: string; children: ReactNode }) {
  return (
    <div className="grid gap-0.5">
      <dt className="text-label-md text-fg-secondary">{term}</dt>
      <dd className="text-body-md text-fg">{children}</dd>
    </div>
  );
}
