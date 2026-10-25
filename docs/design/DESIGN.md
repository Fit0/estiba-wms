---
name: Logistics Industrial Precision
colors:
  surface: '#081425'
  surface-dim: '#081425'
  surface-bright: '#2f3a4c'
  surface-container-lowest: '#040e1f'
  surface-container-low: '#111c2d'
  surface-container: '#152031'
  surface-container-high: '#1f2a3c'
  surface-container-highest: '#2a3548'
  on-surface: '#d8e3fb'
  on-surface-variant: '#c3c6d7'
  inverse-surface: '#d8e3fb'
  inverse-on-surface: '#263143'
  outline: '#8d90a0'
  outline-variant: '#434655'
  surface-tint: '#b4c5ff'
  primary: '#b4c5ff'
  on-primary: '#002a78'
  primary-container: '#2563eb'
  on-primary-container: '#eeefff'
  inverse-primary: '#0053db'
  secondary: '#bec6e0'
  on-secondary: '#283044'
  secondary-container: '#3f465c'
  on-secondary-container: '#adb4ce'
  tertiary: '#4edea3'
  on-tertiary: '#003824'
  tertiary-container: '#007d55'
  on-tertiary-container: '#bdffdb'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#dbe1ff'
  primary-fixed-dim: '#b4c5ff'
  on-primary-fixed: '#00174b'
  on-primary-fixed-variant: '#003ea8'
  secondary-fixed: '#dae2fd'
  secondary-fixed-dim: '#bec6e0'
  on-secondary-fixed: '#131b2e'
  on-secondary-fixed-variant: '#3f465c'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#081425'
  on-background: '#d8e3fb'
  surface-variant: '#2a3548'
typography:
  display:
    fontFamily: Plus Jakarta Sans
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: -0.015em
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 22px
  body-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
  data-mono:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.02em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 0.75rem
  gutter-lg: 1.25rem
  margin: 1rem
  margin-lg: 1.5rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
---

## Brand & Style

This design system is engineered for industrial-grade Warehouse Management Systems (WMS), supply chain logistics hubs, and inventory command centers. The interface prioritizes high-throughput spatial density, situational awareness, and ergonomic endurance over extended 8-to-12-hour shifts.

### Visual Architecture & Character
- **Aesthetic Movement:** Modern Functional Minimalism married with Industrial Precision.
- **Tone:** Methodical, reliable, sharp, and utilitarian. It eliminates non-essential decorative styling in favor of operational clarity, instant scanning of tabular data, and micro-metric transparency.
- **Emotional Response:** Inspires quiet confidence, absolute control, operational velocity, and minimal cognitive strain during complex inventory reconciliation.

## Colors

The palette balances deep navy slates with crisp logistics blues and targeted telemetry status accents. The system defaults to dark mode for high-contrast, low-fatigue operations in warehouse terminals, while retaining a full light-mode parity system.

### Functional Status System
- **In Stock / Optimal:** Emerald (`#10B981` surface tint / `#059669` fill) for confirmed pallet locations and normal bin fill rates.
- **Low Stock / Reorder Point:** Amber (`#F59E0B` / `#D97706`) for threshold alerts, expedited picking warnings, and cross-docking staging delays.
- **Out of Stock / Critical Fault:** Crimson (`#EF4444` / `#DC2626`) for stockouts, broken SKUs, bay obstructions, and discrepancy holds.
- **Receiving / In Transit:** Logistics Blue / Cyan (`#3B82F6` / `#06B6D4`) for incoming manifests, active dock doors, and dynamic putaway tasks.

### Theme Modes & Structural Tones
- **Light Mode:** Canvas background `#F8FAFC`, structural surface `#FFFFFF`, secondary cards `#F1F5F9`, borders `#E2E8F0`, primary text `#0F172A`, secondary text `#64748B`.
- **Dark Mode:** Canvas background `#0B0F17`, structural surface `#0F172A`, raised card containers `#1E293B`, elevated overlays `#334155`, borders `#1E293B` to `#334155`, primary text `#F8FAFC`, secondary text `#94A3B8`.

## Typography

The typography couples the structured, crisp geometry of **Plus Jakarta Sans** for dashboards and section headers with the utilitarian, screen-optimized legibility of **Inter** for data grids, telemetry readouts, and operational forms.

### Usage Directives
- **Tabular Figures:** Always apply `font-feature-settings: "tnum" 1, "cv05" 1` to numerical displays, SKU strings, barcode translations, and inventory quantities to prevent layout jittering during real-time streaming updates.
- **Labels & Micro-copy:** Keep metadata labels uppercase when using `label-sm` for metric descriptors (`BIN LOC`, `LOT #`, `SKU`, `TOTAL CUBE`), paired with 500 or 600 font weights.
- **Scale Optimization:** Body copy defaults to a compact 13px baseline (`body-md`) to ensure dense vertical packing without compromising cross-row scan efficiency.

## Layout & Spacing

The layout philosophy centers on a fluid, multi-pane desktop grid constructed for ultra-wide command monitors, standard 1080p desktop terminals, and ruggedized vehicle-mounted tablets.

### Layout Model
- **Grid Architecture:** 12-column fluid grid system paired with persistent collapsible left-hand navigation (240px wide default, 64px icon-only rail) and optional contextual utility panels (320px right inspector).
- **Desktop Density (1440px+):** Gutter is locked at `1.25rem` (`gutter-lg`) with page margins at `1.5rem` (`margin-lg`).
- **Terminal & Tablet Breakpoints (<1024px):** Gutter tightens to `0.75rem` (`gutter`) and canvas margin collapses to `1rem` (`margin`). The right inspector shifts to an off-canvas drawer.
- **Compact Vertical Cadence:** Form field vertical rhythm and table row padding strictly utilize `space-xs` (4px) and `space-sm` (8px) increments to maximize visible records above the fold.

