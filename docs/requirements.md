# Requirements

Status legend: `[ ]` open · `[x]` done · `[-]` dropped (with reason).

## R1 — Authentication

- [x] **R1.1** Users log in with email + password. Login is required for every
      application page; unauthenticated visitors land on the login screen.
- [x] **R1.2** Users can register an account (self-hosted, small team). Password
      strength follows the framework defaults.
- [x] **R1.3** Logout ends the session (invalidate + token regeneration).
- [x] **R1.4** Login is rate limited and never reveals whether an email exists.
- [x] **R1.5** Session fixation protection: the session id is regenerated on login.

## R2 — Per-user data

- [x] **R2.1** Time entries belong to exactly one user. A user only ever sees,
      edits, or deletes their own entries.
- [x] **R2.2** Todos belong to exactly one user, same isolation.
- [x] **R2.3** Ownership is enforced server-side (not by hiding UI), including
      the whole-day delete and the entry edit routes.
- [x] **R2.4** Existing entries created before authentication can be assigned to
      a user without data loss.

## R3 — Per-user settings

- [x] **R3.1** A settings page reachable from a gear icon in the header.
- [x] **R3.2** Weekly working hours are configurable per user (default 40).
- [x] **R3.3** Working days per week are configurable per user (default 5).
- [x] **R3.4** The daily target is derived from both and drives every target
      display (today card, week card, chart baseline, plus/minus balance).
- [x] **R3.5** Name and email are editable.
- [x] **R3.6** Password change requires the current password.
- [x] **R3.7** Settings are strictly per user — one user's change never affects
      another user.

## R4 — Colour themes

- [x] **R4.1** The colour design is configurable; the choice is stored per user.
- [x] **R4.2** Theme selection lives in the settings page behind the gear icon,
      with a visual preview per theme.
- [x] **R4.3** Theme 1 "Mitternacht" — the current dark blue/indigo scheme.
- [x] **R4.4** Theme 2 "Tageslicht" — light/white scheme.
- [x] **R4.5** Theme 3 "Onyx" — black scheme.
- [x] **R4.6** Theme 4 "Salbei" — dark sage green with light green accents,
      oriented on the supplied Personio screenshot.
- [x] **R4.7** Themes are applied server-side (no flash of the wrong theme) and
      every surface follows them — no hardcoded colour left behind.

## R5 — Todo list

- [x] **R5.1** A third navigation entry "ToDo" next to "Heute" and "Verlauf".
- [x] **R5.2** Add an item from a single input field.
- [x] **R5.3** Tick an item off and un-tick it again.
- [x] **R5.4** Rename an item in place.
- [x] **R5.5** Delete a single item and delete all completed items at once.
- [x] **R5.6** Filter open / done / all, with counts.
- [x] **R5.7** Modern, simple, keyboard friendly (add field focused, Enter saves).

## R6 — UI corrections

- [x] **R6.1** Remove the "Ziel 8 h" label from the bottom right of the footer.

## R7 — Naming and logo (proposal only, no implementation yet)

- [x] **R7.1** Propose new product names — "Takt" is rejected. (decided: Takt)
- [x] **R7.2** Propose a more modern logo direction, decided together with the name.

## Out of scope for this round

- Drag & drop reordering of todos (buttons/order by insertion for now).
- Teams, shared calendars, approvals, absence management.
- Email delivery (password reset by mail) — no mail transport is configured.

## R8 — Identity (name, logo, positioning)

- [x] **R8.1** New product name — the app is no longer a pure time tracker but a
      workspace for organising work (time **and** tasks).
- [x] **R8.2** New logo: flat, geometric, legible at 16 px, no skeuomorphic badge
      with clock hands, works on every theme and every design style.
- [x] **R8.3** Tagline reflects the widened scope.

## R9 — Sidebar navigation

- [x] **R9.1** Replace the top navigation and the gear icon with a left sidebar.
- [x] **R9.2** Sidebar holds brand, the main sections (today, history, todos,
      settings) and the account block (user + logout) at the bottom.
- [x] **R9.3** Responsive: sidebar on wide screens, compact bar on narrow ones,
      without JavaScript.
- [x] **R9.4** The active section is visually unmistakable.

## R10 — Design styles (second axis next to the colour theme)

- [x] **R10.1** A per-user design style, independent of the colour theme, that
      changes shape language: radii, border weights, shadows, density, headings.
- [x] **R10.2** Many styles, clearly distinct from each other, including
      industrial and factory/HMI flavours.
- [x] **R10.3** Selectable in the settings with a live preview per style.
- [x] **R10.4** Every surface follows the style — no component keeps a hardcoded
      radius, border or shadow.

## R11 — Text and component colour audit

