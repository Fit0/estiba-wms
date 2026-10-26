import type { ComponentProps } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost';

const variants: Record<Variant, string> = {
  primary: 'bg-primary text-on-primary hover:bg-primary-hover active:bg-primary-active',
  secondary: 'border border-line-strong bg-surface-2 text-fg hover:bg-surface-3',
  ghost: 'text-fg-secondary hover:bg-surface-2 hover:text-fg',
};

/**
 * A button of the design system (DESIGN.md): `h-control` tall, so the touch density of the PWA makes
 * it bigger without a change here. `icon` makes it a square for a button that has only an icon,
 * which must carry an `aria-label`.
 */
export function Button({
  variant = 'secondary',
  icon = false,
  className = '',
  type = 'button',
  ...props
}: ComponentProps<'button'> & { variant?: Variant; icon?: boolean }) {
  const shape = icon ? 'w-control' : 'px-3';

  return (
    <button
      type={type}
      className={`inline-flex h-control items-center justify-center gap-2 rounded-control text-body-md font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${shape} ${variants[variant]} ${className}`}
      {...props}
    />
  );
}
