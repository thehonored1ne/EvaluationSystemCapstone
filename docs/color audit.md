# System Color Audit & Semantic Token Guide

This document provides a comprehensive audit of all colors utilized across the **Evaluation System** application. All colors are organized into a standardized **Semantic Design Token System**, mapped across **Light Mode** and **Dark Mode**, and detailed with corresponding Tailwind CSS utility classes and hex/HSL definitions.

---

## 2. Core Brand Palette

| Token Name                | Light Mode Value    | Dark Mode Value    | Tailwind Class | Usage / Target                                                                      |
| :------------------------ | :------------------ | :----------------- | :------------- | :---------------------------------------------------------------------------------- |
| `--color-brand-primary` | `#9b0000 -passed` | `#F89696-passed` |                | Main navbar, active sidebar tab, brand accents, primary buttons, wizard active step |
| `--color-brand-hover`   | `#7A0000 -passed` | `#F57575-passed` |                | Hover states on primary buttons & active navigation tabs                            |
| `--color-brand-subtle`  | `#9C2121 -passed` | `#FCC5C5-passed` |                | Selected container backgrounds, brand tag backgrounds                               |
| `--color-brand-border`  | `#9b0000 -passed` | `#F89696-passed` |                | Navbar bottom border, logo icon frame border, accent card borders                   |
| `--color-brand-ring`    | `#9b0000 -passed` | `#F89696-passed` |                | Focus outlines on form inputs & selected rating pills                               |

---

## 3. Semantic Surface & Background Tokens

| Semantic Token            | Light Mode (CSS / Hex)         | Dark Mode (CSS / Hex)          | Tailwind Utility | Component Application                               |
| :------------------------ | :----------------------------- | :----------------------------- | :--------------- | :-------------------------------------------------- |
| `--surface-canvas`      | `#fafafa-passed`             | `#252525-passed`             |                  | Root app background, viewport wrapper               |
| `--surface-sidebar`     | `#ffffff-passed`             | `#171717-passed`             |                  | Collapsible sidebar, mini-sidebar rail              |
| `--surface-card`        | `#ffffff-passed`             | `#171717-passed`             |                  | Stat metric cards, leaderboard rows, content panels |
| `--surface-card-subtle` | `#ffffff-passed`             | `#171717-passed`             |                  | Card header bars, secondary table header strips     |
| `--surface-elevated`    | `#ffffff-passed`             | `#171717-passed`             |                  | Dropdown menus, modal dialogs, flyout tooltips      |
| `--surface-header-nav`  | `#9b0000 -passed`            | `#F89696-passed`             |                  | Top navigation header across all evaluator portals  |
| `--surface-footer`      | `#e5e6eb-passed`             | `#e5e6eb-passed`             |                  | Fixed/Sticky full-width application footer          |
| `--surface-input`       | `#ffffff-passed`             | `#171717-passed`             |                  | Form text fields, textareas, search bars            |
| `--surface-muted`       | `#525260-passed`             | `#B9B9BB-passed`             |                  | Inactive rating buttons, skeleton placeholders      |
| `--surface-overlay`     | `rgba(0, 0, 0, 0.25)-passed` | `rgba(0, 0, 0, 0.45)-passed` |                  | Modal backdrop, welcome hero overlay                |

---

## 4. Semantic Typography & Text Tokens

| Semantic Token       | Light Mode (Hex / Class) | Dark Mode (Hex / Class) | Tailwind Utility | Visual Role                                           |
| :------------------- | :----------------------- | :---------------------- | :--------------- | :---------------------------------------------------- |
| `--text-primary`   | `#18181b-passed`       | `#ffffff-passed`      |                  | Main headings, table titles, primary values           |
| `--text-secondary` | `#52525b-passed`       | `#E0E0E0-passed`      |                  | Subtitles, helper descriptions, table body text       |
| `--text-tertiary`  | `#4C4C52-passed`       | `#EBEBEB-passed`      |                  | Timestamps, metadata labels, icon accents             |
| `--text-muted`     | `#55555E-passed`       | `#B1B1B9-passed`      |                  | Form input placeholders, disabled text                |
| `--text-brand`     | `#9b0000-passed`       | `#F89696-passed`      |                  | Stat counters, active tabs, highlighted faculty links |
| `--text-inverse`   | `#ffffff-passed`       | `#ffffff-passed`      |                  | Text on navbar, footer, primary buttons, badges       |

---

## 5. Semantic Border & Divider Tokens

| Semantic Token               | Light Mode (Hex / Class)     | Dark Mode (Hex / Class)      | Tailwind Utility | Usage                                               |
| :--------------------------- | :--------------------------- | :--------------------------- | :--------------- | :-------------------------------------------------- |
| `--border-subtle`          | `#545463-passed`           | `#B2B2BD-passed`           |                  | Table cell dividers, sidebar right border           |
| `--border-card`            | `#9b0000-passed`           | `#F89696-passed`           |                  | Stat cards, containers, wizard panels               |
| `--border-accent-card`     | `5px solid #9b0000-passed` | `5px solid #F89696-passed` |                  | Left accent stripe on all admin & report stat cards |
| `--border-focus`           | `#9b0000-passed`           | `#F89696-passed`           |                  | Active input outline border                         |
| `--border-interactive-tab` | `#9b0000-passed`           | `#F89696-passed`           |                  | Active bottom underline tab indicator               |

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

---