- [x] **R11.1** Text colour adapts to the theme everywhere; readable on every
      background in all four themes.
- [x] **R11.2** Components with mismatched colours are corrected (badges, chart
      labels, idle states, inputs, disabled/empty states).

## R12 — Todos, second iteration

- [x] **R12.1** A todo has a title and an optional body (details).
- [x] **R12.2** A todo can be scheduled with a due date and optional time.
- [x] **R12.3** Todos appear on the dashboard; scheduled ones are highlighted,
      overdue ones stand out most.
- [x] **R12.4** Todos can carry tags; tags belong to the user.
- [x] **R12.5** Per tag: how long before the due time a warning appears.
- [x] **R12.6** Per tag: whether expired todos are auto-completed.
- [x] **R12.7** Tag settings live in the settings page.
- [x] **R12.8** Warnings are shown in the app (no mail transport configured).
- [x] **R12.9** Grouping/sorting oriented on established todo apps: overdue,
      today, this week, later, no date, done.

## R13 — UI polish round

- [x] **R13.1** Every text colour is theme-driven; no inverted token used on the
      wrong background (the running clock was `accent-ink` on a dark canvas).
- [x] **R13.2** Tag settings are large enough to read, each field labelled, with
      an explanation of what the lead time and the auto-complete flag do.
- [x] **R13.3** Tag colour is picked from clickable colour swatches, not a select.
- [x] **R13.4** Design style picker shows a real mini-UI preview per style and
      applies without visual glitches.
- [x] **R13.5** Style catalogue reworked: added Skeuomorphism, Neumorphism,
      Glassmorphism, Minimalism and Bento; dropped Swiss (≈ Minimalism), Fabrik
      (≈ Industrial) and Glas (renamed Glassmorphism). Existing users are migrated.
- [x] **R13.6** The settings icon is no longer a gear.
- [x] **R13.7** The design style also drives spacing density, not only shape.
- [x] **R13.8** Component sizes, stat labels and the mobile top bar fit without
      overflow or clipping.
- [x] **R13.9** Tag management lives on its own page, reachable from the todo area,
      not inside the settings.
- [x] **R13.10** The design style picker is a carousel: arrows step through the
      styles, one preview at a time, and the button reads "Auswählen" until the
      style is active, then "Aktiv" with a check.
- [x] **R13.11** Flipping through the design styles happens without a page reload.
- [x] **R13.12** Status notifications float as a toast and never shift the layout.

## R14 — Personal work manager, second expansion

Deliberately **not** a ticket or project system: no customers, no projects, no team
assignment. Everything below serves one person organising their own work.

### R14.1 Tag list collapses

- [x] On the tag page a tag shows only its name and colour until it is opened.
- [x] Opening reveals the full settings; works without JavaScript.

### R14.2 Recurring tasks (idea 4)

- [x] A task can repeat: daily, on weekdays, weekly, every two weeks, monthly, yearly.
- [x] Completing a repeating task creates the next occurrence with the same title,
      body, tags, subtasks-template-free copy and the due date advanced past today.
- [x] Recurrence is visible on the task row and editable on the task page.
- [x] Repetition never produces duplicates when the same task is completed twice.

### R14.3 Month close and export (idea 6)

- [x] A month view with work, break, balance, booked days and overtime.
- [x] A print-optimised timesheet (browser print to PDF, no extra dependency).
- [x] CSV export of a month, Excel-friendly (semicolon, BOM, German dates).
- [x] JSON export of all own data (entries, tasks, tags) as a backup.

### R14.4 Calendar (idea 7)

- [x] Month grid with per-day work time and the tasks due that day.
- [x] Navigation between months, today highlighted, click jumps into the history week.
- [x] Subscribable iCal feed of the dated tasks, protected by a per-user token that
      can be regenerated.

### R14.5 Command palette and quick capture (idea 8)

- [x] ⌘K / Ctrl+K opens a palette: navigate, start/stop timer, jump to sections.
- [x] Keyboard driven: filter by typing, arrows, Enter, Escape.
- [x] Natural language capture: "Angebot Müller morgen 14:00 #deadline" sets title,
      due date, time and tag from one line.

### R14.6 Installable app and notifications (idea 9)

- [-] Web app manifest plus icons: withdrawn on 2026-08-24, the app ships as a native
      bundle instead (R21.1).
- [x] Service worker caches the shell and the built assets, offline fallback page.
- [x] Browser notifications for the tag warning window while the app is open;
      permission is requested explicitly in the settings.
- [x] Honest limitation: no background push (that needs a push server).

### R14.7 Subtasks, notes and attachments (idea 10)

- [x] A task can hold subtasks with their own done state and a progress indicator.
- [x] Subtasks are added, ticked and removed on the task page.
- [x] A task can hold file attachments, stored outside the web root.
- [x] Attachments are served only to their owner, with a size and type limit.

