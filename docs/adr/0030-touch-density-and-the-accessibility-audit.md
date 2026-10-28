# 0030. Touch density and the accessibility audit

- Status: Accepted
- Date: 2026-10-28

## Context

The same components serve two ways of working. In the office, with a mouse, a control 32 px tall lets a table show many rows. In the warehouse, with gloves and a handheld, a target under 48 px is missed. ADR 0023 left the sizes as tokens (`--control-height`, `--row-height`) and a `data-density` attribute on the root; UI-04 makes them usable and checks that the screens are accessible (WCAG 2.2 AA).

## Decisions

- **Three densities**: `compact` (32 px controls, 36 px rows; the office), `comfortable` (36 / 44) and `touch` (48 / 56; the picking PWA). They are set by `data-density` on `<html>`, like the theme, and every control reads the token: `h-control`, `size-control`, `min-h-control`, `min-w-control`, `h-row`.
- **Who chooses**: the visitor, with a button in the top bar and an action in the command palette, and the choice is kept in `localStorage` (`estiba-density`, with a fallback when the browser refuses it). Without a choice, a coarse pointer (a finger) gets `touch` and everything else `compact`. The PWA of E07 reuses the same code.
- **Nothing pressable has a fixed height of 32–44 px.** Radix items, the close button of a dialog, the checkbox of a table row (its label is the target), the links that stand alone and the palette items use the tokens, so touch enlarges them. `rules.test.ts` fails if a component writes `h-8`…`h-11`, `min-h-8`…, or `size-8`…`size-11` (the only exceptions are a list of drawn pieces that cannot be pressed: the brand mark, the icon tiles of the sign-in page and the loading skeleton of a card).
- **Text size**: no size below 11 px (only the uppercase labels use it, and they reach 4.5:1), a body of 13 px or more and a lead of 15 px or more. A test reads the scale of `index.css`; no component writes `text-[…]` by hand.
- **axe over the real screens.** `axe-core` runs in Vitest (`testing/axe.ts`, no extra wrapper) over the sign-in page in both languages, the shell in the touch density, the warehouse screens with their dialogs open and showing errors, the command palette and the component gallery, with the rules of WCAG 2.0 to 2.2 A and AA and the best practices. A test proves that it fails on a page that is not accessible.
- **What jsdom cannot judge is covered another way**: contrast has no layout in jsdom, so `tokens.test.ts` measures every pair of tokens in both themes (ADR 0023); and the size of targets is held by the rule above and by the token test of the 48 px.

## Consequences

- The first run of axe found one real defect: two landmarks with the same name on the gallery page. It is fixed.
- A new screen is audited by adding one `expectNoViolations()` to `a11y.test.tsx`.
- Checking by eye with a screen reader and a phone is still needed before v1.0 (E10), because an automatic audit finds about a third of the problems at most.
