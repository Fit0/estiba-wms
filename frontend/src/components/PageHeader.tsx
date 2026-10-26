import type { ReactNode } from 'react';

/**
 * The title of a page, and a line under it that says what it is for.
 */
export function PageHeader({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <header className="mb-gutter-lg grid gap-1">
      <h1 className="text-headline-lg-mobile lg:text-headline-lg">{title}</h1>
      {children !== undefined && <p className="text-body-lg text-fg-secondary">{children}</p>}
    </header>
  );
}