## Elevation & Depth

Visual hierarchy uses crisp, low-contrast structural outlines and planar surface shifts instead of heavy drop shadows, eliminating visual noise across multi-window operational workflows.

### Depth Architecture
- **Surface Elevation Layers (Dark Mode):**
  - Layer 0 (Canvas Base): `#0B0F17`
  - Layer 1 (Data Grids & Table Containers): `#0F172A` with 1px border `#1E293B`
  - Layer 2 (Raised Cards, Active Row Highlighting, Popovers): `#1E293B` with 1px border `#334155`
  - Layer 3 (Modals, Barcode Scanning HUDs, Context Menus): `#1E293B` with 1px border `#475569` and ambient shadow `0 12px 32px -4px rgba(0, 0, 0, 0.45)`
- **Surface Elevation Layers (Light Mode):**
  - Layer 0 (Canvas Base): `#F8FAFC`
  - Layer 1 (Cards, Main Workspaces): `#FFFFFF` with 1px border `#E2E8F0`
  - Layer 2 (Dropdowns, Floating Inspectors): `#FFFFFF` with 1px border `#CBD5E1` and ambient shadow `0 4px 16px -2px rgba(15, 23, 42, 0.08)`
  - Layer 3 (Modals, Overlays): `#FFFFFF` with 1px border `#94A3B8` and ambient shadow `0 16px 36px -4px rgba(15, 23, 42, 0.16)`
- **Border Hierarchy:** Never rely on elevation through drop shadows alone. Every functional boundary (panels, headers, cell dividers) is explicitly anchored by a 1px solid stroke.

## Shapes

The design system enforces a precise, soft geometry (`roundedness: 1`) to preserve an industrial, mechanical identity while avoiding sterile, sharp corners.

### Radii Distribution
- **Controls & Micro-elements:** Buttons, input fields, dropdown toggles, and status badges utilize `0.25rem` (4px) corner radius.
- **Containers & Panels:** Data tables, analytical card panels, and slide-over sidebars utilize `0.5rem` (8px, `rounded-lg`).
- **Interactive Badges:** Small counter badges and numerical dot indicators use full pill rounding (`9999px`) to maintain typographic balance against tabular numerical data.

## Components

### Buttons
- **Primary:** High-visibility logistics blue (`#2563EB`), hover `#1D4ED8`, active `#1E40AF`. Text is crisp white `#FFFFFF`, semi-bold 13px (`body-md`), height 32px (compact) or 36px (standard), with `space-md` horizontal padding.
- **Secondary:** Subtle border (`#334155` dark / `#E2E8F0` light) against surface color. Hover states introduce a subtle 4% brightness lift.
- **Destructive:** Signal red outline or subdued red fill (`#EF4444` at 12% opacity) with `#EF4444` text for scrap item, cancel pick, or bay lock commands.
- **Focus Rings:** 2px solid `#3B82F6` with 2px offset, ensuring strict keyboard accessibility across fast barcode-scanner-to-keyboard handoffs.

### Data Tables (WMS Grid Core)
- **Density:** Compact row height capped at 36px; standard overview mode at 44px.
- **Header:** Uppercase 11px semi-bold (`label-sm`), background tinted neutral, persistent sorting indicators, sticky positioning during scroll.
- **Cell Structure:** Numeric values right-aligned with tabular figures enabled; SKU/Location codes monospaced; single-line text truncated with ellipses and hover tooltips.
- **Row States:** Alternating zebra striping optional; hover highlights entire row with 1px active indicator bar on the left edge (`#3B82F6`); selected row sets background to 8% primary accent tint.

### Status Badges & Inventory Health Chips
- **Design:** Compact height (20px to 22px), font size 11px, weight 600, border radius `0.25rem`.
- **In Stock:** Emerald tint fill (`rgba(16, 185, 129, 0.12)`), text `#10B981` (dark) / `#047857` (light), bordered with `rgba(16, 185, 129, 0.25)`.
- **Low Stock:** Amber tint fill (`rgba(245, 158, 11, 0.12)`), text `#F59E0B` (dark) / `#B45309` (light), bordered with `rgba(245, 158, 11, 0.25)`.
- **Out of Stock:** Crimson tint fill (`rgba(239, 68, 68, 0.12)`), text `#EF4444` (dark) / `#B91C1C` (light), bordered with `rgba(239, 68, 68, 0.25)`.
- **Receiving / In-Staging:** Logistics blue tint fill (`rgba(59, 130, 246, 0.12)`), text `#3B82F6` (dark) / `#1D4ED8` (light). Includes pulsating 6px status dot for live inbound dock processing.

### Form Controls & Barcode Scanning Inputs
- **Inputs:** 32px height, 1px solid border, inset background `#0F172A` (dark) / `#FFFFFF` (light). Text `#F8FAFC` (dark) / `#0F172A` (light).
- **Scanner Integration:** Dedicated visual cue (right-aligned barcode icon in cyan/blue) indicating input field is active for handheld scanner input. On scan trigger, input flashes a brief 200ms green outline confirmation.
- **Checkboxes & Radios:** 16px square/circle with 1px border. Selected state fills with primary blue and white micro-icon check. Hit target padded to 24px invisible hit-area.

### Operational KPI Stats Badges
- **Structure:** Modular cards featuring a 12px uppercase descriptor label, a prominent 24px bold tabular figure, and a directional micro-badge indicating shift-over-shift pick rates, dock turnarounds, or stock discrepancies.