## R15 — Personal work manager, third expansion

### R15.1 Trash and undo (idea 1)

- [x] Deleted time entries and tasks land in a trash instead of vanishing.
- [x] The trash keeps items for 30 days, lists them and restores or purges them.
- [x] The toast after a deletion offers an immediate undo.
- [x] Expired items are purged automatically.

### R15.2 Backup restore (idea 2)

- [x] The JSON backup can be read back in, additively, without duplicating.
- [x] The import reports what was created and what was skipped.
- [x] A command writes a dated backup file and keeps the last 30.

### R15.3 Absences and public holidays (idea 3)

- [x] Vacation, sick leave and other absences as day ranges.
- [x] German public holidays per federal state, computed (Easter-based included).
- [x] Absence and holiday days carry no daily target, so they never create minus.
- [x] Visible in the calendar, the history and the month close.
- [x] A vacation account: entitlement, taken, remaining.

### R15.4 Working time hints (idea 4)

- [x] Warn when more than 6 h are worked with less than 30 min break.
- [x] Warn when more than 9 h are worked with less than 45 min break.
- [x] Warn above 10 h a day and below 11 h rest between two days.
- [x] Hints are informational, never blocking, shown for today and per history day.

### R15.5 Timer on a task (idea 6)

- [x] A time entry can belong to a task.
- [x] A task can be started directly; the running timer shows the task.
- [x] The task page shows the time booked on it and its entries.
- [x] The manual booking form can pick a task.

### R15.6 Focus timer (idea 7) — withdrawn

- [-] Withdrawn on request (2026-08-24): the focus cycle, its settings and the
      dashboard card were removed again. The app tracks real work time, it does
      not run a pomodoro.

### R15.7 Snooze (idea 8)

- [x] A dated task can be pushed to tomorrow, plus one week, or by one hour.
- [x] Available on the row for urgent tasks and on the task page.

### R15.8 Weekly review (idea 9)

- [x] Week, month and year live on ONE insights surface with the same skeleton:
      header with period switch, four tiles, distribution, completed tasks.
- [x] Per period: work, target, balance, booked days, average, longest day,
      completed tasks.

### R15.9 Year heatmap (idea 10)

- [x] A year grid, one cell per day, intensity by hours worked, on the same page.
- [x] The year distribution shows one bar per month with its target marker.

### R15.10 Full text search (idea 11)

- [x] The palette searches tasks, task details, booking notes and day notes.
- [x] Results link to the right place; search is debounced and scoped to the user.

### R15.11 Day note / journal (idea 12)

- [x] One free text note per day, editable on the dashboard, shown in the history.
- [x] Included in the search.

### R15.12 Checklist templates (idea 13)

- [x] Named subtask templates, managed on their own page.
- [x] Applying a template adds its steps to a task.
- [x] A task's steps can be saved as a new template.

### R15.13 Automatic theme (idea 14)

- [x] A theme option that follows the system light/dark preference.
- [x] No flash of the wrong theme, reacts to a live system change.

### R15.14 Language switch (idea 15)

- [x] The interface language is switchable per user in the settings (de/en).

## R16 — Consistency pass

### R16.1 One insights surface

- [x] Week, month and year use one controller, one service and one view; switching
      the period only changes the numbers, not the layout.
- [x] Timesheet and CSV stay reachable as actions of the month period.

### R16.2 Smooth checkboxes

- [x] Ticking a task or a step no longer reloads the page: the state flips in
      place, the toast is rendered client side.
- [x] In the open filter a completed task fades out and the counters follow.
- [x] A recurring task still reloads once, because a new occurrence appears.

### R16.3 Focus timer removed

- [x] Card, settings, translations, JS and the two user columns are gone.

### R16.4 Name and mark

- [x] The app is called **Takt**; the mark is a bar line with two repeat dots — the musical
      measure, drawn from the same accent tokens as the rest of the UI.
- [x] Logo component, `public/logo.svg`, `public/favicon.svg`, the generated PNG icons, the
      manifest, the artisan prefix (`takt:*`) and every export filename follow the name.

### R16.5 Dialogs inside the app

- [x] `data-confirm` opens an in-app dialog in the app's design instead of `window.confirm`.
- [x] Escape and the backdrop cancel, Enter confirms, the accept button takes focus.
- [x] Without JavaScript the plain form submit still works.

## R17 — Polish round

### R17.1 One insights header, heatmap on the year

- [-] Heatmap in week and month withdrawn on request (2026-08-24): the distribution list
      already carries the same information per day, so the card only stays on the year.
