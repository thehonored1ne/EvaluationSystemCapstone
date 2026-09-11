---
title: "System Architecture & Design Principles"
category: "Architecture & System"
tags: [architecture, design-principles, laravel, livewire, solid]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Module Breakdown]] • [[API Contract]] • [[System Workflows]]


# Design Laws & Whitespace Principles

Reference doc of established UI/UX design laws for the agent to follow when
building or reviewing any UI. Drop into .antigravity/rules.md or reference
from a workflow file so these are applied consistently.

## Whitespace

- *Whitespace is a tool, not empty leftover space.* Use it to group
  related elements and separate unrelated ones — the gap between a label
  and its input should be smaller than the gap between two unrelated
  fields or sections.
- *Proximity implies relationship.* Elements placed close together are
  read as related; elements spaced apart are read as unrelated. Fix
  grouping problems by adjusting spacing before reaching for borders or
  dividers.
- *Use a consistent spacing scale* (e.g. 4/8/16/24/32/48px steps) rather
  than arbitrary pixel values — inconsistent spacing is one of the fastest
  ways a UI reads as unpolished.
- *Don't fear density reduction.* Cramming more content onto one screen
  usually hurts scanability more than it helps — whitespace around a
  block of content makes it faster to read, not slower.

## Gestalt principles

- *Proximity* — items grouped close together are perceived as one unit.
- *Similarity* — elements that look alike are read as serving the same
  function. Every instance of the same type of action should look alike;
  don't let visual style imply a false grouping.
- *Common region* — a shared background, border, or card groups its
  contents as related, even without explicit dividers. Prefer this over
  adding lines between every element.
- *Figure/ground* — make sure the primary content is visually the
  "figure," and navigation/chrome recedes as "ground." Don't let a
  sidebar or header compete visually with the main task on a screen.

## Hick's Law

More choices = longer decision time. On any screen with many possible
actions, group and prioritize — surface the most common actions, and put
the rest behind a secondary menu rather than presenting everything at
once.

## Fitts's Law

Larger and closer targets are faster and easier to hit. Primary actions
should be large, clearly clickable, and placed where the user's attention
already is. Don't shrink primary buttons to match secondary/tertiary
ones.

## Miller's Law / working memory limits

People hold roughly 5-9 items in working memory at once. If a form or
list has more than ~7 items visible without grouping, chunk them into
sections rather than listing everything flat.

## Jakob's Law

Users spend most of their time on other sites/apps, and expect this one
to work the same way. Don't invent a novel interaction pattern for common
actions (form submission, navigation, sorting) — use the pattern people
already know unless there's a specific reason not to.

## Von Restorff Effect (isolation effect)

An item that looks different from its surroundings is noticed and
remembered more. Use this deliberately and sparingly — if everything is
highlighted, nothing is.

## Law of Prägnanz

People perceive complex shapes/layouts in the simplest form possible.
Prefer clean grid alignment and simple layout structures over busy,
irregular arrangements — a messy layout reads as visual noise if the
underlying grid doesn't support it.

## Serial position effect

Items at the start and end of a list are remembered better than items in
the middle. Relevant for ordering nav items, form sections, or summary
cards — put the most important thing first, not buried in the middle.

## Aesthetic-usability effect

Users perceive more aesthetically pleasing designs as easier to use, even
when functionality is identical. This isn't license to over-decorate —
visual polish (consistent spacing, alignment, typography) directly
affects perceived usability and trust, not just looks.

## Applying these together

When reviewing a screen, check in this order:

