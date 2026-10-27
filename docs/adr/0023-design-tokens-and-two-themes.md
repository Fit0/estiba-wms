# 0023. The design system: semantic tokens, two themes and fonts that ship with the app

- Status: Accepted
- Date: 2026-10-26

## Context

The frontend gets a new look: the **Logistics Industrial Precision** system in [docs/design/](../design/README.md), drawn from two example screens. Every screen that comes after (the catalog, the receiving dock, the picking PWA, the KPI dashboard) must look like it, in a dark theme and in a light one, and the app is used for eight to twelve hours at a time on office screens and on scanners with gloves. Several things had to be decided:

- **How a component gets its colors**, so that it looks right in both themes without knowing which one is on.
- **Which palette is the dark one.** `DESIGN.md` gives two: a table of Material-style tones, which the example screens use, and a list of "structural tones" in its text, which they do not.
- **Which theme a visitor sees first.**
- **Where the fonts come from.**
- **What happens where the design does not meet the accessibility target** of the project, WCAG 2.2 AA (CLAUDE.md).

## Options considered

1. **A `dark:` variant on every utility** (`bg-white dark:bg-slate-900`). The common Tailwind way. Every component says both themes, so one that forgets is wrong in one of them, and nothing checks the pairs.
2. **Semantic tokens.** The theme is a set of CSS variables on the root element, a component says what a thing is (`bg-surface-1`, `text-fg-secondary`), and only the variables change. A test can read the variables and measure every pair.
3. **Fonts from the Google Fonts CDN**, as in the examples. Nothing to install, but each visit sends the user's address to Google, and the picking PWA would have no fonts without a connection.
4. **Fonts installed from npm** (`@fontsource-variable/inter`, `@fontsource-variable/plus-jakarta-sans`), bundled by Vite.

## Decision

- **Semantic tokens** (option 2), in [`frontend/src/styles/tokens.css`](../../frontend/src/styles/tokens.css). Tailwind 4 reads them through `@theme inline` in `src/index.css`, so the utilities are `bg-canvas`, `bg-surface-1/2/3`, `text-fg`, `text-fg-secondary`, `text-fg-muted`, `border-line`, `bg-primary`, `text-ok`, `bg-ok-fill`, `rounded-control`, `rounded-panel`, `shadow-overlay`, and so on. The theme is `data-theme="dark"` or `"light"` on `<html>`. A component never writes a color and never uses a `dark:` variant: `rules.test.ts` fails when one does.
- **Dark is the default**, as the design says. A visitor's own choice wins, and otherwise a system that asks for a light theme gets it (`theme/theme.ts`). The choice is kept in `localStorage`, and a browser that refuses storage still works. The switch in the top bar comes with UI-02.
- **The dark palette is the one of the example screens** (the table of `DESIGN.md`: `#081425` canvas, `#111c2d`, `#1f2a3c` and `#2a3548` layers). The light palette is the "light mode" list of `DESIGN.md`, since the examples have no light theme. The structural tones of the text for dark (`#0B0F17` and the like) are not used, because they are not what the screens look like.
- **Where `DESIGN.md` is below AA, the token is a shade that reaches it**, and `tokens.test.ts` measures the contrast of every pair that is used together (text on the four layers, status text on its own tint, the label of a button, the focus ring and the edge of a control):

  | Token | `DESIGN.md` | Here | Why |
  |---|---|---|---|
  | Secondary text, light | `#64748B` | `#526077` | 4.3:1 on a secondary card |
  | Warning text, light | `#B45309` | `#92400E` | 4.2:1 over its own tint on a card |
  | Success text, light | `#047857` | `#046A4D` | 4.4:1 over its own tint in a selected row (found by UI-03) |
  | Error text, dark | `#EF4444` | `#F87171` | 4.1:1 over its own tint |
  | Info text, dark | `#3B82F6` | `#60A5FA` | 4.0:1 over its own tint |
  | Edge of a control, light | `#E2E8F0` | `#64748B` | an edge that must be seen needs 3:1; `--line` stays for what only separates |
  | Label of a primary button | `#EEEFFF` (dark) | `#FFFFFF` | 4.5:1 on the blue, and 5.2:1 with white, which is what the text of `DESIGN.md` says |

- **Typography and sizes are tokens too**: `text-display`, `text-headline-lg`, `text-headline-md`, `text-headline-sm`, `text-body-lg/md/sm`, `text-label-md/sm` and `text-data`, with their line height and spacing. The base size is 13 px, as in the design. Numbers use `tnum` (tabular figures) so that a figure that changes does not move the ones beside it. The radii are two names, `rounded-control` (4 px, buttons and inputs) and `rounded-panel` (8 px, tables and cards); the examples use the same sizes under other names.
- **Density is a token as well**: `--control-height` and `--row-height` follow `data-density` on the root element. Compact is the default (32 px controls, 36 px rows, as in the design). `comfortable` and `touch` (48 px controls, the minimum of the picking PWA) come into use in UI-04.
- **The fonts are installed from npm** (option 4): Plus Jakarta Sans for headings and Inter for everything else, as variable fonts of Latin and the other alphabets, loaded only when a text needs them. No request leaves for a third party, and they work offline.
- **Icons** are `lucide-react`, which `shadcn/ui` uses too, and are added in UI-02 where the first ones are needed. The example uses Material Symbols, and one set of icons is easier to keep than two.

## Consequences

- A new screen is correct in both themes without thinking about it, and a change of palette is a change of one file.
- The contrast of the design is tested and not remembered. Changing a token to a color that fails breaks the build, and the failure names the pair.
- A color that is not in the tokens (a chart series, a status that does not exist yet) has to be added to them, with its pair and its test. The charts of E07 will need a few.
- The values differ from `DESIGN.md` in the seven places above. A person who compares the two will find them here and not by surprise.
- The theme is applied when the page script runs, so a visitor who prefers light may see a flash of dark. An inline script in `index.html` would remove it, and the content security policy of E10 would then need a hash for it. Until then it stays as it is.
- The fonts add about 270 kB of files, of which a visitor loads only the ones for their alphabet (Latin: about 75 kB).