- [x] The period header carries the same controls everywhere (period switch, back/today/
      forward); the month's timesheet and CSV are icon actions, so nothing reflows.
- [x] The distribution list scrolls inside its card, so the two columns stay balanced.

### R17.2 Settings as JSON

- [x] Working time, vacation, federal state, language, colour theme and design style can
      be exported as a JSON file and imported again from the settings.
- [x] The import validates every value on its own: invalid keys are skipped and counted,
      the rest is applied; a file without one valid setting is rejected.

### R17.3 Collapsible sidebar

- [x] The sidebar collapses to an icon rail and expands again, animated, with the choice
      remembered locally and applied before the first paint.
- [x] Collapsed, the toggle button is gone: hovering the logo fades it out and the expand
      button appears in its place.
- [x] Nothing moves vertically between the two states — row heights are pinned, and the
      logout button stays on the avatar's row instead of dropping below it.

### R17.4 Dialog design

- [x] The confirmation dialog is centred, with an icon badge, title, message and two equal
      buttons; Escape and the backdrop cancel, Enter confirms.

## R18 — UI polish pass

### R18.1 Structure and accessibility

- [x] One `h1` per page: the history and entry-edit cards no longer repeat the page title.
- [x] Every icon-only control has an accessible name; every field has a label, an
      `aria-label` or a placeholder (audited across all 17 pages).
- [x] No horizontal overflow at 375 px on any page — grid children carry `min-width: 0`
      and the entry type badge drops its label on the smallest screens.
- [x] Wider content column on xl screens; compact hour format in the calendar cells.

### R18.2 Sidebar

- [x] Collapsing moves nothing but the label boxes and the rail's right edge: logo, nav
      icons, search icon and avatar sit on one shared axis in both states (measured).
- [x] Labels shrink via `max-inline-size` instead of switching to absolute positioning,
      which is what made the collapse look like it stuttered.
- [x] No view transitions: they cross-faded two snapshots of the page and looked wrong.

### R18.3 Account menu

- [x] Logout is gone from the rail. The avatar opens a popover with settings, trash and
      logout; it has its own opaque ground, no ring on the avatar, and is rendered outside
      the sidebar and positioned by script — a `backdrop-filter` ancestor clips absolutely
      positioned children in some Chrome builds, which cut the menu off at the rail edge.
- [x] Settings left the nav list, so the rail carries the five work sections only.

## R19 — Report, reminders, fewer reloads

### R19.1 Printable report (idea 11)

- [x] Every insights period exports a printable report: totals, the distribution table with
      target markers, absences per row and the tasks completed in that period.
- [x] The three documents (report, timesheet, CSV) sit behind one export menu with a short
      line of explanation each, instead of three bare icons.

### R19.2 Working time reminders (idea 12)

- [x] Notifications for the daily target, a due break above six hours and the approaching
      ten hour limit, each fired once per day, while the app is open.
- [x] Switchable per user in the settings (`notify_worktime`).

### R19.3 No hard reloads

- [x] Forms marked `data-live` post in place and only the marked regions are replaced —
      adding a task, booking time, deleting a row, restoring from the trash, the undo in the
      toast and the timer from the palette no longer reload the page.
- [x] Without JavaScript, or when the response leaves the current page, the browser does
      the normal navigation.
- [x] Popovers survive a region swap because their behaviour is delegated, and a card that
      hosts one is raised — the filling entrance animation makes every card its own
      stacking context, so a later card would otherwise paint over the menu.

### R19.4 Tasks stay simple

- [-] Time booking on tasks withdrawn on request (2026-08-24): the start button, the booked
      time, the task's entry list, the task field in the booking form and the
      `time_entries.todo_id` column are gone.
- [x] A dated row shows its state as an inset accent bar, so the row keeps its own border
      on every side.

## R20 — Desktop app and task detail

### R20.1 A real application

- [-] NativePHP dropped: `nativephp/electron` is abandoned and caps at Laravel 12, and the
      successor `nativephp/desktop` 2.x has no local build — the runtime comes from the
      account-based Bifrost service, and it registers `native:migrate:fresh` twice, which
      breaks `php artisan list` on Laravel 13.
- [x] `takt:app` builds a real macOS application instead: a Cocoa window around a WKWebView
      (`desktop/main.swift`, compiled with `swiftc`, ad-hoc signed), own icon, own menu,
      remembered window frame, native notifications bridged from the web Notification API,
      external links handed to the browser. It starts the local server when nothing serves
      the port and stops only its own child. Without Xcode's toolchain it falls back to a
      chromeless browser window.
- [x] `takt:autostart` registers a launchd login item, so the server is always up; both are
      reachable as `make app` and `make autostart`.

### R20.2 Task detail view

- [x] Clicking anywhere on a task row opens its own page with everything: title, details,
      badges, state, dates, warning, steps with progress and attachments.
