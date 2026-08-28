# Concept — the ticket board

## What Takt has that Linear does not

Linear knows the ticket. Takt knows the **time** and the **work**: which branch, which commits,
which pull request, how long the timer ran. It also runs locally, which means it may hold things
that have no business being in a shared tracker — private notes, a personal order, a guess.

That is the whole design principle of this area: **Linear stays the source of the ticket; Takt
owns everything about how the ticket is actually being worked on.**

## The board

Kanban columns that describe *my* day, deliberately not Linear's workflow states:

| Column | Meaning |
|---|---|
| **Heute** | what I intend to touch today |
| **Als nächstes** | the queue behind it |
| **Wartet** | blocked on somebody else (review, question, deploy) |
| **Zurückgestellt** | consciously parked, with a reason |
| **Fertig** | closed in Linear or merged — falls off the board after a week |

A ticket's column is Takt's own value and never written to Linear. Linear's state travels along
as a pill on the card, so the two are visible side by side — and a contradiction becomes
obvious ("Linear says In Review, the pull request has changes requested").

Drag and drop between columns reuses the board mechanics the dashboard already has.

## The card

One line each, in this order of prominence:

- ticket id, title
- Linear state pill · priority · assignee (when it is not me)
- **time booked on this ticket** against an estimate I set locally
- pull request dot: draft / open / changes requested / approved / merged, plus CI colour
- commits count and the branch, if one exists
- age of the last movement — the number that says "this is stuck"

## Features that exist nowhere else

1. **Start the timer on a ticket.** Not on a project — on the ticket. Every booking made this
   way carries the id, so the estimate-versus-actual is real data instead of the even split the
   current list has to fall back on.
2. **Estimate versus actual.** My guess, local. Nobody else sees it, so it can be honest.
3. **Private notes and a checklist per ticket.** Local, never pushed. The place for "ask Mareike
   about the export format" that does not belong in a public comment.
4. **Reality check against git.** A state that git contradicts is flagged: In Review without an
   open pull request, In Progress without a commit in five days, Done with an unmerged branch.
5. **Waiting detection.** No commit, no pull request movement, no comment for N days — the
   column "Wartet" fills itself as a proposal, which I confirm or reject.
6. **Focus mode.** One ticket is active: the timer runs on it, the card shows the branch, the
   pull request, the notes and the checklist in one place, and the menu bar shows the id.
7. **Local-only tickets.** Things I do that have no Linear issue — a refactor, an experiment,
   an errand. Same card, same time tracking, marked as local. Optionally pushable to Linear
   later, never automatically.
8. **The day plan.** Pick tickets for today, put a planned duration on each, and compare against
   what was actually booked in the evening. That is the loop nothing else in my stack closes.

## Editing, and where the line is

Writing back to Linear is possible and wanted, but narrow and always explicit:

- **state** (its own workflow states, read from Linear so the list is never invented)
- **priority**
- **assignee** (me / nobody, more only if it turns out to be needed)
- **a comment**
- **title and description** — deliberately last, because renaming somebody else's ticket from a
  side tool is how confusion starts

Every write is a single deliberate action with a visible result, never a side effect of moving a
card. Moving a card changes Takt's column; changing Linear's state is a separate control on the
card. Failures surface as themselves (Linear's own message), and the local value is never
silently rolled back — it stays and says "not sent yet".

## What stays out

- No second inbox, no notifications from Linear — Takt is not a Linear client.
- No sync engine. There is one direction of truth per field: Linear owns the issue, Takt owns
  the column, the estimate, the notes, the time.
- No bulk edits.

## Phases

1. Ticket detail: notes, checklist, estimate, local tickets — all Takt-owned, no API writes.
2. Time on a ticket: the timer takes an id, bookings carry it, actual-versus-estimate replaces
   the even split.
3. The board itself: columns, drag and drop, the card as described.
4. Reality check and waiting detection.
5. Writes to Linear: state, priority, assignee, comment.
6. Focus mode and the day plan.
