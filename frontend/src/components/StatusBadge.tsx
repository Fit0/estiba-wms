import type { ReactNode } from 'react';

export type Tone = 'ok' | 'warn' | 'bad' | 'info' | 'neutral';

const tones: Record<Tone, string> = {
  ok: 'border-ok-line bg-ok-fill text-ok',
  warn: 'border-warn-line bg-warn-fill text-warn',
  bad: 'border-bad-line bg-bad-fill text-bad',
  info: 'border-info-line bg-info-fill text-info',
  neutral: 'border-line-strong bg-surface-2 text-fg-secondary',
};

const dots: Record<Tone, string> = {
  ok: 'bg-ok',
  warn: 'bg-warn',
  bad: 'bg-bad',
  info: 'bg-info',
  neutral: 'bg-fg-muted',
};

/**
 * A short state: in stock, low, out, receiving. Its words say it, and the color only helps. `live`
 * adds a dot that pulses for what is happening now, such as a dock that is receiving, and the dot
 * stays still for a person who asked their system for less motion.
 */
export function StatusBadge({
  tone,
  live = false,
  children,
}: {
  tone: Tone;
  live?: boolean;
  children: ReactNode;
}) {
  return (
    <span
      className={`inline-flex h-5 items-center gap-1.5 rounded-control border px-2 text-label-sm whitespace-nowrap ${tones[tone]}`}
    >
      {live && (
        <span
          aria-hidden="true"
          className={`size-1.5 rounded-full motion-safe:animate-pulse ${dots[tone]}`}
        />
      )}
      {children}
    </span>
  );
}
