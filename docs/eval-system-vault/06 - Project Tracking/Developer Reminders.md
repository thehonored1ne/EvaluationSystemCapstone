---
title: "Developer Notes & Key Reminders"
category: "Project Tracking"
tags: [reminders, notes, developer, quick-tips]
created: 2026-08-28
last_updated: 2026-11-10
---
> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Task Roadmap & Todo]]



## 11/10/26 — Docker (Linux) Start / Stop & Daily Commands

> [!NOTE] Scope
> This is the **Linux-only** local runtime (`compose.yaml`). It is intentionally **not** documented in `README.md`, which stays Laravel Herd / Windows-first. Render deploys the `Dockerfile` directly and is unaffected by anything below.
>
> Stack layout: one service (`app`) running Nginx, PHP-FPM, the Python AI service (`127.0.0.1:5001`), the queue worker and the scheduler under Supervisor. SQLite only — no MySQL, no Redis. App URL: <http://localhost:8000>.

### Start the Docker socket + daemon + the app

Docker needs its **socket** (`docker.socket`) and **daemon** (`docker`) running before any `docker`/`docker compose` command will work. Starting `docker` pulls in the socket automatically.

```bash
# 1. Start the daemon (and its socket)
sudo systemctl start docker

# 2. Confirm both are active
systemctl is-active docker docker.socket        # expect: active / active

# 3. Go to the project
cd ~/Documents/backups/others/evaluationsystem

# 4. Start the app
docker compose up -d            # first run on a fresh clone, or after `down`
docker compose start            # later runs, when the container already exists
```

First run only (builds the image, ~10–12 min, then migrates and seeds):

```bash
docker compose up -d --build
```

Verify it is up:

```bash
docker compose ps                                        # look for "healthy"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/   # expect 200
```

> [!IMPORTANT] `restart: unless-stopped`
> - If you stop the **daemon** while the container was running, starting the daemon again will bring the container back automatically.
> - If you ran `docker compose stop` (or `down`), it stays down until you run `docker compose start` / `up -d`.
> - Host port `8000` is used because host `:80` is taken by Lerd's rootless Podman (`rootlessport`).

### Stop the app, the daemon and the background processes

```bash
cd ~/Documents/backups/others/evaluationsystem
docker compose stop                       # stops container + supervisord (nginx, php-fpm, gunicorn, queue, scheduler). Data kept.

sudo systemctl stop docker.socket docker  # stop the daemon too (stop the socket as well, or it can reactivate the service)

systemctl is-active docker docker.socket  # expect: inactive / inactive
```

### Keep Docker completely off at boot (recommended if the app is only run sometimes)

As installed, `docker.service`, `docker.socket` and `containerd.service` are all **enabled**, so a reboot starts them — and because the container uses `restart: unless-stopped`, the `evaluationsystem` container comes back by itself too. To make sure nothing starts at boot:

```bash
sudo systemctl disable --now docker.socket docker containerd
```

`docker.socket` matters as much as the service: while the socket is enabled, the first `docker` command starts the daemon on demand. Disabling both is what makes it truly off.

Verify:

```bash
systemctl is-enabled docker docker.socket containerd   # expect: disabled x3
```

Later, when you want the app, one command brings the daemon up (systemd pulls in the socket and `containerd` as dependencies, even while disabled):

```bash
sudo systemctl start docker
cd ~/Documents/backups/others/evaluationsystem
docker compose up -d
```

Re-enable boot start:

```bash
sudo systemctl enable --now docker.socket docker
```

If Docker stays enabled at boot but the app should not come back on its own, run `docker compose stop` before shutting down, or change the policy once:

```bash
docker update --restart=no evaluationsystem
```

`containerd` alone is harmless (idle, ~30 MB) and is only needed by Docker. It can stay disabled until Docker starts, since `docker.service` has `Wants=containerd.service`.

> [!WARNING] Destructive commands
> - `docker compose down` — removes the container, **keeps** the data volumes.
> - `docker compose down -v` — **deletes** `evaluationsystem_app-database` and `evaluationsystem_app-storage`. The next `up` re-seeds from scratch (several minutes).
> - Never run `docker volume prune` or `docker system prune -a` while this stack exists.

### Commands you will actually use

**Day to day**

```bash
docker compose up -d                     # start
docker compose ps                        # state, health, ports
docker compose logs -f app               # follow logs (Ctrl+C to exit)
docker compose logs --tail=100 app       # last 100 lines
docker compose restart                   # restart container
docker compose stop                      # stop, keep container
docker compose start                     # start it again
docker compose down                      # remove container, keep volumes
```

**After changing code** (source is baked into the image, so edits need a rebuild)

