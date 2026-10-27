# 0024. Controlled data components and a development gallery

- Status: Accepted
- Date: 2026-10-27

## Context

UI-03 adds the components every back-office screen will use: a data table, a status badge and a KPI card. The first screens that need them (the stock list, E06-03, and the catalog) get their rows from the API a page at a time (ADR 0013). Three things had to be decided:

1. **Who owns the sort, the selection and the page**: the table, or the screen that uses it.
2. **How a status is shown** so that its meaning does not depend on color.
3. **How to look at the components** before a real screen uses them, without a screen that only exists for that.

## Options considered

1. **A table that sorts, selects and pages by itself** (client-side, like most table libraries). Quick to use, but with server data it sorts only the rows it has, and the screen must reach inside it to know what is selected.
2. **A controlled table.** The screen passes `sort`, `selection` and `pagination` and gets callbacks. It is a little more code per screen, and the same component works for client-side lists and for server-driven ones.
3. **A table library (TanStack Table).** Powerful, with a large API. Its headless model fits later, if grouping or column resizing arrive, but the current needs are small and the markup and accessibility (`aria-sort`, a scroll region, a select-all) would be ours to write anyway.
4. **A tool such as Storybook** for the gallery. It is the usual answer, and it adds a build, a dependency tree and a second place where the tokens and themes must be wired.
5. **A page in the app**, at `/design`, with example data.

## Decision

- **Controlled components** (option 2), written by hand (not option 3). `DataTable` takes `columns`, `rows`, `rowKey`, `sort`, `selection` and `pagination`, and tells the screen what changed. It does draw the three states every list has: loading, an error with a retry, and no rows. `Pagination` is a component of its own, and its `pageWindow` function (seven slots at most, so the bar does not jump) is a pure function with its own tests. If a later screen needs more than this, the columns can move to TanStack Table without changing how screens call `DataTable`.
- **A status says what it is in words.** `StatusBadge` takes a `tone` (`neutral`, `ok`, `warn`, `bad`, `info`) and always a text, and `KpiCard` writes its trend in words next to the arrow. Color is a help. The tone is also a `data-tone` attribute, which is what tests read.
- **The table is accessible by construction**: a caption for screen readers, `aria-sort` on sortable headers with a real button inside, a scroll region that the keyboard can reach, a select-all that is indeterminate when only some rows of the page are chosen (it never touches rows of other pages), `aria-selected` on rows, `aria-busy` while loading and `role="alert"` on an error.
- **Row height is the density token**: `h-row` (new, next to `min-h-row`) follows `--row-height`, so the touch density of UI-04 makes rows taller without a change in the component.
- **Numbers and dates follow the language** through `Intl`, so `useTranslation()` now also returns `language`.
- **The gallery is a page at `/design`** (option 5). It shows every component with sample data, switches the table between ready, loading, error and empty, and works in both themes. The route is added only when `import.meta.env.DEV` is true and is loaded lazily, so the production bundle has no gallery code. It has no entry in the navigation. A test opens it, so it cannot rot unnoticed.
- **Contrast is tested for the new places**: status text straight on a card (the trend of a KPI) and a badge on its tint inside a selected row. The second test found that the light `ok` text reached only 4.4:1, so it moves from `#047857` to `#046A4D` (ADR 0023 lists it).

## Consequences

- A screen that uses the table writes its own sorting, selection and paging. For server data that is the point; for a small list there is a little more code than with a self-contained table.
- The row has no way yet to show a detail panel or an action menu; those come with the screens that need them (the inspector panel and the filter bar were left out of UI-03 on purpose).
- The gallery uses the same i18n catalog as the app, with Spanish and English texts, so it also checks that the components work in both.
- Row selection is kept by `rowKey`, so it survives sorting and paging as long as the screen keeps the set.
- The page-size selector and the page buttons use the compact controls; the 48 px touch sizes for the picking PWA are the work of UI-04.
