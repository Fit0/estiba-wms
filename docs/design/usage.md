# Using the design system

How to build a screen so that it looks and behaves like the rest. The values are in [ADR 0023](../adr/0023-design-tokens-and-two-themes.md), the density and the audit in [ADR 0030](../adr/0030-touch-density-and-the-accessibility-audit.md), and the components can be seen in the gallery at `/design` (development only).

## Colors and text

- Use the semantic tokens (`bg-surface-1`, `text-fg-secondary`, `border-line`, `text-bad`…). Never a hex, `rgb()` or `dark:`; `rules.test.ts` fails if you do. A token already changes with the theme.
- Never let a color carry the meaning alone. A status is a word (and a dot), as in `StatusBadge`; an error is text under the field, not only a red edge.
- Sizes come from the scale: `text-body-md` (13 px) for the interface, `text-body-lg` for leads, `text-label-sm` (11 px) only for uppercase labels, `text-data` for codes and figures. Use `tnum` on figures that line up.

## Size of what is pressed

- A button, input, select or row takes its height from `h-control` / `h-row`; a square button from `w-control`. If you need a minimum, `min-h-control` and `min-w-control`.
- Do not write `h-8`, `size-10` or similar on something that is pressed. In the touch density it would stay small.
- An icon that is only drawn is `size-4` with `aria-hidden="true"`; the button around it has the size and the name.

## Names

- Every control has a visible label (`TextField`, `Select`) or an `aria-label` (an icon button). A select in a bar uses `hideLabel`; the name is still read.
- The name of a button says what it does and, when several look alike, on what: "Bloquear A-01-01", not "Bloquear".
- A dialog has a title (`DialogContent` requires it). Close it with Escape; the focus goes back to what opened it.
- A table has a caption (not drawn) and its scroll region is reachable by keyboard.
- Two landmarks (a region, a navigation) on one page must not share a name.

## Forms

- React Hook Form with a Zod schema (ADR 0029). The messages of the schema are keys of the catalog.
- Each error is text under its field, linked with `aria-describedby`, and the field has `aria-invalid`. What the API refuses is shown in a `role="alert"` under the fields.

## Lists

- The text, the filters and the page of a list live in the address (`useSearchParams`), and the server does the searching and the paging (ADR 0031). Copy `ProductsPage`.
- Ask before what cannot be undone with `ConfirmDialog`; a form that needs fields uses `FormDialog`.

## Density

- The office uses `compact`, the PWA of the pickers `touch`. Do not branch on the density in a component: use the tokens and the layout adapts.
- The button in the top bar cycles the three; the palette has the same action.

## Check before you open the PR

1. `npm run test` passes, including `a11y.test.tsx`. Add `await expectNoViolations()` for the new screen, with its dialogs open.
2. Look at it in both themes and in the touch density, and use it with the keyboard only.
3. If you add a color, the pair must have its contrast checked in `tokens.test.ts`.