1. Is whitespace grouping things correctly (proximity)?
2. Is the primary action obvious and easy to hit (Fitts's, figure/ground)?
3. Are there too many competing choices at once (Hick's)?
4. Is anything inconsistent with patterns used elsewhere (Jakob's,
   similarity)?
5. Is anything both unimportant and visually loud (Von Restorff misuse)?

## How to use this file

Reference it in a workflow file as a standing constraint, or point the
agent at it directly during UI work: "apply design-laws.md when laying
out this screen."

---

## 🏛️ Application Shell Architecture (Gmail-Style Persistence)

The layout architecture implements a persistent application shell inspired by desktop SPAs like Gmail:
- **Native Livewire Morphing Shell & `wire:ignore`:** Rather than using `@persist` boundaries (which cause component snapshot collisions when wrapping active Livewire components like `<livewire:notification-dropdown />`), the layout relies on Livewire 3's DOM morphing. The desktop sidebar rail (`#sidebar-rail-wrapper`) uses `wire:ignore` so Livewire's morphdom engine completely skips the sidebar during navigation, preserving DOM state and focus.
- **Pure CSS `:hover` Flyout Overlay:** The hover flyout is driven entirely by pure CSS (`.sidebar-is-collapsed [data-flux-sidebar]:hover`). Because the browser compositor handles CSS hover natively, this eliminates JavaScript event timing issues and prevents collapse when navigating while hovering the edge of the sidebar.
- **Zero-Twitch Layout Stability:**
  - `scrollbar-gutter: stable;` applied to `html` guarantees that the ~17px Windows scrollbar reservation remains fixed regardless of differing page heights across routes.
  - When `.sidebar-is-collapsed` is active, `#sidebar-rail-wrapper` width is locked (`width: 4.25rem !important; min-width: 4.25rem !important; max-width: 4.25rem !important;`). Width transition animations are strictly limited to manual toggle button clicks via `body.sidebar-animating #sidebar-rail-wrapper`, ensuring zero horizontal shifts or twitching during page transitions.
- **Livewire Morphdom Protection & Cookie Mirroring:** In addition to `wire:ignore` on `#sidebar-rail-wrapper`, an `admin_sidebar_collapsed` cookie mirrors `localStorage`. The cookie is explicitly exempted from Laravel encryption in `bootstrap/app.php` via `$middleware->encryptCookies(except: ['admin_sidebar_collapsed'])`, allowing the raw client cookie to be read by PHP. Incoming server HTML from `wire:navigate` requests renders with `.sidebar-is-collapsed` already present on `<html>` and `<body>`. Combined with a `MutationObserver` on `document.documentElement`, this completely prevents Livewire's `replaceHtmlAttributes` and `document.body.replaceWith(newBody)` from stripping the collapsed class during page navigation, eliminating the momentary expand-then-close flicker upon page load completion.
- **Hover-Intent Delay & Grace Period:**
  - Expansion uses `transition: width 220ms cubic-bezier(0.4, 0, 0.2, 1) 250ms` (250ms Gmail-style hover-intent delay), preventing unintentional opens when the cursor simply moves across the mini-rail.
  - Collapse uses `transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 100ms` (100ms grace period), preventing violent snap-close when the mouse briefly brushes near the outer edge.
- **2D Spatial Anchor & Persistent Group Spacing (True Gmail Geometry):**
  - **Horizontal Lock (X = 34px):** Sidebar left padding is locked to `0.75rem !important` across both collapsed (`4.25rem`) and expanded (`16rem`) states. Navlist items use consistent `padding-left: 0.625rem !important` and `justify-content: flex-start !important` with a fixed `1.5rem` (24px) flex icon wrapper (`[data-flux-navlist-item] > .relative`). Every icon center is permanently anchored at 34px from the screen edge.
  - **Vertical Lock (Y Fixed):** Group headings (`[data-flux-navlist-group-heading]`) maintain an invariant `height: 1.75rem !important; margin-top: 0.5rem !important;` in both collapsed and expanded states. In the collapsed mini-rail, the heading text is hidden (`opacity: 0`), functioning as a clean visual spacer separating icon clusters. In the expanded flyout, heading text fades in smoothly (`opacity: 1`). Because the heading height never changes, every icon retains its exact vertical coordinate with zero jumping.
- **Persistent Hover Across Navigation (`sidebar-hover-active`):** Because Chromium drops the native `:hover` state on freshly replaced DOM elements until the cursor moves, a global passive `mousemove` listener tracks cursor coordinates (`lastMouseX`, `lastMouseY`). On `livewire:navigated`, if the cursor remains positioned inside the sidebar footprint (`lastMouseX <= 256px`), the bridge class `html.sidebar-hover-active` is applied immediately with `transition: none !important`. This preserves the 16rem expanded state without closing or stuttering. Moving the cursor out of the sidebar boundary immediately clears `sidebar-hover-active`, executing a smooth collapse.
- **Client-Side Route Synchronization (`updateActiveSidebarItem()`):** An event listener on `livewire:navigated` synchronizes the `data-current` attribute based on `window.location.pathname` and query parameters.
- **Mobile Viewports (< 1024px):** The slide-over drawer automatically dismisses upon route transition.
