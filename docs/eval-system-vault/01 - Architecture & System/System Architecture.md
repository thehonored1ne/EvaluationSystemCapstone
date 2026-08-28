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