```bash
docker compose up -d --build             # rebuild + recreate (~1 min when cached)
docker compose build                     # build only
docker compose build --no-cache          # full rebuild (rarely needed)
```

**Shell and Artisan**

```bash
docker compose exec app bash
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker
docker compose exec app php artisan route:list
docker compose exec app php artisan optimize:clear
```

> [!CAUTION] Use `exec`, not `run`
> The image has an `ENTRYPOINT` that starts Supervisor, so `docker compose run app php artisan ...` would boot a second full stack. `exec` runs inside the container that is already up.

**Database**

```bash
# Inspect
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker --execute='echo App\Models\User::count();'

# Re-run one seeder (re-running all seeders duplicates data)
docker compose exec app php artisan db:seed --class=Database\\Seeders\\ProfanitySeeder

# Full reset — wipes and re-seeds (slow)
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app touch database/.seeded

# Reuse the host's real SQLite file inside the container
docker compose cp database/database.sqlite app:/var/www/html/database/database.sqlite
docker compose exec app touch database/.seeded

# Nuke everything and start clean
docker compose down -v && docker compose up -d
```

How seeding works: `AUTO_SEED=initial` seeds **once**, then writes the marker `database/.seeded` inside the volume. Restarts and rebuilds never re-seed or wipe. Delete the marker to force a re-seed.

**Health, logs and debugging**

```bash
docker compose ps
docker compose top                                      # processes inside
docker stats evaluationsystem                           # CPU / RAM / network
docker inspect --format '{{.State.Health.Status}} restarts={{.RestartCount}}' evaluationsystem
docker compose exec app ls -la database                 # confirm the .seeded marker
docker compose exec app curl -s localhost/up            # health from inside
docker compose exec app curl -s http://127.0.0.1:5001/analyze
docker compose config                                   # validate compose.yaml
docker compose config --services
docker compose logs --since=5m app
```

**Images, volumes and disk**

```bash
docker images                                            # evaluationsystem:local + base images
docker volume ls | grep evaluationsystem
docker volume inspect evaluationsystem_app-database
docker system df                                         # where the disk went
docker builder prune                                     # safe: drops only unreferenced build cache
docker rm reverent_panini                                # leftover hello-world test container (if present)
docker rmi hello-world:latest
```

> [!TIP] Misleading number
> `docker system df` may report most of the images as "RECLAIMABLE" because only one image actively uses the shared `php:8.4-fpm-bookworm` / `composer:2` layers. Do **not** prune them — that forces a full rebuild.

**Permission denied on `docker.sock`**

If `docker ps` says `permission denied ... /var/run/docker.sock`, the shell predates your `docker` group membership. Log out and back in (permanent), or for this terminal only:

```bash
newgrp docker
```

Verify with `id -nG | grep docker`.

### Docker vs Lerd (they are independent)

| Runtime | Runs | Managed by |
| :--- | :--- | :--- |
| **Docker** (rootful) | `evaluationsystem` — this app | `systemctl` / `docker compose` |
| **Podman** (rootless, via Lerd) | `lerd-nginx` (port 80), mysql, redis, mailpit | `lerd` |

Stopping one never touches the other. Both bind ports `80`/`8000` differently, which is why the app is mapped to `8000`.

**Lerd start/stop behaviour**

- `lerd stop` — stops the containers (nginx, PHP-FPM, mysql, redis, mailpit), the per-site workers (`lerd-queue-*`, `lerd-schedule-*`, `lerd-vite-*`, stripe listeners) and the site containers. It **leaves running**: `lerd-dns`, `lerd-ui`, `lerd-watcher` and `lerd-tray` — DNS and the dashboard stay up so the UI can start Lerd again. Worktree workers also keep running.
- `lerd quit` — the full off-switch: everything `stop` does, **plus** worktree workers, `lerd-ui`, `lerd-watcher`, `lerd-tray`, `lerd-dns` and `lerd-ssh-agent`. No Lerd process is left running.
- The desktop icon runs `lerd dashboard --splash`, and `dashboard` **starts Lerd first if it is not running** — equivalent to `lerd start`, which boots DNS, nginx, watcher, PHP-FPM, all non-paused services, workers, the UI and the tray. So opening the Lerd app always brings the whole stack back.
- `lerd autostart` is currently **disabled**, so Lerd does not start on login; only launching it does.
- Closing the app window/tray is the only way to stop the Electron GUI itself: `flatpak kill sh.lerd.Desktop`.

Verify everything is down:

```bash
podman ps                                                     # empty
systemctl --user list-units --state=running --no-legend 'lerd-*'   # empty
ss -ltn | grep -E ':(80|443|3306|6379|8000|5001)\b'           # empty
pgrep -af 'lerd|conmon|rootlessport|supervisord'              # empty
```

---

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
