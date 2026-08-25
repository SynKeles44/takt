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
