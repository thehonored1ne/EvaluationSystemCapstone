---
title: "Color System & UI Design Tokens Audit"
category: "UI & Design"
tags: [ui, design-system, colors, tokens, dark-mode, tailwind]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[System Architecture]]

# System Color Audit & Semantic Token Guide

This document provides a comprehensive audit of all colors utilized across the **Evaluation System** application. All colors are organized into a standardized **Semantic Design Token System**, mapped across **Light Mode** and **Dark Mode**, and detailed with corresponding Tailwind CSS utility classes and hex/HSL definitions.

---

## 2. Core Brand Palette

| Token Name                | Light Mode Value    | Dark Mode Value    | Tailwind Class | Usage / Target                                                                      |
| :------------------------ | :------------------ | :----------------- | :------------- | :---------------------------------------------------------------------------------- |
| `--color-brand-primary` | `#9b0000 -passed` | `#e07a7a -passed` |                | Main navbar, active sidebar tab, brand accents, primary buttons, wizard active step |
| `--color-brand-hover`   | `#7A0000 -passed` | `#ea8c8c -passed` |                | Hover states on primary buttons & active navigation tabs                            |
| `--color-brand-subtle`  | `#9C2121 -passed` | `rgba(224, 122, 122, 0.15) -passed` | | Selected container backgrounds, brand tag backgrounds                               |
| `--color-brand-border`  | `#9b0000 -passed` | `#e07a7a -passed` |                | Navbar bottom border, logo icon frame border, accent card borders                   |
| `--color-brand-ring`    | `#9b0000 -passed` | `#e07a7a -passed` |                | Focus outlines on form inputs & selected rating pills                               |

---

## 3. Semantic Surface & Background Tokens (Lightness Elevation Hierarchy)

| Semantic Token            | Light Mode (CSS / Hex)         | Dark Mode (CSS / Hex)          | Tailwind Utility | Component Application                               |
| :------------------------ | :----------------------------- | :----------------------------- | :--------------- | :-------------------------------------------------- |
| `--surface-canvas`      | `#fafafa -passed`            | `#111113 -passed`            |                  | Level 0: Root app background, viewport wrapper      |
| `--surface-sidebar`     | `#ffffff -passed`            | `#18181b -passed`            |                  | Level 1: Collapsible sidebar, mini-sidebar rail     |
| `--surface-card`        | `#ffffff -passed`            | `#18181b -passed`            |                  | Level 1: Stat metric cards, leaderboard rows panels |
| `--surface-card-subtle` | `#ffffff -passed`            | `#1f1f23 -passed`            |                  | Level 1 Sub: Card header bars, secondary headers    |
| `--surface-elevated`    | `#ffffff -passed`            | `#27272a -passed`            |                  | Level 2: Dropdown menus, modal dialogs, popovers    |
| `--surface-header-nav`  | `#9b0000 -passed`            | `#161619 -passed`            |                  | Top navigation header across all evaluator portals  |
| `--surface-footer`      | `#e5e6eb -passed`            | `#18181b -passed`            |                  | Fixed/Sticky full-width application footer          |
| `--surface-input`       | `#ffffff -passed`            | `#18181b -passed`            |                  | Form text fields, textareas, search bars            |
| `--surface-muted`       | `#525260 -passed`            | `#27272a -passed`            |                  | Inactive rating buttons, skeleton placeholders      |
| `--surface-overlay`     | `rgba(0, 0, 0, 0.25) -passed`| `rgba(15, 15, 20, 0.70) -passed` |              | Modal backdrop, welcome hero overlay                |

---

## 4. Semantic Typography & Text Tokens (Anti-Glare / Soft Off-White)