- [x] The edit page is only the form now and returns to the task page.
- [x] A checklist template can be attached while creating a task, not only afterwards.

### R20.3 Nothing leads into a 404

- [x] Deleting a task from its own page returns to the list instead of reloading the page of
      the deleted task; deleting from a list stays where it was. Same for a time entry
      deleted from its edit page.
- [x] A live action that lands on a different page swaps the regions from the response that
      was already rendered and rewrites the URL, so the message and its undo survive; the
      history entry is replaced, so Back does not walk into the deleted page.

### R20.4 One owner for the local server

- [x] The login item is the single owner once installed: `takt:autostart` stops a manually
      started server first, so the agent cannot end up respawning against a taken port
      (it had looped 55 times), and retries the bootstrap once because launchd refuses the
      same label straight after a bootout.
- [x] `make start`, `make stop` and `make status` defer to the login item when it exists;
      the app bundle kickstarts it instead of starting a second server.
- [x] Verified: killing the server brings it back within seconds (KeepAlive), and removing
      the login item hands the port back.

## R21 — Cleanup

### R21.1 The web-app install path is gone

- [-] Manifest, service worker, offline page, the 512 px icon and the apple-mobile meta tags
      were removed on request: Takt ships as a native app bundle, so a second install path
      only added surface. The 192 px icon stays as the notification icon.

### R21.2 Dead code removed

- [x] `DueState::borderClass` (replaced by the inset accent), `AbsenceType::countsAsVacation`,
      `EntryType::accent`, `EntryType::options`, the `plus-circle` icon and six orphaned
      language keys (`form.no_task`, `history.subtitle`, `settings.tags_hint`,
      `settings.active`, `month.title`, `month.subtitle`).
- [x] Swept for orphans: no unreferenced classes, views, components or stray files remain;
      every language key a view asks for resolves in both locales.

### R21.3 Logs rotate

- [x] `LOG_CHANNEL=daily` with `LOG_LEVEL=info` — the single log file had grown to 6 MB.
      The old `laravel.log` is left in place; it can be deleted by hand.

## R22 — Public repository

### R22.1 Install command

- [x] `install.sh` for `curl … | bash`: checks git, composer, node and PHP 8.3+ with
      `pdo_sqlite` and `gd` (naming what is missing), clones or updates, installs the
      dependencies, prepares `.env`, key, database and icon, and on macOS builds the app
      bundle and — asked when a terminal is attached — the login item.
- [x] Overridable through `TAKT_DIR`, `TAKT_REPO`, `TAKT_REF`, `TAKT_AUTOSTART`.

### R22.2 Repository hygiene

- [x] `.gitignore` covers `.env`, the SQLite database, the pid file, generated icons,
      backups and the agent tooling state — checked file by file before the first commit.
- [x] MIT licence, GitHub Actions running the test suite and the code style on PHP 8.4,
      README with the one-line install, manual steps and requirements.
- [x] No personal data in the tracked files; `.env.example` carries no key.

### R22.3 App window like an editor

- [x] The window buttons float over the app surface (no bar with the app name), the top
      strip moves the window, and the page reserves room for the buttons.
- [x] Inside the app window only the content scrolls, the sidebar is a static column, the
      reserved scrollbar gutter and the scrollbars themselves are gone — that dark strip on
      the right was the gutter, and the sticky sidebar in a grid track was the shivering.
- [x] The overrides sit unlayered at the end of the stylesheet, because a Tailwind utility
      would otherwise outrank them, and the document is marked `data-shell="native"` from
      the user agent instead of from a script that ran before `documentElement` existed.
- [x] A service worker left over from the removed web-app layer is unregistered on load.

### R22.4 Popovers and window dragging, fixed properly

- [x] `.nav-menu` and the stacking rule had been deleted by an earlier cleanup edit, which is
      why both popovers turned transparent again. They are back, and the ground is now an
      explicit opaque `--color-popover` per theme instead of two stacked gradients, so it
      cannot depend on how an engine resolves a translucent token.
- [x] Measured in a real WKWebView (not assumed): the panel paints `#121a2b` instead of
      letting the page through.
- [x] `StylesheetTest` guards the compiled stylesheet — a vanishing rule or a translucent
      popover value now fails the suite.
- [x] The drag strip starts the drag explicitly via `performDrag`; the earlier `mouseDown`
      override consumed the event before the window server could move the window. Its frame
      comes from constraints instead of an autoresizing mask.

## R23 — Published

### R23.1 Repository

- [x] Public repository with description, ten topics, MIT licence and a green CI run
      (tests + code style on PHP 8.4, Node 22).
