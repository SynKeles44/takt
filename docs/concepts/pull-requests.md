# Concept — the pull request area (replacing releases)

Releases showed tags and offered nothing to do. Pull requests are where the day actually gets
stuck, so that is what the area should be: a place to see what blocks whom, and to act on it.

Releases do not disappear — they become one view inside it ("what was merged since the last
tag"), which is the only question tags really answered.

## Three lists, one question each

| List | The question |
|---|---|
| **Wartet auf mich** | what am I blocking |
| **Meine** | what is blocking me |
| **Alle offenen** | what is going on in the team — including other people's |

The third one is new and is what makes this an area rather than a widget: every open pull
request of every registered repository, with author, reviewers and state. Filterable by
repository, author, label, draft, and "touches a project I work on".

## What a row shows without being opened

- title, id, repository, author (avatar initials, like the account chip)
- **review state**: draft / awaiting review / changes requested / approved, as a colour
- **CI**: green, red, running, none — red carries the failing job's name
- **mergeability**: clean, conflicts, behind base
- size as `+312 −87` and the number of files, because a 40-line pull request and a 2000-line one
  are not the same request
- age of the last movement, and a mark past a week
- the ticket id, linked to the ticket board

## What can be done from here

Read-only first, then the writes — every one an explicit action with a visible result:

- **open the diff** (files changed, per project, collapsed by default)
- **failing checks**: the failed jobs with the last lines of their log, via `gh run view
  --log-failed`, so a red does not require a browser
- **approve**, **request changes**, **comment** — the three review verbs
- **request a reviewer**, **assign myself**
- **draft → ready**, **ready → draft**
- **merge**, behind an explicit confirmation naming the target branch (a Hard-Floor action:
  never a side effect, never a bulk operation)
- **rerun failed checks**
- **copy** the links, in the shapes the copy feature already offers

## Features that exist nowhere else

1. **The blocking graph.** Who waits on whom: my pull requests waiting for review, reviews
   waiting for me, and pull requests whose base is another open pull request — the merge order
   nobody can see on GitHub.
2. **Time next to the pull request.** How long this branch was worked on (from the ticket's
   time), how long the pull request has waited, how long my reviews usually take.
3. **Review queue by cost.** Sort what waits for me by size and age, so the ten-line fix does
   not sit behind the thousand-line refactor for two days.
4. **Stale sweep.** Pull requests with conflicts, with a red CI for days, or without movement —
   one list, one place to act.
5. **Release view.** What was merged since the last tag per project, grouped by ticket, as a
   draft for release notes. That is the releases area, finally useful.
6. **My own PRs across projects, in one queue**, with the copy shapes that already exist.

## Costs, and how they are paid

The current review fetch is one pooled round trip and 940 ms cold. This area needs more:
review state, checks and mergeability are separate fields. Three rules keep it honest:

- **One pooled request per view**, never one per row.
- **Everything cached** like the reviews are, with an explicit reload; the list never blocks the
  page — the page renders from cache and fills in.
- **Detail on demand.** Diff, logs and checks are fetched when a row is opened, never for the
  list.

The GraphQL API answers review state, checks and mergeability in one query per repository, which
is the version worth building; the REST endpoints would need one call per pull request.

## What stays out

- No merge queue management, no branch protection editing — that is GitHub's own surface.
- No writing code review comments on individual lines. The diff is for reading here; line
  comments belong in the editor or on GitHub.
- No bulk merges, ever.

## Phases

1. The three lists with the full row (state, CI, mergeability, size, age) from one pooled
   GraphQL query per repository, cached.
2. Detail on demand: files changed, failing checks with log lines.
3. The review verbs: approve, request changes, comment, request reviewer, self-assign.
4. Draft toggle, rerun checks, merge behind a confirmation.
5. The blocking graph and the review queue by cost.
6. The release view, replacing what the releases area did.