| Semantic Token       | Light Mode (Hex / Class) | Dark Mode (Hex / Class) | Tailwind Utility | Visual Role                                           |
| :------------------- | :----------------------- | :---------------------- | :--------------- | :---------------------------------------------------- |
| `--text-primary`   | `#18181b -passed`       | `#f4f4f5 -passed`      |                  | Main headings, table titles, primary values           |
| `--text-secondary` | `#52525b -passed`       | `#d4d4d8 -passed`      |                  | Subtitles, helper descriptions, table body text       |
| `--text-tertiary`  | `#4C4C52 -passed`       | `#a1a1aa -passed`      |                  | Timestamps, metadata labels, icon accents             |
| `--text-muted`     | `#55555E -passed`       | `#71717a -passed`      |                  | Form input placeholders, disabled text                |
| `--text-brand`     | `#9b0000 -passed`       | `#e07a7a -passed`      |                  | Stat counters, active tabs, highlighted faculty links |
| `--text-inverse`   | `#ffffff -passed`       | `#f4f4f5 -passed`      |                  | Text on navbar, primary buttons, badges               |

---

## 5. Semantic Border & Divider Tokens

| Semantic Token               | Light Mode (Hex / Class)     | Dark Mode (Hex / Class)      | Tailwind Utility | Usage                                               |
| :--------------------------- | :--------------------------- | :--------------------------- | :--------------- | :-------------------------------------------------- |
| `--border-subtle`          | `#545463 -passed`           | `#27272a -passed`           |                  | Table cell dividers, sidebar right border           |
| `--border-card`            | `#9b0000 -passed`           | `#e07a7a -passed`           |                  | Stat cards, containers, wizard panels               |
| `--border-accent-card`     | `5px solid #9b0000 -passed` | `5px solid #e07a7a -passed` |                  | Left accent stripe on all admin & report stat cards |
| `--border-focus`           | `#9b0000 -passed`           | `#e07a7a -passed`           |                  | Active input outline border                         |
| `--border-interactive-tab` | `#9b0000 -passed`           | `#e07a7a -passed`           |                  | Active bottom underline tab indicator               |

---

## 6. Feedback & Status Tokens

### 6.1 Positive / Success / High Rating

*Used for: Completed evaluations, positive sentiment comments, active status badges, scores >= 4.0.*

| Semantic Token              | Light Mode         | Dark Mode          | Tailwind Class |
| :-------------------------- | :----------------- | :----------------- | :------------- |
| `--status-success-bg`     | `#DFFBEE-passed` | `#DFFBEE-passed` |                |
| `--status-success-badge`  | `#035E44-passed` | `#03DD9F-passed` |                |
| `--status-success-text`   | `#035E44-passed` | `#03DD9F-passed` |                |
| `--status-success-border` | `#035E44-passed` | `#03DD9F-passed` |                |
| `--status-success-solid`  | `#035E44-passed` | `#03DD9F-passed` |                |

---

### 6.2 Warning / In-Progress / Neutral Sentiment

*Used for: Active semester period tag, pending evaluations, neutral sentiment, draft persistence notices.*

| Semantic Token              | Light Mode         | Dark Mode          | Tailwind Class |
| :-------------------------- | :----------------- | :----------------- | :------------- |
| `--status-warning-bg`     | `#FCF6E4-passed` | `#FCF6E4-passed` |                |
| `--status-warning-badge`  | `#843C06-passed` | `#F7A15E-passed` |                |
| `--status-warning-text`   | `#843C06-passed` | `#F7A15E-passed` |                |
| `--status-warning-border` | `#843C06-passed` | `#F7A15E-passed` |                |
| `--status-warning-solid`  | `#843C06-passed` | `#F7A15E-passed` |                |

---

### 6.3 Danger / Destructive / Constructive Sentiment

*Used for: Closed schedules, constructive/negative sentiment, deletion confirmation modals, profanity warnings.*

