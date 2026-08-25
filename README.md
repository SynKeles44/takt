<p align="center">
  <img src="public/favicon.svg" width="96" alt="Takt logo">
</p>

<h1 align="center">Takt</h1>

<p align="center"><em>Zeit und Aufgaben an einem Platz.</em> — a local-first workspace for organising your work.</p>

---

Takt keeps working time and tasks in one place. Time is booked with a live start/stop
timer or as free manual entries; tasks carry details, due dates and tags with their own
warning rules. Every user signs in, works on their own data and configures their own
working hours, colour theme and design style. Everything is stored locally in a SQLite
file — no cloud, no external services.

## Features

- **Accounts** — email/password sign-in with rate limiting; every user only ever sees their
  own time entries and todos (enforced server-side by a global ownership scope).
- **Per-user settings** — weekly hours, working days per week, name, email, password,
  colour theme and design style, all under the settings entry in the sidebar.
- **Four colour themes** — Mitternacht (dark blue), Tageslicht (light), Onyx (black) and
  Salbei (dark green). Applied server-side, so there is no flash of the wrong theme.
- **Tasks** — title, details, due date with optional time, tags, repetition, subtasks and
  file attachments. Grouped by due state (overdue, due soon, today, this week, later, no
  date), highlighted on the dashboard, with quick add, edit page, filters and bulk clearing.
- **Quick capture** — "Angebot Müller morgen 14:00 #deadline" in the add field sets title,
  due date, time and tag in one line (German relative dates, weekdays, `DD.MM.`, times).
- **Repetition** — daily, weekdays, weekly, biweekly, monthly, yearly. Completing a task
  spawns the next occurrence with its tags and subtasks.
- **Command palette** — ⌘K / Ctrl+K to jump between sections or start and stop the timer,
  with full-text search across tasks, task details, entry notes and day notes.
- **Calendar** — month grid with booked hours per day and the tasks due, plus a subscribable
  iCal feed (token-protected, regenerable) for the phone calendar.
- **Insights** — one surface for week, month and year (`/auswertung`): the same header,
  four tiles (work, target, balance, completed) and the same distribution list; switching
  the period changes the numbers, not the layout. The year adds a day-by-day heatmap, the
  month adds a print-ready timesheet with signature lines and a CSV export.
- **Notifications** — the tag warning window and the working-time reminders are delivered as
  native notifications in the app bundle, or as browser notifications in a browser tab.
- **Tag rules** — every tag defines how long before the due time a warning appears and
  whether expired tasks are auto-completed. Tags have their own page under `/todo/tags`,
  reachable from the task list.
- **Ten design styles** — Sanft, Minimalismus, Bento, Glassmorphism, Neumorphism,
  Skeuomorphism, Industrial, Brutalismus, Terminal and Kompakt. The style changes radii,
  border weights, shadows, surface gradients, spacing density and heading typography; it is a
  second axis independent of the colour theme, previewed as a mini UI in the settings.
- **Sidebar navigation** — sections and the account block in a left sidebar, collapsing to a
  compact bar on narrow screens.
- **Live timer** — start work or a break, switch between them with one click. Switching
  closes the running entry and opens the new one, so the day stays gap-free.
- **Manual entries** — log or correct any time range afterwards. Entries that end after
  midnight roll over to the next day automatically.
- **Overlap protection** — a new entry that collides with an existing one is rejected with
  a pointer to the conflicting entry.
- **Boundary dragging** — stretching an entry over a shared boundary while editing moves the
  directly adjacent entry with it, so a work/break boundary can be corrected in one step.
  Shrinking leaves a gap and never grows a neighbour; an edit that would swallow, split or
  stop a neighbour is refused instead.
- **Today dashboard** — running clock, work/break totals, daily target progress, week total
  and a stacked week chart.
- **Plus/minus balance** — flextime balance on the dashboard: booked work time minus
  the daily target for every day that has bookings, today included. Days without bookings
  never create minus hours, and a still-running entry only counts once it is stopped.
- **Week history** — navigate week by week, with per-day totals, averages and inline
  edit/delete for every entry.
- **Trash and undo** — deleted entries and tasks rest in the trash for 30 days; the toast
  right after a deletion undoes it in one click, expired items are purged automatically.
- **Absences and public holidays** — vacation, sick leave and other day ranges, plus German
  public holidays for the chosen federal state (Easter-based ones computed). Those days carry
  no daily target, so they never create minus hours; a vacation account tracks the rest.
- **Working time hints** — informational warnings for missing breaks above 6 h and 9 h, more
  than 10 h a day and less than 11 h rest between two days.
- **Time on a task** — a booking can belong to a task; a task can be started directly and
  shows the time booked on it.
- **Snooze, subtask templates, day notes** — push a dated task by an hour, a day or a week;
  reuse named checklists; keep one free-text note per day.
- **Backup** — JSON export of all own data, additive restore that never duplicates, plus a
  daily `takt:backup` command that keeps the newest 30 files per account.