- [x] CI renders the notification icon first: `public/icons` is generated and ignored, so a
      fresh checkout had none and the test that guards it failed.
- [x] `tests/Unit` carries real unit tests for the duration helper — the suite was declared
      in `phpunit.xml` but the directory was empty, and git does not carry empty directories,
      so every clone failed with exit code 2.

### R23.2 Two commands

- [x] `install.sh` and `update.sh`, both verified end to end against the published
      repository: clone, dependencies, database, icon, app bundle; update reports
      "Schon aktuell" when there is nothing to pull and refuses to run over local changes.
- [x] Fixed a false negative that aborted every install: `php -m | grep -q` closes the pipe
      early, which fails the pipeline under `set -o pipefail`. The check asks PHP directly.

### R23.3 A second installation cannot hijack the app

- [x] `takt:app` keeps a bundle that points at another installation and names it, unless
      `--force` is passed — the end-to-end install test had silently re-pointed the app at a
      throwaway checkout.

## R24 — Development section

A section for the programmer's day, reachable as its own sidebar entry with four tabs.

### R24.1 Today's commits

- [x] Registered project folders are read with `git log` for the chosen day, grouped per
      project, with short sha, subject and time; the day can be paged.
- [x] "Mine" means the repository's own `user.email` plus the account address, so work
      identities per repository are covered.
- [x] Missing folder or missing `.git` is reported per project instead of failing the page.

### R24.2 Snippets

- [x] Commands and text blocks with a label, one click to the clipboard from the page, the
      overview card or the ⌘K palette; a copy counts, and the most used float to the top.

### R24.3 Review queue

- [x] Open pull requests waiting for my review and my own open ones, oldest first, with a
      red mark above 24 hours; cached for two minutes with a manual reload.
- [x] Needs a GitHub token with read access, entered once in the settings; a rejected token
      (401) or an unreachable API is reported in plain words instead of an empty list.
- [x] Only strings and numbers reach the cache. Laravel refuses to unserialize classes from a
      cache store (`serializable_classes: false`), so a cached Carbon came back as an
      incomplete object and took the whole page down; dates are cached as ISO strings. A cache
      entry that is not the expected shape is thrown away and fetched again.

### R24.4 Project launcher

- [x] Per project: folder, repository, start command and port. Start and stop run the
      configured command in the project folder, the state comes from the recorded pid and
      from probing the port; a link opens the running app.

### R24.5 Test post builder

- [x] Builds the three-line block for the testing channel from ticket, PR and instance.
      A bare key (`COR-6944`), a bare number (`2456`) or a bare instance id are expanded
      through templates; a full URL is taken as it is.
- [x] The Linear link keeps the key upper case and appends the title as a slug, exactly like
      Linear's own URLs.
- [x] Templates are configurable per user; the preview names what is still missing and the
      whole block copies with one click.
- [x] Three fields only: ticket, PR, instance. The instance field carries the id and, behind
      a slash, the path (`b63d4865/mod/zeiterfassung/?fn=…`).
- [x] The block posts straight into Slack, under the user's own name and avatar — which needs a
      user token (`xoxp-`) with `chat:write`; a bot token would post as an app. Token and
      channel live in the settings, the token stored encrypted and never rendered back.
- [x] The button only appears once both are set; posting asks for confirmation first (a post
      cannot be taken back) and links to the message afterwards. A rejected token, an unknown
      channel, a channel the user has not joined, a missing scope or an unreachable API are
      reported in plain words, and an incomplete block is never sent.

### R24.6 Registering a project

- [x] The folder is picked inside the page: the button sits in the field itself and opens
      Takt's own folder dialog — path as clickable crumbs, one row per sub-folder, git
      repositories marked. A browser hands out no absolute path, and the local server does.
- [x] The dialog reads only below the home directory: a path is resolved first and refused
      unless it still sits inside it, files and hidden folders stay out of the list.
- [x] Typing or pasting a path still works the same way.
- [x] What the folder already states fills the rest of the form: name from the folder,
      repository from `git remote origin`, start command from a `Makefile` target,
      `package.json` script or `artisan`, port from `Makefile`, `.env` or the vite config.
- [x] `make start` is the default start command. The port stays optional — it is only used
      for the state dot and the open link, never to run the command.

## R26 Booking an absence from the calendar

- [x] Days in the month grid are marked by holding the mouse and dragging across them; a long
      press marks a single day. A plain click still follows the day's own link.
- [x] Letting go opens the absence window right there, with the marked range already set and
      the range spelled out in words; escape or cancel closes it and clears the marking.
- [x] Saving books the absence through the existing endpoint and swaps the calendar back in,
      so the new range shows up without a reload.

## R28 Make targets from Takt

- [x] The targets of a project's Makefile are read, listed with their `## description`, and run
      with one click. Special targets, pattern rules and variables are not offered.
