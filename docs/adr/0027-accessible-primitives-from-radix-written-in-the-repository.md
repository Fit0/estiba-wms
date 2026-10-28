# 0027. Accessible primitives from Radix, written in the repository

- Status: Accepted
- Date: 2026-10-28

## Context

The screens that follow (E05-07, E06) need a dialog to confirm, a menu of row actions, a select, a tooltip, notices, and a command palette. These are the controls that are hardest to make accessible by hand: focus that stays inside a modal and goes back where it came from, a menu that moves with the arrows, a select that answers to typing, a notice that is announced without stealing the focus. The look has to come from the tokens of ADR 0023 in both themes, and the plan names shadcn/ui.

## Options considered

1. **Write them by hand.** Full control, and every keyboard and screen-reader behaviour to write and keep right.
2. **A finished component library** (MUI, Mantine). Complete, but it brings its own look and its own theming, to be bent into the tokens.
3. **The shadcn/ui CLI** (`npx shadcn add`). It generates files into the repository. It needs its registry from the network, and its files are written for its own colors and have to be rewritten for ours.
4. **The same pieces that shadcn/ui uses, written in the repository**: Radix UI for the behaviour, `cmdk` for the palette and `sonner` for the notices, with our own classes.

## Decision

- **Option 4.** `radix-ui` (one package; only what is imported ships), `cmdk` and `sonner`, wrapped in `frontend/src/components/ui/`: `Dialog`, `DropdownMenu`, `Select`, `Tooltip`, `Toaster` and `CommandPalette`. The files are ours: they speak only in tokens (no color, no `dark:`; `rules.test.ts` keeps it so), take their texts from the catalog, and expose a smaller API than the libraries.
- **A dialog always has a title** (a required `title` prop), and a `description` when it has one. The Radix dialog traps the focus, closes with Escape and gives the focus back to the control that opened it.
- **A select always has a visible label.** Its list is the Radix one, which answers to typing, the arrows, Home and End.
- **A tooltip repeats or completes a name, never alone carries one.** It shows on focus as well as on hover. The `TooltipProvider` is at the root.
- **Notices** (`toast.success(...)`, `toast.error(...)`) are announced politely and do not take the focus. The kind shows in the edge and in the text, not in the color of the text alone.
- **The command palette** opens with Ctrl+K (Cmd+K on a Mac) and with a button in the top bar. It offers the pages the role can see (the same `visibleNavigation` as the sidebar, so the two cannot disagree), switching the theme and signing out. A screen with an action of its own adds a group.
- **The theme was already remembered** (ADR 0023: `localStorage`, with a fallback when the browser refuses it). E05-05 only adds a test that the palette's theme action saves the choice too.
- **The gallery at `/design`** shows the primitives next to the data components.

## Consequences

- The behaviour is Radix's, tested by its authors, and the look is ours. Updating Radix is an `npm update`; nothing to merge.
- The bundle grows by about 155 kB, 49 kB compressed, mostly Radix and `cmdk`. Dialog, menu and select are loaded with the app; code-splitting the palette is possible when it matters.
- jsdom lacks what Radix and `cmdk` ask of a browser (`ResizeObserver`, pointer capture, `scrollIntoView`), so `test-setup.ts` stubs them. A bug that only a real browser shows (the position of a popover) is for the end-to-end tests of E06-06.
- The palette lists pages and two actions now. The warehouse switch and the search come with their screens (E05-07, E06).
- The automatic accessibility audit with `axe` and the touch sizes are UI-04.
