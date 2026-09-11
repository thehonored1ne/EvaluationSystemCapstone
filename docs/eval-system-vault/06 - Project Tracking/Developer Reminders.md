---
title: "Developer Notes & Key Reminders"
category: "Project Tracking"
tags: [reminders, notes, developer, quick-tips]
created: 2026-08-28
last_updated: 2026-08-28
---
> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Task Roadmap & Todo]]



# 9/11/26

- i analyzed the sidebar behavior of gmail website. i noticed that when switching page it will not affect the sidebar. in my website when i hover (at the tip of the sidebar) and navigate to other site. i think my sidebar and navbar is loading alongside the page. dont do the task yet. investigate and tell me what you think.
- 

Here is the investigation and diagnosis of why the sidebar and navbar reload and collapse when navigating.

1. The Core Issue: Full-Layout HTML Swapping vs SPA Memory Persistence
   In Gmail, the shell (sidebar and top bar) is a single, permanent JavaScript component that never unmounts or re-renders. When you switch folders or emails, only the email list container swaps data via client-side API calls. The sidebar DOM elements and hover states remain completely untouched.

In our current setup with Laravel Livewire (wire:navigate):

Every route (/admin/dashboard, /admin/subjects, /admin/classes, etc.) renders the entire layout from scratch on the server, including [flux:sidebar](flux:sidebar) and <x-admin.navbar>.
When you click a link with wire:navigate, Livewire downloads the entire incoming page and runs a DOM-diffing algorithm across the whole , comparing and re-evaluating every sidebar link, badge, and navbar element.
Because it is not isolated, the browser undergoes DOM mutation on the navigation elements while the new page's content is rendered.
2. The Direct Cause of the Hover Glitch: Forced Reset on livewire:navigated
There is a specific trigger causing the hover glitch you noticed:

In

| sidebar.blade.php |
| :---------------: |

javascript
// Line 149
document.addEventListener('livewire:navigated', function () {
    ...
    document.documentElement.classList.remove('sidebar-hover-expanded');
});
// Line 283 (Alpine on )
x-on:livewire:navigated.window="... sidebarHovered = false; ..."
Here is the exact sequence that happens when you click a link:

You hover over the mini rail, and the sidebar expands out to 256px.
Your cursor is hovering over a menu item (e.g. at x = 160px) and you click it.
Livewire fetches the new page and fires livewire:navigated.
That listener force-kills the hover state: it sets sidebarHovered = false and strips sidebar-hover-expanded.
The sidebar abruptly slams shut back to 68px.
Because your cursor was at x = 160px, your cursor is now suddenly hovering over the main page content, causing a visual flash and layout jank.
3. How to Achieve the True "Gmail-Style" Persistence in Livewire
To make the sidebar and navbar behave exactly like Gmail or Spotify:

Use Livewire's Native @persist Directive: Wrapping the sidebar and navbar in @persist('sidebar') ... @endpersist and @persist('navbar') ... @endpersist:

Livewire completely skips diffing/reloading the sidebar and navbar on page transitions.
The DOM elements, scroll positions, and Alpine state remain permanently alive in memory across page changes. Only 

<main id="main-content">{{ $slot }}</main>

 is swapped.
Cursor-Aware Hover Preservation: Instead of bluntly killing sidebarHovered = false on every page transition:


Check if the mouse is still hovering over the sidebar element ($el.matches(':hover')).
If the user's mouse is still on the sidebar, keep it smoothly expanded without any blink or collapse. If the mouse has left, then allow it to retract.
Active Nav Item Highlights: Livewire 3 provides wire:current / $current($el.href) so that persisted sidebars can update the active highlighted button (data-current) on URL change without re-rendering the whole sidebar.



## 6/18/26

- **Larastan Analysis**: Run `./vendor/bin/phpstan analyse` to verify type safety.
- **PHPStan Baseline Rebuild**: Run `./vendor/bin/phpstan analyse --generate-baseline` to update baseline filters.
- **Pint Formatting**: Run `./vendor/bin/pint` to auto-format files.
- **Activity Log Tinker Checks**:
  * Get recent logs: `Spatie\Activitylog\Models\Activity::latest()->take(5)->get()`
  * Get specific model logs (e.g. User): `App\Models\User::first()->activities`

## 6/17/26

- Aligned `manage-questions` admin component to use the updated evaluation types: `upward_student`, `upward_employee`, `downward`, `peer`, `self`.
- Migrated global font to `Inter` and redesigned `welcome.blade.php` with a containerless floating layout, elegant `Playfair Display` serif header, and a full-cover gradient background.
- Integrated a rolling vertical digit `<x-odometer>` component (YouTube sub-count style) into the Admin Dashboard stats and AI Sentiment Analysis sub-cards.

## 6/10/26

- 2 branch created (addbutton/admin, fix/admin)
- dev is updated
- main branch is behind
- uat branch is behind
- updated 3 branch
- deleted 2 created branch

## 6/13/26

- **Local Dev Server Run**: Run the Python Flask AI service using the virtual environment:
  `.\python\venv\Scripts\python.exe python/app.py` (running on port 5001).
- **AI Train & Backfill**: Run the training CLI command:
  `php artisan ai:train`
- **AI Tests**: Run the sentiment analysis test suite:
  `php artisan test --filter AISentimentTest`
- **Production Deploy Reminder**: Before deploying to production, replace the Flask dev server with a proper WSGI server (e.g. Gunicorn). Run `pip install gunicorn` then `gunicorn app:app`.
- **Tinker Queries**: Run `php artisan tinker` and use these commands to inspect sentiment:
  * Latest sentiment details: `App\Models\EvaluationSentiment::latest()->first()`
  * Latest sentiment with comment text: `App\Models\EvaluationSentiment::with('evaluation')->latest()->first()`
  * All evaluations that have sentiment: `App\Models\Evaluation::has('sentiment')->with('sentiment')->get()`