- [x] A run is detached and writes into its own log; the dialog follows the output while it
      runs, shows the state and the exit code, and can stop it. Polling only happens while a
      run is open.
- [x] The target is never taken from the request as a command: it is looked up in the project's
      own Makefile and only its name is passed on. A shell fragment fails validation.
- [x] The run goes through a login shell with the usual tool directories on the PATH — Takt is
      normally started by a login item, whose bare PATH made every Makefile calling docker fail
      with "docker: No such file or directory".
- [x] Projects are collapsed by default and remember what was opened; a filter narrows the
      targets across all projects at once.
- [x] Finished runs and their output are pruned after a week, together with the trash.

## R29 Reviews per repository

- [x] The pull requests of a registered project are read per repository, not through the search
      API: search silently returns nothing for a repository the token cannot see, while the
      repository endpoint answers 404 — so Takt says "no access to this repository" instead of
      showing an empty list.
- [x] "My open pull requests" is grouped per project, with everything else under other
      repositories; both sections are collapsed by default.
- [x] The development page renders what the cache holds and fetches the sections afterwards —
      a fresh fetch costs over a second and the rest of the page has no reason to wait.
- [x] Commits are collapsible per project, closed by default, and remember what was opened.
- [x] Paging through the days replaces only the header and the commits; the reviews stay as they
      are, and the browser's back button pages back.

## R30 Interactive runs

- [x] A target that expects a terminal gets one: the run happens inside a pseudo terminal
      (`bin/takt-pty`), which is what `docker compose exec` without `-T` needs to work at all.
- [x] A prompt can be answered from the dialog: what is typed goes through the run's FIFO into
      the command's input. The field only appears while such a run is going.
- [x] Terminal output is cleaned before it is shown — colour codes dropped, and a carriage
      return rewrites its line, so a progress bar stays one line instead of hundreds.
- [x] Without python3 for the helper, runs work as before, just without a terminal and without
      the input field.

## R31 Docker

- [x] The containers of this machine, grouped by compose project, running groups first, with
      state, image, age and the published ports as links.
- [x] Start, stop and restart per container; logs in a window. Removing a container is not
      offered — reversible actions only.
- [x] Only an id from the current list is ever acted on: the page sends an id, it is looked up,
      and the looked-up id reaches docker. A shell fragment fails validation.
- [x] A missing docker, a stopped daemon and any other error are reported in plain words.
- [x] The list refreshes itself every few seconds while the page is in front, and stops when the
      tab goes to the back.

## R32 Guides where the field is

- [x] Slack and GitHub tokens carry an (i) whose help appears on hover and on keyboard focus:
      the steps to create the token, with the scope that actually matters.
- [x] A click pins the help open so the links in it can be used; a click outside or escape
      closes it again, and a click inside it never does. The links open in a new tab.

## R25 Dashboard of widgets

- [x] The dashboard is a tile board: six columns, a fixed row height, every widget states how
      many columns and rows it takes. A tile keeps its shape and its content scrolls inside,
      so no widget can punch a hole into the layout.
- [x] Arranged on the dashboard itself, the way a home screen is: "Dashboard anpassen" starts
      the edit mode, the tiles wiggle, a minus badge at the top left takes one off, the pill at
      the bottom right changes width and height, and a tile can be dragged to a new place.
- [x] The gallery of everything that is not on the board slides in from the right; an entry is
      dragged onto the board or tapped to add it. The board makes room for the gallery instead
      of being covered by it.
- [x] Nothing is stored while arranging: "Fertig" writes the whole layout at once and pulls the
      page back in place through the region swap, never a reload. Leaving the page with an
      unsaved arrangement still writes it.
- [x] A widget pushed onto the board shows its real content right away — rendered on its own
      by the server, without storing anything — and stays marked (dashed, softly pulsing)
      until the save happens.
- [x] "Fertig" saves, the x next to the gallery throws the arrangement away and escape does
      the same; the mode is left the moment it is clicked, not when the server answers.
- [x] The gallery takes only the width it needs; the board keeps enough room that no tile is
      squeezed into a column of headlines, and the tool pill never sits on a widget's content.
- [x] Until something is arranged, the dashboard is the default set — timer, key figures, week
      chart, day note, tasks, booking form, entries — and it can be restored at any time. An
      empty board stays empty, because that was a decision, not an untouched default.
- [x] 21 widgets in three groups, among them month summary, week trend, year heatmap, upcoming
      days off, tasks by label, task progress, commits today, commits this week, review queue,
      my pull requests, project launcher, snippets, test post and repository links.
- [x] Only the widgets on the board load data — the GitHub-backed ones cost nothing when they
      are not shown.

