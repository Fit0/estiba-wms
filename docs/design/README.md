# Design

The visual design of the app is the **Logistics Industrial Precision** system. It was drawn with Google Stitch from two example screens, and it is the reference every screen of the frontend follows (UI-01 to UI-04 of the plan turn it into code).

| File | What it is |
|---|---|
| [DESIGN.md](DESIGN.md) | The design system as Stitch wrote it: colors, typography, spacing, radii, elevation and components, in dark and light |
| [reference/login.html](reference/login.html) | Example of the sign-in screen: two panels, role picker, language and theme switches |
| [reference/inventory.html](reference/inventory.html) | Example of the app shell with the inventory screen: collapsible side navigation, top bar, KPI cards, filters and a dense data table |

The two HTML files are **references to look at, not code to run or to copy**. They load Tailwind and the fonts from a CDN, keep all their text in Spanish and invent data (figures, partner logos, SSO and RFID sign-in, an ERP sync) that Estiba does not have. What is taken from them is the look and the layout.

Where the example and the project rules differ, the rules win:

- **Name and language**: the app is Estiba, with Spanish by default and English on request (ADR 0004), not NexusWMS.
- **Touch targets**: the example is dense, with controls of 32 px. The picking PWA needs targets of at least 48 px, so the system has a compact density for the office and a touch density for the PWA.
- **Contrast and text size**: text of 11 and 12 px must still reach 4.5:1 (WCAG 2.2 AA). A test checks it.
- **Only what the system does**: no figure, partner or sign-in method that does not exist.

## In the code

The design is built in `frontend/` and is decided in [ADR 0023](../adr/0023-design-tokens-and-two-themes.md): the values are semantic tokens in `frontend/src/styles/tokens.css`, a dark theme by default and a light one, and the contrast of every pair is tested. The ADR lists the seven values that differ from `DESIGN.md` to reach WCAG 2.2 AA.

The screens that follow the system, in order: tokens and themes (UI-01), the login and the shell (UI-02, done), the data components (UI-03, done), the touch density and the accessibility audit (UI-04).

The top bar of the example has a warehouse selector, a search box, a sync indicator and notifications. The shell has none of them yet, because the data they show does not exist: the selector comes with the warehouse screens (E05-07), the search with the command palette (E05-05) and the notices with the real-time ones (E07-06). What the bar has now is what the system can already tell: the connection, the theme and who is signed in.

The screenshots that Stitch exports (`screen.png`) did not download, so the HTML is the only picture of the screens.