| Semantic Token             | Light Mode         | Dark Mode          | Tailwind Class |
| :------------------------- | :----------------- | :----------------- | :------------- |
| `--status-danger-bg`     | `#fff1f2-passed` | `#fff1f2-passed` |                |
| `--status-danger-badge`  | `#A30F34-passed` | `#F89BB2-passed` |                |
| `--status-danger-text`   | `#A30F34-passed` | `#F89BB2-passed` |                |
| `--status-danger-border` | `#A30F34-passed` | `#F89BB2-passed` |                |
| `--status-danger-solid`  | `#A30F34-passed` | `#F89BB2-passed` |                |

---

### 6.4 Informational / Machine Learning / Overrides

*Used for: AI validation accuracy metric, manually overridden sentiment chips, informational callouts.*

| Semantic Token           | Light Mode         | Dark Mode          | Tailwind Class |
| :----------------------- | :----------------- | :----------------- | :------------- |
| `--status-info-bg`     | `#eef2ff-passed` | `#eef2ff-passed` |                |
| `--status-info-badge`  | `#4338ca-passed` | `#BCB6EC-passed` |                |
| `--status-info-text`   | `#4338ca-passed` | `#BCB6EC-passed` |                |
| `--status-info-border` | `#4338ca-passed` | `#BCB6EC-passed` |                |

---

## 7. Institutional Rankings & Medal Tokens

| Role / Rank         | Token Name         | Hex Color                 | Tailwind Utility                        | Visual Asset    |
| :------------------ | :----------------- | :------------------------ | :-------------------------------------- | :-------------- |
| **1st Place** | `--medal-gold`   | `#fbbf24` / `#f59e0b` | `text-amber-400`, `bg-amber-400/10` | 🥇 Gold Medal   |
| **2nd Place** | `--medal-silver` | `#cbd5e1` / `#94a3b8` | `text-zinc-300`, `bg-zinc-300/10`   | 🥈 Silver Medal |
| **3rd Place** | `--medal-bronze` | `#b45309` / `#d97706` | `text-amber-700`, `bg-amber-700/10` | 🥉 Bronze Medal |

## 8. CSS Shimmer & Animation Specifications

### 8.1 Hardware-Accelerated Shimmer Sweep

### 8.2 Custom Scrollbar Accent

- **Light Thumb**: `rgba(161, 161, 170, 0.35)` (`zinc-400` at 35% opacity), Hover: `rgba(161, 161, 170, 0.60)`
- **Dark Thumb**: `rgba(113, 113, 122, 0.35)` (`zinc-500` at 35% opacity), Hover: `rgba(113, 113, 122, 0.60)`

### 8.3 Persistent Rail & Flyout Hover Interaction (Gmail Model)

- **Pinned Rail Width:** `4.25rem` (`68px`), padding `0.75rem` (`12px` left & right), content column `2.75rem` (`44px`).
- **Shift-Free Invariant Leading Icon Anchor (0px Horizontal & Vertical Delta):**
  - **Horizontal Invariance:** Both collapsed and expanded states maintain `justify-content: flex-start !important;` and identical margins (`margin-left: 2px !important; margin-right: 2px !important;`). Leading icon slot is an invariant `2.5rem × 2.5rem` (`40px × 40px`) flex box (`item.x = 14px`, `icon.x = 26px`, center `X = 34px`). Expanding to hover flyout grows the item width purely rightward (`calc(100% - 4px)`), yielding a **0.00px horizontal shift delta**.
  - **Vertical Invariance:** Maintained consistent `display: block` formatting context on `<ui-tooltip>` across both collapsed and hovered states to prevent flexbox margin collapsing discrepancy (which previously produced a 1px compound shift per item up to 10px). All items now maintain an invariant `Delta Top: 0.00px` in automated Playwright verification.
- **Synchronized Expansion Timing (Zero Active Lag):**
  - Removed artificial delays on child navigation items and sidebar container: both container and active indicator pill transition at `200ms cubic-bezier(0.4, 0, 0.2, 1)`, moving in exact lockstep without the active tab lagging behind.
  - Replaced snapping `min-width: 16rem` with `min-width: 4.25rem; max-width: 16rem; width: 16rem;` on `:hover`, enabling smooth interpolation instead of instantaneous jump.
