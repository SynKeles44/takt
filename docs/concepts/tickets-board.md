# Concept — the ticket area

> Replaces the first version of this document. That one was right about the direction and was
> never built, which is why the area still reads as a commit list. The rewrite exists to be
> implemented, so every phase below names what ships and what proves it.

## The measurement this starts from

The area is not empty and Linear is not broken. Measured on the real account, 90-day window:

| Source | Count |
|---|---|
| Linear issues assigned to me | 55 |
| Ids found only in git (commit subjects, branch names) | 156 |
| Total rows rendered | 211 |

Three quarters of the list is git residue — old ids, other people's ids, ids from repositories
that have nothing to do with current work. The tickets are in there, outnumbered three to one.
That is the whole reason the area feels like a commit viewer: it *is* mostly commits, by row
count, and the 55 rows that matter are not privileged in any way.

So the first fix is not a feature. It is a decision about what a row is.

## The principle

**Linear owns the ticket. Takt owns the work on it.**

Takt is not a second tracker and must never try to be. It is the layer that knows what Linear
structurally cannot: how long the work actually took, what was committed, what is waiting on
whom, what I thought about it privately, and what I intend to do today. A shared tracker cannot
hold a private guess; a local app can.

Every feature below earns its place by answering: *does Linear already do this well?* If yes, it
is a link to Linear, not a reimplementation.

## What a row is

- **A ticket** is a Linear issue assigned to me, or a local ticket I created here. Those are the
  rows.
- **Git is enrichment, never a row.** Commits, branches and pull requests attach to a ticket.
- **A git-only id is not a ticket.** It goes into a collapsed "Im Code gefunden" list at the
  bottom, with one action per entry: *link to a Linear issue*, *create a local ticket from it*,
  or *ignore permanently*. Ignoring is remembered, so the list shrinks as it is used instead of
  regrowing every visit.

That single change turns 211 rows into 55 plus a footnote.

## The board

Kanban columns that describe **my** day, deliberately not Linear's workflow:

| Column | Meaning |
|---|---|
| **Heute** | what I intend to touch today |
| **Als nächstes** | the queue behind it |
| **Wartet** | blocked on somebody else — review, answer, deploy |
| **Zurückgestellt** | parked on purpose, with a reason I typed |
| **Fertig** | closed in Linear or merged; leaves the board after seven days |

The column is Takt's own value and is never written to Linear. Linear's state rides along as a
pill on the card, so the two sit side by side and a contradiction becomes visible at a glance —
*Linear says In Review, the pull request has changes requested*. That contradiction is the most
useful thing this area can show, and neither tool shows it alone.

Drag and drop reuses the dashboard's board mechanics (`resources/js/board.js`), which already
handles pointer drag, ghost element, drop target and a debounced save.

## The card

In order of prominence:

- id and title
- Linear state pill · priority · assignee when it is not me
- **time booked on this ticket**, against an estimate I set locally
- pull request dot — draft / open / changes requested / approved / merged — plus CI colour
- commit count and branch, when one exists
- **age of the last movement**: the number that says "this is stuck"

Everything else is in the file, one click away.

## The ticket file

One page per ticket, and the reason the area stops being a list:

- **Header** — id, title, Linear state, priority, a link that opens it in Linear.
- **Time** — every entry booked against this ticket, summed, next to my local estimate. Start a
  timer for this ticket straight from here.
- **My notes** — local, markdown, never synced. The place for "ask Weber about the certificate
  first" that has no business in a shared tracker.
- **Timeline** — one merged stream: commits, branch created, pull request opened, review
  received, time booked, status changed, notes added, absences that overlapped. This is the
  answer to *what happened with this ticket and when*, which today requires three tools.
- **Waiting** — how long it has been in its current Linear state, and since when it has been in
  the Wartet column, with the reason I gave.

## Time on tickets — the one thing Linear cannot do