## R27 One command, and an address with a name

- [x] After `install.sh` nothing is left to run: dependencies, `.env`, key, database, icons,
      the host name, the macOS app, the login item, the running server and the opened app.
- [x] Takt answers on `local.takt.de` instead of `localhost`, taken from `APP_URL`. The server
      binds to `127.0.0.1`; the name resolves there through one line in `/etc/hosts`.
- [x] That line is the only step needing administrator rights. It is asked for once during the
      install; skipping it keeps Takt on `localhost` and says so.
- [x] `APP_URL` only carries the name once the name really reaches this machine — otherwise
      every link Takt writes, assets included, would point at an address nothing answers on.
- [x] `takt:hostname <name>` sets it later, `--remove` goes back, `--dry-run` shows what would
      change; the app bundle is rebuilt afterwards, because it carries the address.
- [x] A login item that belongs to another copy of Takt is neither used nor taken over
      silently — `make`, the app bundle and `takt:autostart` all check where it points.

## R33 Navigation that stays put

- [x] A "back to now" button (current week, current month) is always there and simply inactive
      while it has nothing to do — a button that appears and disappears moves its neighbours.

## R34 Generated history respects the calendar

- [x] `takt:history` skips public holidays and absences. Such a day carries no target, so work
      booked on it is overtime by definition — which is right for real work and wrong for
      generated data: it left the balance permanently in the plus (measured: +22 h from three
      holidays in one generated range).

## R35 A destructive command carries its own net

- [x] `takt:history` replaces real entries in its range. Before deleting anything it writes a
      full JSON safety copy under `storage/app/backups/<user>/…-before-history-….json`, prints
      the path, and only then asks. `--force` skips the question, never the copy. Together with
      the 30-day trash a regeneration is reversible twice over — the copy imports through
      Settings, the entries restore from the trash.

## R36 The live clock keeps ticking

- [x] The clock ticks even when the page was loaded while nothing was running. The buttons
      swap regions instead of reloading, so an interval started only on page load left the
      clock frozen at `00:00:00` after a start.

## R37 A balance that can be traced

- [x] Time booked on a day without a target (public holiday, absence) counts as overtime in
      full — correct for real work, and the one way the balance can look inexplicable. The
      balance reports that share separately and the stats widget names it ("davon 22h an
      freien Tagen"), so the number can be traced instead of doubted.
## R38 Home office is a marker, not an absence

- [x] `home_office` is an absence type that changes nothing about the working day: it keeps
      its target, so the hours booked on it are normal hours and not overtime. Every
      calculation reads the `blocking` flag of an exemption instead of its mere presence.
- [x] A home-office entry never displaces a holiday on the same day — the marker loses, the
      day without a target wins.
- [x] Settings hold the agreed days per week; the absence page reports the days this year, the
      days in the chosen window, and the average per week against that agreement.
- [x] The widget shows this week against the agreement plus the average, over 7, 30 or 365
      days — the choice is stored, so it survives a reload.
## R39 A single-day absence is visible on its own day

- [x] `starts_on` holds a datetime, so comparing it against a bare date (`<= '2026-08-26'`)
      never matched an absence stored as `2026-08-26 00:00:00`. Every range query that ended
      on the covered day came back empty — which is why the timer never once said
      "today: vacation". The scope compares against the ends of the days.
## R40 The widget gallery shows the shape

- [x] Each gallery card carries the tile's real proportion (span to rows, computed from the
      board's own column and row size) and a schematic of its content — bars, lines, tiles,
      fields. A scaled-down copy of the real widget is unreadable at gallery width; a
      schematic is not.
- [x] Pointing at a card opens the peek: the real widget, rendered at the width its span gets
      on the board and scaled into a panel beside the gallery. Fetched once per widget.
- [x] The peek fits what it shows: the frame takes the height the widget actually renders (the
      board height is only the cap) and the width of the scaled widget, and the panel wraps
      the frame — measured 213 px of content in a 280 px frame before, which read as broken.
- [x] Scrolling closes the peek instead of dragging it along, and a short pause afterwards
      swallows the pointerover events a moving list fires under a still pointer. Below the
      panel's breakpoint nothing opens at all — a hidden panel measures zero, and scaling by
      that mirrored the widget.
- [x] The gallery filters by group and by text, and a removed tile returns as a full card from
      a catalogue pool — so no card markup is built twice.
## R41 The development page waits for nobody

- [x] Every GitHub call goes into one pool: identity, one call per repository, and the search.
      Sequentially this was 3461 ms for three repositories; pooled it is ~940 ms, and the page
      itself never waits for it.
- [x] Commits are read with one `git log` per repository, all at once — 293 ms for four
      repositories became 127 ms, and the address bar is the same either way.