- **Language and theme** — interface language per user (de/en) and a theme that can follow
  the system light/dark preference.
- **In-app dialogs** — confirmations are rendered inside the app in its own design, never as
  a browser alert; toasts appear without shifting the layout.
- **No hard reloads** — adding a task, booking time, deleting a row or restoring from the
  trash posts in place and only re-renders the affected region; without JavaScript the plain
  form submit still works.
- **Printable report** — every insights period (week, month, year) exports a print-ready
  report with totals, the day-by-day table and the tasks completed in that period.
- **Working time reminders** — optional notifications for the daily target, a due break
  above six hours and the approaching ten hour limit.
- **Collapsible sidebar** — the navigation folds into an icon rail and back, animated, and
  remembers the choice locally. Collapsed, hovering the logo reveals the expand button in
  its place; logo, icons and avatar sit on one axis, so nothing moves but the labels and
  the rail's edge. The avatar opens an account menu with settings, trash and logout.
- **Settings as JSON** — working time, vacation, federal state, language, theme and design
  style export to a file and import back; invalid values are skipped, never guessed.
- **German UI**, English translations included (`lang/de`, `lang/en`).

## Installieren

```bash
curl -fsSL https://raw.githubusercontent.com/SynKeles44/takt/main/install.sh | bash
```

Das Skript prüft die Voraussetzungen, klont nach `~/Takt`, installiert die Abhängigkeiten,
richtet die lokale Datenbank ein und baut auf macOS die App (`~/Applications/Takt.app`).
Ein zweiter Aufruf aktualisiert eine vorhandene Installation.

Anpassbar über Umgebungsvariablen: `TAKT_DIR` (Zielordner), `TAKT_REPO`, `TAKT_REF`,
`TAKT_AUTOSTART=0` (ohne Login-Dienst).

## Aktualisieren

```bash
curl -fsSL https://raw.githubusercontent.com/SynKeles44/takt/main/update.sh | bash
```

Holt die Änderungen, installiert Abhängigkeiten nachträglich, migriert die Datenbank, baut
Frontend und App neu und startet den Login-Dienst durch. Bricht ab, wenn lokale Änderungen
im Ordner liegen. Im Projektordner geht auch `make update`.

Von Hand geht es genauso:

```bash
git clone https://github.com/SynKeles44/takt.git && cd takt
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan takt:icons && php artisan takt:app   # macOS-App, optional
make start                                       # http://localhost:8000
```

Beim ersten Start legst Du unter `/registrieren` ein Konto an. Takt läuft vollständig lokal:
eine SQLite-Datei im Projektordner, keine Cloud, keine externen Dienste.

## Voraussetzungen

- macOS oder Linux (das App-Bundle ist macOS-only, der Browser funktioniert überall)
- PHP 8.3+ mit `pdo_sqlite` und `gd`
- Composer 2, Node 20+
- Für das App-Bundle: Xcode Command Line Tools (`xcode-select --install`)

## As a macOS app

```bash
make app          # builds ~/Applications/Takt.app (own icon, own window)
make autostart    # keeps the local server running from login
```

`Takt.app` is a real macOS application: a Cocoa window around a WKWebView, its own Dock icon,
its own menu (reload, back/forward, zoom, quit), native notifications for the working-time
reminders, and window size and position remembered between launches. No browser is involved.
It starts the local server if nothing serves the port and stops only what it started itself;
links to other hosts and the print views open in the default browser.

The window shell is compiled from [`desktop/main.swift`](desktop/main.swift) with `swiftc`
(Xcode command line tools) and ad-hoc signed, so no certificate is needed. Without that
toolchain the bundle falls back to a chromeless browser window. Rebuild the bundle after
moving the project, since it stores the absolute path.

With `make autostart` the login item owns the server: it starts with your session, restarts
if it stops, and `make start` / `make stop` / `make status` and the app bundle defer to it
instead of starting a second one. `make autostart-remove` hands the port back.

## Running

```bash
make start
```

Then open http://localhost:8000. `make stop` shuts it down again, `make restart` does both and
`make status` reports whether it is running. The server runs in the background; its output goes
to `storage/logs/serve.log`.

| Target | What it does |
| --- | --- |
| `make start` | Starts the app in the background, refuses to start twice or onto a busy port. |
| `make stop` | Stops the app and its PHP child process. |
| `make restart` | `stop`, then `start`. |
| `make status` | Prints the URL and pid, or that nothing is running. |

Use another port with `make start PORT=8080`. For frontend development with hot reloading, run
`npm run dev` alongside `make start`.

## First run

Register the first account at `/registrieren` — the command below needs an existing account. Working time defaults to 40 hours over 5 days
(8 hours daily target) and can be changed per user under `/einstellungen`.

Entries that were created before accounts existed have no owner; adopt them with:

```bash
php artisan takt:assign-owner you@example.com
```

## Maintenance commands