Today the booked time per ticket is **an estimate and admits it**: a day's working time split
evenly across the tickets committed that day (`app/Services/Tickets.php`). It is a guess dressed
up in minutes.

The fix is a real link: a nullable `ticket_id` on the time entry, plus a `tickets` table for
local tickets and for the per-Linear-issue data Takt owns (column, estimate, notes, ignore flag).
Then:

- Start the timer *for a ticket* — from the ticket file, from the board card, from the command
  palette, from the menu bar.
- A booking made without a ticket can be assigned afterwards, and the day's bar in the evaluation
  area can be broken down by ticket.
- The estimate becomes falsifiable: local estimate against measured time, per ticket and as a
  personal calibration figure over the last N tickets. That number — *I estimate 60 % of what it
  takes* — exists in no other tool I use.

The split-evenly heuristic stays as a fallback for tickets with no linked entry, clearly marked
as an estimate, so history does not go blank on the day the feature lands.

## Editing, and where the line is

Written back to Linear, through the API that already works:

- title, description, state, priority, estimate — `issueUpdate`
- a comment — `commentCreate`
- assign to me / unassign
- create a Linear issue from a local ticket — `issueCreate`

Never written to Linear: my column, my notes, my local estimate, my ignore flags. Those are the
private layer, and mixing them into a shared tracker would be a bug, not a feature.

Every write is optimistic locally and reconciled on the next fetch; a failed write keeps the
local value and says so on the card, because silently losing an edit is worse than a visible
conflict.

## Local tickets

Tickets that exist only here, with their own prefix (`TAKT-1`). For the work that is real but not
team-visible yet: an idea, a chore, a "look at this before it becomes a ticket". They behave
exactly like Linear tickets on the board and in the file, and one action promotes them into
Linear when they graduate.

## Features that exist nowhere else

- **Contradiction badge** — Linear state versus pull request state versus commit activity. Shown
  when they disagree, silent when they agree.
- **Stuck list** — everything untouched for longer than N days, sorted by how long, with the
  waiting reason. The list to read on Monday morning.
- **Estimate calibration** — the personal factor described above.
- **Today's focus** — one ticket marked as current; the menu bar shows it next to the running
  clock, and the global hotkey starts its timer.
- **Ticket in the note** — the daily note can reference a ticket, and the reference shows up in
  that ticket's timeline. Written once, readable from both sides.
- **Absence overlap** — a ticket that lay still while I was away says so, instead of counting as
  three days of neglect.

## What stays out

No sprints, no cycles, no team boards, no assignee juggling, no dependency graph, no burndown.
Linear does all of it better and none of it is about *my* work. Takt shows my slice.

## Phases

Each phase is shippable on its own and leaves the area usable.

| # | Phase | Ships | Proven by |
|---|---|---|---|
| 1 | **Rows become tickets** | Linear issues are the list; git-only ids move to a collapsed section with link / create / ignore; ignore is persisted | 211 rows become 55 + footnote on the real account |
| 2 | **The board** | `tickets` table, five columns, drag and drop, Linear state pill, movement age | a ticket dragged to Wartet survives a reload |
| 3 | **The ticket file** | header, notes, commits, pull requests, timeline | timeline shows a commit, a pull request and a note in one stream |
| 4 | **Real time on tickets** | `ticket_id` on time entries, start timer for a ticket, assign afterwards, estimate versus measured | a booking made from a ticket appears in that ticket's sum, not in a split-evenly guess |
| 5 | **Write back to Linear** | title, state, priority, comment, assign, create from local | an edit made here is visible in Linear and survives the next fetch |
| 6 | **Local tickets** | own prefix, full board and file behaviour, promote to Linear | a local ticket becomes a Linear issue and keeps its notes and time |
| 7 | **The rest** | contradiction badge, stuck list, calibration, focus + menu bar, note references, absence overlap | each one has a test that fails without it |

Phase 1 is the one that changes the impression, and it needs no schema change beyond the ignore
flags. Phase 4 is the one that makes this area worth having.
