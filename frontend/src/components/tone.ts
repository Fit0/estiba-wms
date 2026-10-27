/** The meaning of a status. The text always says it too: color alone never carries it (DESIGN.md). */
export type Tone = 'neutral' | 'ok' | 'warn' | 'bad' | 'info';

export const toneBadge: Record<Tone, string> = {
  neutral: 'border-line-strong bg-surface-2 text-fg-secondary',
  ok: 'border-ok-line bg-ok-fill text-ok',
  warn: 'border-warn-line bg-warn-fill text-warn',
  bad: 'border-bad-line bg-bad-fill text-bad',
  info: 'border-info-line bg-info-fill text-info',
};

export const toneText: Record<Tone, string> = {
  neutral: 'text-fg-secondary',
  ok: 'text-ok',
  warn: 'text-warn',
  bad: 'text-bad',
  info: 'text-info',
};
