# Implementation plan

Phases run top to bottom; each phase ends with the test suite green.
Requirement ids refer to `requirements.md`.

## Phase 1 — Authentication (R1)

- [x] Extend the `users` table: `weekly_hours`, `working_days`, `theme`.
- [x] `LoginController` (form, attempt, throttle, session regeneration, logout).
- [x] `RegisterController` (form, create, auto login).
- [x] Route split: `guest` group for login/register, `auth` group for everything else.
- [x] Auth layout (login/register screens in the app's visual language).
- [x] Header: user chip, gear (settings), logout.
- [x] Tests: login ok/failed, throttle, logout, redirect for guests, no user enumeration.

## Phase 2 — Per-user data (R2)

- [x] Migration: `user_id` on `time_entries` and `todos` (nullable + index + FK).
- [x] `BelongsToUser` trait: global scope for the authenticated user + auto-fill on create.
- [x] `TimeTracker` target values come from the caller, not from config.
- [x] `takt:assign-owner {email}` — adopt entries without an owner (R2.4).
- [x] `takt:history` gains `--user=` and writes for that user only.
- [x] Tests: cross-user isolation on index, edit, update, delete, day delete, todos.

## Phase 3 — Settings (R3)

- [x] `SettingsController` (show) + `WorkTimeController`, `ProfileController`,
      `PasswordController`, `ThemeController` for the individual cards.
- [x] `User::dailyTargetSeconds()` / `weeklyTargetSeconds()`.
- [x] Replace every `config('kairos.*')` read in views/controllers with the user value.
- [x] Tests: saving each card, validation bounds, isolation between users.

## Phase 4 — Themes (R4)

- [x] `App\Enums\Theme` with label, description, swatches.
- [x] Semantic colour tokens in `app.css` (`canvas`, `surface`, `panel`, `line`,
      `ink`, `muted`, `faint`, `accent`, `work`, `rest`, `danger` + text/ink variants).
- [x] Four `[data-theme]` blocks with the token values.
- [x] Refactor every blade view from hardcoded colours to the semantic tokens.
- [x] `data-theme` rendered server-side from the user's setting.
- [x] Tests: theme persists per user, invalid theme rejected, attribute rendered.

## Phase 5 — Todo list (R5)

- [x] `todos` table, `Todo` model, controller, requests, routes.
- [x] Nav entry, list view, add field, toggle, inline rename, delete, clear done.
- [x] Filter tabs with counts, empty states.
- [x] Tests: CRUD, toggle, filter, clear, validation, isolation.

## Phase 6 — Clean-up (R6)

- [x] Remove the footer target label.
- [x] README update for auth, settings, themes, todos.

## Phase 7 — Naming and logo (R7)

- [x] Name proposals with reasoning (no rename executed yet).
- [x] Logo directions matching the shortlisted names.

## Done in this round

- 120 tests / 1411 assertions green, Pint clean.
- `config/kairos.php` removed: the per-user setting replaced its last consumer.
- Verified visually in all four themes on dashboard, history, todo and settings.

## Phase 8 — Identity (R8)

- [x] Pick the name, set `APP_NAME`, tagline, README, docs.
- [x] New logo component + `favicon.svg` + `logo.svg`, theme-token driven.
- [x] Rename the artisan namespace `kairos:*` → the new name.

## Phase 9 — Design styles (R10)

- [x] `App\Enums\DesignStyle` + `users.design_style` column.
- [x] Component class layer in `app.css` (`surface`, `control`, `btn`, `pill`,
      `heading`) driven by shape tokens.
- [x] One CSS block per style overriding the shape tokens.
- [x] Refactor views from hardcoded radius/border/shadow to the component classes.
- [x] Style picker in the settings with preview.

## Phase 10 — Sidebar (R9)

- [x] `app-layout` becomes sidebar + content; responsive without JS.
- [x] Active state, account block, settings as a normal nav item.

## Phase 11 — Todos v2 (R12)

- [x] Migrations: `todos.body`, `todos.due_at`, `tags`, `todo_tag`.
- [x] Models + relations, `Tag` with `warn_lead_minutes`, `auto_complete_expired`.
- [x] Due-state logic (overdue / warning / today / week / later / none).
- [x] Todo index grouped, quick add, detail edit page, tag assignment.
- [x] Dashboard card with highlighted scheduled todos.
- [x] Tag settings section + CRUD.
- [x] Maintenance service + command for auto-completing expired todos.

## Phase 12 — Audit (R11)

- [x] Walk every view in all themes × representative styles, fix contrast and
      mismatched component colours.

## Phase 13 — UI polish (R13)

- [x] Fix inverted colour tokens; audit every `*-ink` usage.
- [x] Rework the tag settings card (labels, explanations, colour swatches).
- [x] Rework the style picker (mini-UI preview, no glitches).
- [x] New style catalogue with migration for the retired values.
- [x] Sliders icon instead of the gear.
- [x] `--stack-gap` density token wired into page layouts.
- [x] Mobile top bar: hide brand text and avatar below `sm`/`lg`.
- [x] `TagController` + `/todo/tags` page; tag routes moved off the settings.
- [x] Style carousel driven by a `?stil=` query parameter (works without JS).
- [x] Carousel: all slides pre-rendered, arrows swap client-side, `?stil=` links stay as the no-JS fallback.
- [x] Flash messages moved into a fixed-position toast.

## Phase 14 — Tag list collapses (R14.1)

- [x] `<details>`/`<summary>` per tag, summary shows colour dot, name, usage.

## Phase 15 — Task depth (R14.2, R14.7)

- [x] `Recurrence` enum + `todos.recurrence`; next-occurrence logic on completion.
- [x] `todo_steps` table, model, add/toggle/delete, progress on the row.
- [x] `todo_attachments` table, upload/download/delete, ownership-checked streaming.

## Phase 16 — Calendar and feed (R14.4)

- [x] `CalendarController` month grid, navigation, day links.
- [x] `users.ical_token`, public token route serving VEVENTs, regenerate in settings.

## Phase 17 — Month close and export (R14.3)

- [x] `MonthController`: print view + CSV (summary and backup moved to `Insights` and `BackupController` in phase 27).

## Phase 18 — Palette and quick capture (R14.5)

- [x] Palette markup in the layout, JS with filter/keyboard handling.
- [x] `TodoInputParser` for German dates, times and `#tags`.

## Phase 19 — PWA (R14.6)

- [x] Manifest, icon generation command, service worker, offline page.
- [x] Notification permission + due-window check while a tab is open.

## Phase 20 — Trash and undo (R15.1)

- [x] Soft deletes on `time_entries` and `todos`, trash page, restore, purge, undo toast.

## Phase 21 — Language and automatic theme (R15.14, R15.13)

- [x] `users.locale` + `SetLocale` middleware + settings select.
- [x] `Theme::Auto` with a pre-paint script and a `matchMedia` listener.

## Phase 22 — Absences, holidays, working time hints (R15.3, R15.4)

- [x] `absences` table + `AbsenceType`, holiday service per federal state.
- [x] Target calculation skips absence and holiday days everywhere.
- [x] Vacation account in the settings, absences managed from the calendar.
- [x] `WorkTimeCompliance` service + hints on dashboard and history.

## Phase 23 — Task time, snooze, templates (R15.5, R15.7, R15.12)

- [x] `time_entries.todo_id`, start-from-task, task select in the booking form.
- [x] Snooze actions; `step_templates` + items, apply and save as template.

## Phase 24 — Focus timer and day notes (R15.6, R15.11)

- [-] Focus timer withdrawn on request (2026-08-24) and removed again.
- [x] `day_notes` table, dashboard editor, history display.

## Phase 25 — Insights and search (R15.8, R15.9, R15.10)

- [x] `Insights` service + one `/auswertung` surface for week, month and year.
- [x] Year heatmap inside that surface, search endpoint wired into the palette.

## Phase 26 — Backup restore (R15.2)

- [x] Import form in the settings, additive merge with skip counters.
- [x] `takt:backup` command, daily schedule, keeps the newest 30 files.

## Phase 27 — Consistency pass (R16)

- [x] One insights view for all three periods (`InsightsController`, `Insights`,
      `insights.blade.php`); `/rueckblick`, `/jahr` and `/monat` are gone.
- [x] Async toggles for tasks and steps: no reload, client toast, fade-out in the
      open filter, counters updated.
- [x] Focus timer removed (card, settings, lang keys, JS, two user columns).

## Phase 28 — Takt (R16.4, R16.5)

- [x] Renamed to Takt everywhere: config, manifest, service worker cache, iCal PRODID,
      command prefix `takt:*`, CSV/backup filenames, Makefile, docs.
- [x] New mark (bar line + repeat dots) in the logo component, `logo.svg`, `favicon.svg`
      and the GD icon renderer.
- [x] `x-confirm-dialog` + the async confirm flow in `app.js` replace `window.confirm`.

## Phase 29 — Polish round (R17)

- [x] `Insights` returns the heatmap for every period plus a `heatmapMode`; the view renders
      a calendar grid for week/month and the compact grid for the year.
- [x] Stable insights header (period tabs · back/today/forward · icon actions) and a
      scrollable distribution list.
- [x] `PreferenceFile` service + `settings.export` / `settings.import` routes and the
      settings card; per-key validation with applied/skipped counters.
- [x] Collapsible sidebar (`nav-shell` grid variable, `nav-label` fade, localStorage +
      pre-paint script).
- [x] Redesigned confirm dialog (`dialog-panel`, `wb-dialog` animation).

## Phase 30 — Sidebar rail and heatmap scope (R17.1, R17.3)

- [x] Heatmap back to the year only; week and month keep the distribution list.
- [x] Personio-style toggle: `.nav-collapse` in the expanded header, `.nav-expand` layered
      over the logo and revealed on hover; both driven from CSS so no utility outranks the
      state.
- [x] Pinned `.nav-item` / `.nav-brand` heights and a single-row account block, verified by
      measuring every row's offset in both states (zero shift).

## Phase 31 — UI polish pass (R18)

- [x] Structural audit script over every rendered page (headings, accessible names,
      labels, 375 px overflow) plus the fixes it surfaced.
- [x] Sidebar: shared 44 px glyph axis, `max-inline-size` label shrink, paddings moved out
      of Tailwind utilities into CSS so the state rules are not outranked.
- [x] `nav-menu` account popover (`data-account-toggle` / `data-account-menu`) with
      settings, trash and logout; opaque background layered from canvas + surface.

## Phase 32 — Report, reminders, live forms (R19)

- [x] `insights-report` print view + `insights.report` route; `x-menu` / `x-menu-item`
      components for the export menu.
- [x] `workWatch` payload from the view composer + the reminder loop in `app.js`;
      `users.notify_worktime` + `settings.notifications` route.
- [x] Live form pipeline in `app.js` (confirm → fetch → region swap → client toast) and
      `data-live` on 38 forms; `data-region="main"` in the layout.
- [x] Task time tracking removed (controller, service, request, models, views, lang, column).
- [x] `DueState::accentClass()` + `.row-accent-*` inset bars.

## Phase 33 — Desktop bundle, task detail (R20)

- [x] `AppIcon` support class shared by `takt:icons` and `takt:app`; icns via `iconutil`.
- [x] `takt:app` (Info.plist, launcher script with `--dry-run` / `--start-only`, icns) and
      `takt:autostart` (launchd plist, `--dry-run`), plus Makefile targets.
- [x] `todos.show` route + view; edit view reduced to the form; row uses a stretched link.
- [x] `step_template_id` on the create form, validated per account and applied on store.

## Phase 34 — Dead-end fixes (R20.3)

- [x] `TodoController@destroy` / `TimeEntryController@destroy` decide their redirect from the
      referer; `data-region="nav"` added so a cross-page swap keeps the active section.
- [x] Verified end to end: content and URL follow the response, the flash survives, no
      reload, no extra history entry.

## Phase 35 — Native window (R20.1)

- [x] `desktop/main.swift`: NSApplication + WKWebView, server child process with readiness
      wait, menu, zoom, external-link routing, `Notification` shim onto
      `UNUserNotificationCenter`, frame autosave.
- [x] `takt:app` compiles it, writes `TaktRoot` / `TaktPhp` / `TaktPort` into Info.plist and
      ad-hoc signs the bundle; falls back to the shell launcher without swiftc.
- [x] Verified: Mach-O arm64 binary, adhoc signature, one 1180×820 window owned by Takt on
      screen, and the app's own requests in the server log.

## Phase 36 — Native window polish and cleanup (R20.1, R21)

- [x] Window: real title bar instead of `fullSizeContentView`, opaque web view, no
      `isMovableByWindowBackground` — the content no longer sits under the window buttons,
      the bar is a handle again, and scrolling stopped shivering.
- [x] `[data-shell='native']` disables the sidebar's backdrop blur inside the app window.
- [x] Removed the PWA layer and the dead code found by a repository-wide orphan sweep;
      switched logging to daily rotation.

## Phase 37 — Publication (R22)

- [x] `install.sh`, `.github/workflows/ci.yml`, `LICENSE`, `.gitignore` additions, README
      install section; `git init` without a commit — the first commit and the push stay with
      the maintainer.
- [x] Window: `fullSizeContentView` with a hidden title, `DragStrip` overlay for moving,
      `applicationNameForUserAgent` marker, unlayered app-shell CSS.

## Phase 38 — Publication (R23)

- [x] Repository created through the browser under the right account, description and topics
      set, initial commit pushed, CI green after two fixes (unit suite, icon step).
- [x] `install.sh` / `update.sh` proven against GitHub; requirement check rewritten without
      a pipe; `takt:app` guards a foreign bundle (`--force` to take it over).

## Phase 39 — Development section (R24)

- [x] `projects` and `snippets` tables plus four user columns (token, three templates).
- [x] Services: `Commits` (local git), `Reviews` (GitHub search, cached), `ProjectRunner`
      (detached start, pid, port probe), `TestPost` (template expansion + slug).
- [x] Pages: overview (commits, reviews, projects, snippets), projects, snippets, test post,
      plus the settings card; `data-copy` clipboard handler wired into the palette.
- [x] 21 tests, including real git repositories created in the test and a faked GitHub API.

## Phase 40 — Colour scheme as a flip-through element (R14)

- [x] One `choice-carousel` component drives the design style and the colour scheme alike;
      the previous per-style JS became a generic `[data-carousel]` handler.
- [x] `theme-preview` shows a real mock-up in the scheme's own tokens; an explicit
      `[data-theme='midnight']` block lets a preview keep its colours inside another theme.
- [x] Automatic shows both halves of the same mock-up as a diagonal split, so no slide
      changes the element's height.
- [x] The design-style slides did resize the card anyway (154–186 px, measured in the browser).
      Every slide now stays in the layout, stacked in one grid cell and hidden by visibility,
      so the tallest one sets the height once: both cards hold a single height across all
      slides (407 px and 382 px).

## Phase 41 — Registering a project (R24.6)

- [x] `ProjectScanner` reads folder, remote, start command and port; `NSOpenPanel` bridge in
      the shell (`window.takt.pickFolder`), scan on paste or blur in the browser.
- [x] `make start` as the default, the port labelled optional with the reason spelled out.

## Phase 42 — Dashboard of widgets (R25)

- [x] `Widget` / `WidgetGroup` enums as the register (span + rows per widget),
      `dashboard_widgets` table, `users.dashboard_arranged` to tell an empty board from an
      untouched one, `Dashboard` service that resolves the layout and loads data per widget.
- [x] The old dashboard split into seven widget views; fourteen new ones on top, ten of them
      about development.
- [x] One endpoint: the edit mode sends the whole layout (order, span, rows) as JSON, so a
      drag never leaves a half state; reset writes the default set back.
- [x] `board.js`: edit mode, pointer-driven dragging with a ghost tile, FLIP animation for
      every tile that moved, the gallery on the right, iOS-style wiggle and pop-in badges.
      Every listener is bound to the document once, so a region swap never loses the wiring.
- [x] Row height measured against the real content in the browser until no tile scrolled by
      accident (6–12 px overflows on three tiles, fixed by a 5rem row).
- [x] 14 layout tests plus `EveryPageTest`, which renders every page with content and every
      widget on one board.
- [x] Measured in the browser after the first round of feedback: rows are a floor, not a cap
      (`minmax(row, max-content)`), so a tile narrowed in edit mode grows instead of clipping;
      the height arrows are gone — the width is the only size worth choosing by hand.
- [x] Saving moved to the end of the edit mode: arranging is local, "Fertig" (or escape) is the
      one commit, `pagehide` writes an unsaved arrangement as a net. A pushed-in widget stays
      marked until that commit, and a removed one goes straight back into the gallery.
- [x] `.control` got one height for every field type and text size — a monospace path next to a
      plain name was five pixels shorter, which is what read as broken.
- [x] Development header: the day navigation moved left of the tabs, so the tab bar keeps its
      place on all four pages instead of shifting on the overview.

## Phase 43 — Marking a range in the calendar (R26)

- [x] `day-range.js`: pointer-driven marking (drag or long press), the range painted straight
      onto the day cells, backwards drags sorted, escape and cancel clearing it.
- [x] The absence window lives in the calendar view and posts to the existing
      `absences.store`; the calendar comes back through the region swap.
- [x] A plain click keeps its old job — the day still links into the week history.
- [x] 6 tests on the contract the browser reads: `data-day` per cell, `draggable="false"`, the
      window's fields, and the endpoint taking a marked range (single day and reversed range).
- [x] First round did not drag at all: a day is a link, so the browser answered press-and-move
      with its own drag-and-drop. `draggable="false"` plus `preventDefault()` on pointerdown
      fixed it; synthetic events had passed because they skip exactly that default. Verified
      afterwards with real mouse drags in the browser.

## Phase 44 — Posting to Slack (R24.5)

- [x] `users.slack_token` (encrypted cast) + `slack_channel`; the settings card takes both with
      the same semantics as the GitHub token (empty keeps, `-` clears).
- [x] `Slack` service: `chat.postMessage` with the user's own token so the message appears under
      their name, link unfurling off, `chat.getPermalink` for the link back, and Slack's error
      codes translated into plain words.
- [x] The test-post preview gained the send button next to copy, behind an in-app confirmation;
      it only shows up once token and channel are stored.
- [x] 9 tests against a faked Slack API — the real endpoint is never called.

## Phase 45 — One command and a real address (R27)

- [x] `LocalUrl` as the single source for host, port and the hosts line; every place that used
      a hard-coded `localhost:8000` reads it now (app bundle, launcher, login item, Makefile).
- [x] `takt:hostname` writes `/etc/hosts` (sudo only when the file needs it, otherwise it
      prints the one line to run) and `APP_URL` — in that order, and only when the name
      resolves. `takt:setup` chains the whole install; `install.sh` is down to one command.
- [x] The macOS shell learned `TaktHost`: the window opens the real address while the health
      probe and `serve` stay on the loopback.
- [x] Proven on a fresh clone in /tmp, which surfaced three real faults: a fresh `.env` is not
      in the config of the running process (the bundle would have been named `Laravel.app`),
      `make` mistook another installation's login item for its own, and a detached server
      inherits the command's pipe, so `Process::run` never returned. The server start moved
      into the shell script for exactly that reason.
- [x] 13 tests, with the hosts file and the `.env` as options so the machine's own files are
      never touched.

## Phase 46 — The edit mode, after using it (R25)

- [x] `dashboard.widget` renders one widget on its own, so a pushed-in tile fetches its real
      content instead of showing a named placeholder until the save.
- [x] The x is a cancel: it reloads the board from the server and stores nothing (verified in
      the browser — no PUT leaves), escape does the same, "Fertig" is the only save.
- [x] Leaving the mode applies at once instead of after the round trip, and the swapped-in
      tiles skip their entrance animation, which is what made the page look like it rebuilt.
- [x] The gallery went from 19.5rem to 16.5rem and the board's padding with it: at 1440 px the
      board keeps ~1000 px instead of ~700, where the timer's own heading wrapped.
- [x] Room for the tool pill inside a tile, and a badge that lifts above its neighbours.

## Phase 47 — The review cache (R24.3)

- [x] The development page broke as soon as a GitHub token existed: `Reviews` cached Carbon
      objects, and `config/cache.php` ships `serializable_classes: false`, so every cached
      object returned as `__PHP_Incomplete_Class`. Proven both ways in tinker.
- [x] Dates are cached as ISO strings and hydrated on the way out; a cache entry of the wrong
      shape is discarded instead of rendered.
- [x] 4 tests against the store the app really uses (`cache.default: database`), including the
      second visit that reads from the cache — the case that failed in the wild.

## Phase 48 — Picking a folder in the page (R24.6)

- [x] `FolderBrowser` lists sub-folders below the home directory, resolving every path first
      and refusing anything outside — that also settles symlinks and typed "..".
- [x] `folder-picker.js` plus a dialog: crumbs to jump back up any number of levels, one row
      per folder, `git` marked, "take this folder" hands it to the field and the existing scan
      fills name, repository, start command and port.
- [x] The Finder bridge in the shell is gone. It only worked in the app window — and there it
      did not fire at all — so one path that works everywhere replaced it, and the
      `native-only` helper and its callback went with it.
- [x] 7 tests, mostly about where the browser refuses to look.

## Phase 49 — Make targets, and an honest review list (R28, R29)

- [x] `MakeTargets` parses the Makefile (48 real targets out of galawork-web, descriptions
      included); `CommandRunner` starts one detached with its log and exit-code file;
      `CommandRun` + `RunStatus` keep the state. New page "Befehle" with a run dialog.
- [x] Measured before touching anything: reviews 1367 ms uncached, commits 107 ms, project
      state 1 ms. So only the reviews moved out of the request — the page renders from the
      cache and pulls the sections in afterwards.
- [x] The first real run failed with `make: docker: No such file or directory`: a login item
      passes down a bare PATH. Runs now go through the user's own login shell with the usual
      tool directories ahead of it, verified with `command -v docker`.
- [x] "My open pull requests" was empty although pull requests existed. The token carries only
      `public_repo, repo:status`, so the search API returned nothing at all. Reviews now read
      each project's repository directly, which answers 404, and that is reported per project.
- [x] Collapsible per project everywhere (commits, pull requests, targets), closed by default,
      remembered per key; plus a filter over all targets.
- [x] 14 new tests, including a Makefile of its own in the test, a stopped run, a pruned run and
      a repository the token cannot see.

## Phase 50 — Paging the days without reloading the reviews (R29)

- [x] `swapRegions` takes an optional list of regions, and a link marked `data-partial` swaps
      exactly those: the development header and the commits card. The reviews sit outside both,
      so paging through the days never touches GitHub again.
- [x] `pushState` per day with a `popstate` handler, so the browser's back button pages back.
- [x] The review cache went from 2 to 10 minutes — a fetch costs over a second and the refresh
      button is right there.

## Phase 51 — Interactive runs, Docker, and help at the field (R30, R31, R32)

- [x] `bin/takt-pty`: a pty helper (python3, ~120 lines) that runs a command in a pseudo
      terminal, writes what the terminal produced into the log, passes the FIFO into the
      command's input and records the exit code. Proven by hand first: `test -t 0` says TTY,
      a `read` prompt is answered, exit 0.
- [x] `TerminalText` turns that raw output into something a page can show — colour codes gone,
      carriage returns rewriting their line, backspaces applied.
- [x] `ShellEnvironment` now holds the PATH and shell logic both the command runner and docker
      need; `CommandRunner` grew `write()` and an `interactive` flag per run.
- [x] `Docker` reads `docker ps` with a 0x1f-separated format (the first attempt kept the
      literal "\x1f" in the Go template and split nothing), groups by compose project, and
      only ever acts on an id it just listed. Logs come through `TerminalText`.
- [x] `x-hint`: a small (i) with the setup steps for the Slack and GitHub tokens — hover or
      keyboard focus, no JavaScript.
- [x] 15 new tests. Docker is faked throughout, so no test ever starts or stops a container;
      the pty tests skip on a machine without python3.

## Phase 52 — The hint, pinned (R32)

- [x] Clicking the (i) pins the panel open, so its links are usable; outside click and escape
      close it, a click inside does not.
- [x] The visibility rules moved out of the component layer: the panel's own rule ends up
      unlayered, and an unlayered rule beats a layered one — inside the layer the pinned state
      never applied.
- [x] Slack renamed "From scratch" to "Blank app"; the guide says what the dialog says today,
      and a test keeps it that way.
- [x] One measurement trap worth writing down: a background tab never advances a transition, so
      `getComputedStyle(...).opacity` read 0 for a panel that was in fact shown. Verified by
      switching the transition off.

## Phase 53 — Where the +25 h came from (R33, R34)

- [x] Traced instead of guessed: every generated week hit exactly 40 h over exactly five days,
      so the balance had to be right — and it was. The plus came from three days the generator
      had booked on public holidays (1 May, 14 May, 25 May), worth exactly 22 h. A holiday has
      no target, so work on it is overtime; the balance was correct, the data was not.
- [x] `WorkHistoryGenerator` takes the exempt dates and skips them; a week with a holiday
      covers fewer days and its target shrinks with it. A test generates across Ascension Day
      and Whit Monday with `--balance 0` and asserts the balance is exactly zero.
- [x] The "current week" / "current month" buttons stay in place and go inactive, in the
      history, the calendar and the dashboard's week chart.

## Phase 54 — Four small ones, no app rebuild (R49, R50, R46, R51)

- [x] `Reviews::waitStats()` — median, oldest and stuck count for both lists, out of the data
      the fetch already returns. "How fast do I review" was dropped: it needs one request per
      pull request and the rate limit is real.
- [x] "Book like last time": `TimeTracker::lastPattern()` reads the last booked day (earliest
      start, latest end, first break, note) and a `data-fill` button writes it into the form.
      The date is deliberately not copied.
- [x] `Releases` service — `git for-each-ref` per project, in parallel, with `-refname` as the
      tie-breaker because two tags in the same second are otherwise unordered. Cached for five
      minutes as ISO strings, never Carbon.
- [x] `Parallel::run()` extracted from `Commits` instead of copied into `Releases`.
- [x] Search: projects, absences, tags, checklist templates, make targets, releases and pull
      requests joined the palette. Releases and pull requests answer from their caches — the
      palette must not start a git or GitHub round trip per keystroke.
- [x] Tests per source: every group the palette can return is asserted once, plus that nothing
      is sent to GitHub.

## Phase 55 — Tickets, with Linear as the source (R44)

- [x] `Tickets` service: `[A-Z][A-Z0-9]+-\d+` out of branch names, commit subjects and pull
      request titles, grouped per id — the enrichment.
- [x] Linear is the source, not a lookup: `viewer { assignedIssues }` carries the list, git adds
      projects, commits, pull requests and branches. An id that only exists in git is listed as
      git-only rather than dropped.
- [x] Booked time is an estimate and says so: a day's working time split evenly across the
      tickets committed that day. Git records no branch history, so anything more precise would
      be a guess dressed as a measurement.
- [x] Area with search, open/all filter, window (30/90/180 days) and a reload button.

## Phase 56 — Linear (R44)

- [x] `Linear` service: one request per purpose through a single `post()` helper, `Authorization`
      without "Bearer", errors mapped to honest German text, cached ten minutes as strings.
- [x] `mine()` deliberately without a server-side filter — the open/closed split happens locally,
      so a wrong guess about the filter syntax cannot break the whole list. The by-id query does
      use the documented `in` comparators; **not yet verified against a real key**.
- [x] Settings: personal API key, encrypted, never rendered back, `-` clears it.
- [x] Bug found by its own test: a failed request must not remember ids as "unknown", or they
      would never be asked for again until the cache expires.

## Phase 57 — The menu bar and the global key (R52)

- [ ] `NSStatusItem` in `desktop/main.swift`: state, start, stop, today's total, open window.
- [ ] A small HTTP surface the shell talks to for those actions, authenticated by a token the
      bundle already knows.
- [ ] `RegisterEventHotKey` for start/stop; the combination is a setting the shell reads.

## Phase 58 — The Mac was away (R47)

- [ ] The shell subscribes to lock/sleep/wake and posts the gap to Takt.
- [ ] A running timer that spans a gap is surfaced on the next view with three answers.

## Phase 59 — Reading the calendar (R48)

- [ ] EventKit in the shell, permission asked once, events handed to Takt for the current day.
- [ ] Events appear as booking proposals in the day and as context in the history.

## Phase 60 — Takt on the phone (R53)

- [ ] Setting: bind to the local network. The shell reads it and starts `artisan serve`
      accordingly; the WebView's navigation allowlist learns the LAN host.
- [ ] Settings show the reachable address and a QR code; the switch states that this is plain
      HTTP inside your network.

## Phase 61 — The activity trail (R45)

- [ ] The shell records the front application (and the window title where the accessibility
      permission was granted) and posts spans to Takt.
- [ ] Storage with a retention setting, a pause switch, exclusion from every export.
- [ ] Proposals in the day view: a span becomes a booking on one click, never on its own.
