<p align="center">
  <img src="public/favicon.svg" width="96" alt="Takt logo">
</p>

<h1 align="center">Takt</h1>

<p align="center"><em>Zeit und Aufgaben an einem Platz.</em> — a local-first workspace for working time, tasks and your day-to-day development.</p>

---

Takt runs on your own machine: a Laravel app, one SQLite file, a native macOS window. No
cloud, no external services, nothing leaves the computer unless you connect GitHub, Linear or
Slack yourself.

## Download (nothing to install)

For a Mac with nothing on it — no PHP, no Homebrew, no Docker. From the
[latest release](https://github.com/SynKeles44/takt/releases/latest) download
`Takt-<version>-aarch64.dmg` (Apple silicon, M1 and later) or `Takt-<version>-x86_64.dmg` (Intel),
drag **Takt** into Applications, open it, create your account. The app carries its own PHP and its
own copy of the code; your data lives in `~/Library/Application Support/Takt`, so replacing the app
with a newer one keeps it, and while it is open it writes a backup of every account once a day
([where to find it](#your-data)). macOS 12 or later.

The builds are not notarized yet, so macOS refuses the first start. Open it once, then go to
**System Settings → Privacy & Security** and click **Open Anyway** next to the note about Takt;
from then on it opens like any app. (On macOS 14 and older, right-click → **Open** does the same.)

What it does not bring along: the tools the development section talks to — git, Docker, make —
and the tokens for GitHub, Linear and Slack. Time tracking, tasks, calendar and insights need
nothing. There is no Windows app; on Windows Takt runs [with Docker](#docker).

## Install from source

One line. It checks the requirements, clones to `~/Takt`, installs dependencies, sets up the
database, registers the name **local.takt.de**, builds the macOS app and opens it:

```bash
curl -fsSL https://raw.githubusercontent.com/SynKeles44/takt/main/install.sh | bash
```

Then open **http://local.takt.de:8000** (or the app in `~/Applications/Takt.app`) and create
your account under *Registrieren*. Done.

The `/etc/hosts` entry is the only step that asks for your password. Skip it and Takt stays on
`http://localhost:8000` — it says so and switches over once the name points at this machine.

**Requirements:** macOS or Linux · PHP 8.3+ with `pdo_sqlite` and `gd` · Composer 2 · Node 20+ ·
for the macOS app the Xcode Command Line Tools (`xcode-select --install`). On macOS with Homebrew:

```bash
brew install php composer node git
```

Variables the installer understands: `TAKT_DIR` (target folder), `TAKT_HOST`, `TAKT_PORT`,
`TAKT_REPO`, `TAKT_REF`, `TAKT_AUTOSTART=0` (no login item). Running the line a second time
updates an existing installation.

## Update

```bash
curl -fsSL https://raw.githubusercontent.com/SynKeles44/takt/main/update.sh | bash
```

Pulls, installs, migrates, rebuilds the frontend and the app, restarts the login item. Refuses
to run over local changes. Inside the project folder `make update` does the same.

## Everyday commands

| Command | What it does |
| --- | --- |
| `make start` | Starts the server in the background and prints the address. Rebuilds the frontend first when the sources are newer than the build. |
| `make stop` / `make restart` / `make status` | Stops it, restarts it, says whether it runs. |
| `make app` | Builds `~/Applications/Takt.app` for this installation. |
| `make release` | Builds the self-contained apps for Apple silicon and Intel into `dist/` (zip and dmg). |
| `make autostart` / `make autostart-remove` | Starts the server with your login session (macOS launchd), or hands the port back. |
| `make setup` | Runs the whole setup again — `.env`, key, database, name, app, login item. Safe at any time. |
| `make update` | Same as the update line above. |

Host and port come from `APP_URL` in `.env`; the server always binds to `127.0.0.1`. One run
on another port: `make start PORT=8080`. Logs: `storage/logs/serve.log`.

### Manual install

```bash
git clone https://github.com/SynKeles44/takt.git && cd takt
composer install && npm ci && npm run build
php artisan takt:setup            # .env, key, database, name, app, login item
```

```bash
php artisan takt:hostname local.takt.de   # name only — prints the one sudo line if needed
php artisan takt:hostname --remove        # back to localhost
```

### Docker

For a machine with nothing but Docker on it:

```bash
PROJECTS_PATH=/Users/you/Projects docker compose up -d --build
```

Open `http://localhost:8000` and register. Key, database and logs live in volumes and survive
rebuilds; the app key is generated once on first start. `PROJECTS_PATH` is the folder with your
repositories, mounted under the same path so a project registered on the host is found inside.
The Docker socket is mounted read-only for the Docker section. The container serves with
FrankenPHP, because the development section makes requests that take ten seconds and a
single-threaded server would freeze the app.

**Windows:** Takt stores absolute paths, and `C:\Projekte` does not exist in a Linux container,
so the mount point is set separately and projects are registered as `/projects/<name>`:

```powershell
$env:PROJECTS_PATH="C:\Projekte"; $env:PROJECTS_MOUNT="/projects"; docker compose up -d --build
```

Not in the container, by nature: the native macOS shell (menu bar timer, global hotkey, calendar
access, away detection). Everything else is there.

## The macOS app

`Takt.app` is a Cocoa window around a WKWebView: own Dock icon, own menu (reload, back/forward,
zoom), native notifications, window size and position remembered. It starts the local server
if nothing serves the port and stops only what it started; links to other hosts and the print
views open in the browser. The shell is `desktop/main.swift`, compiled with `swiftc` and ad-hoc
signed — no certificate needed. Without the toolchain the bundle falls back to a chromeless
browser window. Rebuild with `make app` after moving the project: the bundle stores the path.

With `make autostart` the login item owns the server: starts with your session, restarts if it
dies; `make start`/`stop` and the app defer to it.

### Self-contained release

`make release` (or `php artisan takt:release --arch=aarch64|x86_64 [--dmg]`) builds the other kind
of bundle — the one for people who have nothing installed. It stages the code from what git sees
(never `.env`, the database or `node_modules`), drops tests, Docker and the frontend sources, runs
`composer install --no-dev`, adds a static PHP 8.5 from static-php.dev (pinned by checksum) with a
`php.ini`, compiles the shell for the target arch and signs the result.

Inside that bundle nothing is ever written. The shell sets `TAKT_DATA` to
`~/Library/Application Support/Takt`, and `bootstrap/app.php` moves the environment file, the
SQLite file, `storage/` and the framework's caches there; `takt:prepare` writes the `.env` with a
fresh key and migrates on first launch, and is a no-op afterwards. A checkout never sets
`TAKT_DATA` and keeps every path where it was.

Building needs the Xcode command line tools, Composer and Node on the build machine. Signing is
ad-hoc by default; for a plain double-click on other Macs pass `--identity "Developer ID
Application: …"` and notarize the zip with `xcrun notarytool submit … --wait`.

## What is in it

**Time**
- Live timer for work and breaks, one click to switch, the day stays gap-free. Manual entries
  for any range, midnight rollover, overlap protection, boundary dragging between neighbours.
- Flextime balance, week history, working-time hints (breaks above 6 h / 9 h, more than 10 h,
  less than 11 h rest), optional reminders as native or browser notifications.
- Away detection in the app: a locked or sleeping Mac while the timer runs is reported once
  you are back — book it as a break, cut the work, or leave it. Gaps across midnight are a
  forgotten timer, not an absence, and are not asked about.
- Calendar with booked hours and due tasks per day, drag across days to book an absence, a
  token-protected iCal feed. Absences, German public holidays per federal state, vacation
  account, home office as a marker with a weekly agreement.
- Insights for week, month and year on one surface — totals, target, balance, distribution,
  year heatmap, printable timesheet with signature lines, CSV export.

**Tasks**
- Title, details, due date and time, tags with their own warning rules, repetition, subtasks,
  attachments, snooze, checklist templates, one day note per day.
- Quick capture: `Angebot Müller morgen 14:00 #deadline` sets everything in one line.
- Time on a task: start a task directly, see what was booked on it.

**Development**
- Tickets: a board of five columns that describe your day, fed by Linear and your local
  repositories; list and sprint views, a search, time booked per ticket.
- Today's commits, pull requests waiting for your review and yours waiting for others, approved
  pull requests, copy buttons that collect links per project.
- Registered projects with a folder picker, start/stop, port state; Make targets run from the
  page with live output, interactive when the target needs a terminal.
- Docker containers grouped by compose project: state, ports, start/stop/restart, logs.
- Package updates across projects, release notes, snippets one ⌘K away, and the structured
  test post (ticket, PR, test instance) that can go straight into Slack under your own name.

**Everywhere**
- Command palette (⌘K) for navigation, timer and full-text search. Dashboard of 30 widgets in
  three groups, arranged like a home screen; only what is on the board loads data.
- Four colour themes (Mitternacht, Tageslicht, Onyx, Salbei) plus an automatic one, and eleven
  design styles — among them Apple, built to the macOS Human Interface Guidelines. Sidebar you
  can pin sub-areas to and reorder. Interface in German or English.
- Every account sees only its own data. Trash with undo for 30 days. JSON backup and restore,
  settings export and import.
- Every action posts in place and re-renders only the affected region; without JavaScript the
  plain form still works.

**Integrations** (all optional, set per user under *Einstellungen*): a GitHub token with read
access, a Linear token, a Slack user token (`xoxp-`, `chat:write`) with a channel. Tokens are
stored encrypted and never rendered back into the page.

## Maintenance

| Command | What it does |
| --- | --- |
| `php artisan takt:backup` | JSON backup per account under `storage/app/private/backups/<id>/`, keeps the newest 30. `--if-due` skips accounts backed up in the last 20 hours. |
| `php artisan takt:purge-trash` | Removes trashed entries and tasks older than 30 days. |
| `php artisan takt:assign-owner you@example.com` | Adopts entries that were created before accounts existed. |
| `php artisan takt:history` | Fills past months with realistic demo working time. Writes a safety copy first; `--help` lists the range, balance and seed options. |
| `php artisan takt:icons` | Regenerates the notification icon. |

The macOS app runs both on its own — `takt:backup --if-due` and `takt:purge-trash` at launch and
every hour while it is open, so there is one backup a day without anything set up. Without the app
(a browser, Docker, a server) `routes/console.php` schedules them daily at 23:45 and 03:15; that
needs a runner: `php artisan schedule:work`, or a cron entry for `schedule:run`.

| `.env` variable | Default | Purpose |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | Name and port the server answers on; `takt:hostname` writes it. |
| `APP_TIMEZONE` | `Europe/Berlin` | Timezone all entries are stored and displayed in. |
| `APP_LOCALE` | `de` | Default interface language (`de` or `en`); every user can override it. |

### Your data

| Install | Database | Daily backups |
| --- | --- | --- |
| Download | `~/Library/Application Support/Takt/database/database.sqlite` | `~/Library/Application Support/Takt/storage/app/private/backups/` |
| From source | `database/database.sqlite` in the project folder | `storage/app/private/backups/` in the project folder |

The database is one file; copying it while the app is closed is a complete backup. The JSON
backups restore under *Einstellungen → Datensicherung*, additively and without duplicates.

## Development

```bash
php artisan test        # the whole suite
npm run dev             # Vite with hot reloading, next to make start
vendor/bin/pint --dirty # formatting
```

`public/build` is not versioned. A build older than the sources shows a banner in the app with
a button that runs `npm run build` for you; `make start` rebuilds as well.

| Path | Role |
| --- | --- |
| `app/Services/TimeTracker.php`, `EntryAdjuster.php` | Timer state machine, totals, balance; boundary trimming between neighbours. |
| `app/Services/AwayTime.php` | Records a locked or sleeping Mac and offers the three answers. |
| `app/Services/Tickets.php`, `Linear.php`, `Reviews.php` | The ticket board and the GitHub/Linear reads behind the development section. |
| `app/Enums/Widget.php`, `app/Services/Dashboard.php` | The 30 widgets and the board layout. |
| `app/Enums/Theme.php`, `app/Enums/DesignStyle.php` | Colour themes and design styles; each style is one token block in `app.css`. |
| `app/Support/Deferred.php` | Slow pages render skeletons first and fetch their content on a second request. |
| `app/Support/DataDirectory.php`, `app/Console/Commands/ReleaseCommand.php` | Where a bundled copy writes (`TAKT_DATA`), and the build of the self-contained app. |
| `app/Support/BuildFreshness.php` | Compares `public/build/manifest.json` with the CSS/JS sources. |
| `resources/js/app.js` | The boot file; it only calls the modules next to it. Every module listens on `document` and never keeps a node from the first paint — regions are swapped in place, and a cached node dies with the swap (`FrontendWiringTest` guards the shape). |
| `resources/css/app.css` | Colour tokens per theme, shape tokens per design style, the component layer. |
| `desktop/main.swift` | The macOS shell: window, menu bar timer, notifications, away detection, the page's canvas colour behind the web view. |
| `docs/requirements.md`, `docs/plan.md` | Requirement list and implementation plan. |