- **Page Transition Bridge Stability (Zero Heading Flicker):**
  - Scoped collapsed hide rules to `html.sidebar-is-collapsed:not(.sidebar-hover-active)` to prevent `<body>` lacking the hover class from triggering visibility drops during DOM morphing.
  - Added `mouseover`/`mouseout` tracking and `morph.updating` class persistence in Livewire to guarantee `sidebar-hover-active` remains unbroken while navigating across pages. Group headings maintain `opacity: 1; visibility: visible; transition: none !important;` during hover transitions (0 dropped frames verified in Playwright).
- **Intentional 250ms Hover Intent Delay:**
  - Sidebar width and box-shadow expansion trigger after an intentional `250ms` delay (`transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 250ms, box-shadow 200ms ease 250ms !important;`) on hover and close, preventing accidental trigger sweeps while moving across the screen.
- **Synchronized Logo Cross-Fade & Bridge Stabilization:**
  - Small icon logo (`.sidebar-small-logo`) is absolutely centered at `left: 22px; top: 50%; transform: translate(-50%, -50%)` (center X = 34.00px, exactly equidistant with 13px left/right margins and matching the navigation icon track).
  - During the 250ms hover-open delay, small logo stays 100% visible (`opacity: 1; visibility: visible;`) and big logo stays 100% hidden (`opacity: 0; visibility: hidden; pointer-events: none;`).
  - Both logos transition via `opacity` and `visibility` with an identical `250ms` transition delay (`transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;`), seamlessly cross-fading only while the sidebar width physically expands from 68px to 256px.
  - Collapsed hide rules are strictly scoped to `html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover)` and bridge rules enforce `transition: none !important;` under `sidebar-hover-active`. When navigating between pages while hovering, the big logo remains permanently visible with 0 dropped frames and zero swap back to the small logo.
- **Synchronized Text & Heading Hover Exit Hold (Zero Premature Disappearance):**
  - Text labels (`[data-content]`), group headings (`[data-flux-navlist-group-heading] > div`), badges, and chevrons apply `transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;` on `:not(:hover)`.
  - During the 250ms mouse exit hold, headings and text remain 100% visible, smoothly fading out and clipping within `overflow: hidden` strictly while the sidebar contracts from 256px to 68px.
- **Manual Toggle Button 0ms Override (`body.sidebar-animating`):**
  - High-specificity rules on `body.sidebar-animating` override the 250ms hover-intent delay to `0ms` across sidebar width, logo cross-fade, headings, and nav items.
  - On toggle button click, the sidebar animates immediately (200ms duration) and the big/small logos cross-fade with 0ms delay, preventing the small logo and big logo from displaying simultaneously.
- **Group Heading Single-Line Non-Wrapping Invariance:**
  - Applied `white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important;` to `[data-flux-sidebar] [data-flux-navlist-group-heading]` and all direct children (`> *`, `> div`, `> span`).
  - In the 68px collapsed mini-rail (inner width ~43px), multi-word section headers like "Reports & Tools" (97px wide) are strictly constrained to a single 12px line without wrapping to multiple lines or vertically overflowing the 1.75rem spacer height.
- **Standalone Centered Active Box:** Nav items in mini-rail rendered as `2.5rem × 2.5rem` (`40px × 40px`) box with `border-radius: 0.625rem` (`rounded-lg`) without right-border clipping.
- **Flyout Expanded Width:** `16rem` (`width: 16rem !important`), `z-index: 45`, floating overlay elevation with dual ambient shadow (`rgba(0,0,0,0.15)` light / `rgba(0,0,0,0.65)` dark).
- **Active Navigation Indicator:** Synchronized via client-side `livewire:navigated` listener applying `[data-current]` token styles (`#9b0000` light / `rgba(224, 122, 122, 0.12)` dark).

---