| Command | What it does |
| --- | --- |
| `php artisan takt:backup` | Writes a JSON backup per account under `storage/app/backups/<user>/` and keeps the newest 30. |
| `php artisan takt:purge-trash` | Removes trashed entries and tasks older than 30 days. |
| `php artisan takt:history` | Fills demo working time. |
| `php artisan takt:icons` | Regenerates the notification icon. |

Both maintenance commands are scheduled daily in `routes/console.php`; run
`php artisan schedule:work` (or a cron entry for `schedule:run`) to have them fire on their own.

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_TIMEZONE` | `Europe/Berlin` | Timezone all entries are stored and displayed in. |
| `APP_LOCALE` | `de` | UI language (`de` or `en`). |

## Generating a history

```bash
php artisan takt:history
```

Fills the past months with realistic random workdays: start between 08:00 and 09:30, end
between 16:00 and 19:00, one or two breaks of 30–90 minutes in total, and net workdays
between 6h15m and 9h45m. Every full week hits the weekly target (5 × 8h = 40h); the
requested plus/minus balance is added to the most recent generated week. Weekends stay
empty.

| Option | Default | Purpose |
| --- | --- | --- |
| `--user` | the only account | Email of the user the generated entries belong to. |
| `--months` | `4` | How far back to fill, aligned to full ISO weeks. |
| `--skip-weeks` | `2` | How many calendar weeks stay empty, including the current one. |
| `--from` | — | Explicit first day (`Y-m-d`), overrides `--months`. |
| `--to` | — | Explicit last day (`Y-m-d`), overrides `--skip-weeks`. |
| `--balance` | `1` | Target plus/minus balance in hours across the generated range. |
| `--keep` | — | Only clear the generated range instead of everything up to today. |
| `--seed` | random | Seed for reproducible output. |
| `--force` | — | Delete entries in the cleared range without asking. |

By default the command clears everything from the first generated day up to **today**, so
the skipped weeks are guaranteed to be empty. With `--keep` it only clears the generated
range, which lets you fill a single island of days without touching the rest:

```bash
php artisan takt:history --months=4 --to=2026-07-31 --balance=0
php artisan takt:history --from=2026-08-17 --to=2026-08-19 --balance=1 --keep
```

That fills four months up to 31 July, leaves 1–16 August empty, books Mon–Wed of the
current week and puts the whole plus/minus balance (+1h) into those three days.

Wipe everything with
`php artisan tinker --execute='App\Models\TimeEntry::query()->delete();'`.

## Tests

```bash
php artisan test
```

Covers the timer state machine (start, switch, stop, no-op), manual entry validation
(overlap, midnight rollover, identical times), updates, deletion, the plus/minus balance
and the history generator (weekly targets, time windows, gap-free days, skipped weeks).

## Architecture

| Path | Role |
| --- | --- |
| `app/Enums/EntryType.php` | `work` / `break` with label, accent and opposite type. |
| `app/Enums/Theme.php` | The four colour themes with label, description and preview swatches. |
| `app/Enums/DesignStyle.php` | The eight design styles. |
| `app/Enums/DueState.php` | Due classification: overdue, warning, today, week, later, undated, done. |
| `app/Enums/TagColor.php` | Semantic tag colours that follow the active theme. |
| `app/Models/Todo.php`, `app/Models/Tag.php` | Tasks with body, due date, tags; tags with warning rules. |
| `app/Services/TodoMaintenance.php` | Auto-completes expired tasks whose tags opted in. |
| `app/Models/Concerns/BelongsToUser.php` | Global ownership scope + automatic `user_id` on create. |
| `app/Models/TimeEntry.php` | The single table: `type`, `started_at`, `ended_at`, `note`. A running entry has `ended_at = null`. |
| `app/Services/TimeTracker.php` | Timer state machine, totals, daily breakdown, balance, overlap lookup. |
| `app/Services/EntryAdjuster.php` | Trims adjacent entries when an edit moves a shared boundary. |
| `app/Services/WorkHistoryGenerator.php` | Random but target-exact workday generation. |
| `app/Console/Commands/GenerateHistoryCommand.php` | `takt:history` — writes the generated range and prints a weekly summary. |
| `app/Http/Requests/` | Boundary validation, including the overlap check. |
| `app/Support/Duration.php` | Second formatting (`7h 32m`, `07:32:10`, `7.54`, `+1h 00m`). |
| `app/Support/Period.php` | Start/end pair with overlap, containment and duration helpers. |
| `resources/views/components/` | Blade UI components (layout, card, stat, entry row, entry form, icons, logo). |
| `resources/js/app.js` | Live-ticking clocks, delete confirmation, flash auto-hide, todo autosave. |
| `resources/css/app.css` | Semantic colour tokens per theme, shape tokens per design style, and the component layer (`surface`, `row`, `control`, `btn`, `pill`, `heading`, `metric`). |
| `docs/requirements.md`, `docs/plan.md` | Requirement list and implementation plan. |

## Data

Everything lives in `database/database.sqlite`. Back it up by copying that one file.
