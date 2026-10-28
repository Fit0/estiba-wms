import axe, { type AxeResults, type Result } from 'axe-core';

/**
 * Runs axe over a part of the page and fails with what it found, in a form that says where.
 *
 * jsdom has no layout and no painting, so two kinds of rule cannot be judged here and are left to
 * other tests: `color-contrast` (the pair of every token is measured in `styles/tokens.test.ts`) and
 * the size of targets (`styles/rules.test.ts` and ADR 0030). Everything else axe knows runs: names,
 * roles, labels, landmarks, headings, ARIA and focus order.
 */
export async function expectNoViolations(root: Element = document.body): Promise<void> {
  const results: AxeResults = await axe.run(root, {
    runOnly: {
      type: 'tag',
      values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'],
    },
    rules: { 'color-contrast': { enabled: false }, 'target-size': { enabled: false } },
  });

  if (results.violations.length > 0) {
    throw new Error(`axe found:\n${results.violations.map(describe).join('\n')}`);
  }
}

function describe(violation: Result): string {
  const where = violation.nodes.map((node) => node.target.join(' ')).join(' | ');

  return `${violation.id}: ${violation.help} (${where})`;
}
