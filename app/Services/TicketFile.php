<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\DayNote;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Duration;
use App\Support\Parallel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One ticket, everything about it. Deliberately not built on the collecting service: that one
 * reads every commit, branch and pull request in the window, which is the right shape for a list
 * and far too much work for a single page. Here git is asked about one id — `git log --grep` per
 * repository, in parallel — so the file opens in the time a page is allowed to take.
 *
 * The timeline is the point of this page: commits, branches, pull requests, booked time, column
 * moves, day notes that mention the id, and absences that overlapped, merged into one stream.
 * Answering "what happened with this ticket and when" takes three tools today.
 */
final class TicketFile
{
    /** How far back the git history is read for a single ticket. */
    public const int DAYS = 365;

    public function __construct(
        private readonly Reviews $reviews,
        private readonly Linear $linear,
        private readonly TicketBoard $board,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user, string $key): array
    {
        $local = Ticket::query()->where('key', $key)->first();
        $issue = $this->issue($user, $key, $local);

        $commits = $this->commits($key);
        $branches = $this->branches($key);
        $pulls = $this->pulls($user, $key);
        $entries = $this->entries($local);

        return [
            'key' => $key,
            'local' => $local,
            'issue' => $issue,
            'title' => $issue['title'] ?? $local?->title,
            'commits' => $commits,
            'branches' => $branches,
            'pulls' => $pulls,
            'entries' => $entries,
            'booked' => $entries->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()),
            'estimate' => $local?->estimate_seconds,
            'notes' => $this->dayNotes($key),
            'absences' => $this->absences($commits, $entries),
            'timeline' => $this->timeline($key, $local, $commits, $branches, $pulls, $entries),
            'contradiction' => $this->contradiction($issue, $pulls, $commits),
        ];
    }

    /**
     * A ticket may be local, may be in Linear, and a local one may have been promoted. Only a
     * key that looks like a Linear identifier is asked about at all — TAKT-3 would be a
     * guaranteed miss and a wasted request.
     *
     * @return array<string, mixed>|null
     */
    private function issue(User $user, string $key, ?Ticket $local): ?array
    {
        if ($local?->isLocal() === true && $local->promoted_url === null) {
            return null;
        }

        $result = $this->linear->forIds($user, [$key]);
        $issue = $result['issues'][$key] ?? null;

        return is_array($issue) ? $issue : null;
    }

    /**
     * `git log --grep` per repository, in parallel. The id is matched literally, so COR-6839
     * cannot match COR-68391.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function commits(string $key): Collection
    {
        $projects = Project::query()->inOrder()->get()
            ->filter(fn (Project $project): bool => $project->exists() && $project->isGitRepository());

        if ($projects->isEmpty()) {
            return collect();
        }

        $since = Carbon::today()->subDays(self::DAYS)->toDateString();

        $outputs = Parallel::run($projects->mapWithKeys(fn (Project $project): array => [
            'p'.$project->getKey() => [
                'git', '-C', $project->absolutePath(), 'log',
                '--all', '--no-merges', '--regexp-ignore-case',
                '--grep='.$key.'\b',
                '--since='.$since,
                '--format=%h%x1f%s%x1f%aI%x1f%an',
                '--max-count=200',
            ],
        ])->all());

        $commits = [];

        foreach ($outputs as $poolKey => $output) {
            $project = $projects->firstWhere('id', (int) mb_substr((string) $poolKey, 1));

            foreach (preg_split('/\R/', trim((string) $output)) ?: [] as $line) {
                if ($line === '') {
                    continue;
                }

                [$short, $subject, $at, $author] = array_pad(explode("\x1f", $line), 4, '');

                if ($short === '') {
                    continue;
                }

                $commits[] = [
                    'short' => $short,
                    'subject' => $subject,
                    'at' => Carbon::parse($at === '' ? 'now' : $at),
                    'author' => $author,
                    'project' => $project?->name,
                ];
            }
        }

        return collect($commits)->sortByDesc(fn (array $commit): string => $commit['at']->toIso8601String())->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function branches(string $key): Collection
    {
        $projects = Project::query()->inOrder()->get()
            ->filter(fn (Project $project): bool => $project->exists() && $project->isGitRepository());

        if ($projects->isEmpty()) {
            return collect();
        }

        $outputs = Parallel::run($projects->mapWithKeys(fn (Project $project): array => [
            'p'.$project->getKey() => [
                'git', '-C', $project->absolutePath(), 'for-each-ref',
                '--sort=-committerdate',
                '--count=400',
                '--format=%(refname:short)%1f%(committerdate:iso-strict)',
                'refs/heads', 'refs/remotes',
            ],
        ])->all());

        $branches = [];

        foreach ($outputs as $poolKey => $output) {
            $project = $projects->firstWhere('id', (int) mb_substr((string) $poolKey, 1));

            foreach (preg_split('/\R/', trim((string) $output)) ?: [] as $line) {
                [$name, $at] = array_pad(explode("\x1f", $line), 2, '');

                if ($name === '' || mb_stripos($name, $key) === false) {
                    continue;
                }

                $branches[] = [
                    'name' => $name,
                    'at' => Carbon::parse($at === '' ? 'now' : $at),
                    'project' => $project?->name,
                ];
            }
        }

        return collect($branches)->unique('name')->values();
    }

    /** @return list<array<string, mixed>> */
    private function pulls(User $user, string $key): array
    {
        $reviews = $this->reviews->cached($user);

        if ($reviews === null) {
            return [];
        }

        // a pull carries title, number, repository, draft and the two dates — no branch, no
        // review state; matching on the title is all the data supports
        return array_values(array_filter(
            [...$reviews['mine'], ...$reviews['incoming']],
            fn (array $pull): bool => mb_stripos((string) $pull['title'], $key) !== false,
        ));
    }

    /** @return Collection<int, TimeEntry> */
    private function entries(?Ticket $local): Collection
    {
        if ($local === null) {
            return collect();
        }

        return TimeEntry::query()
            ->where('ticket_id', $local->getKey())
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * Day notes that mention the id. Written once in the daily note, readable from the ticket —
     * the reference works in both directions without a second input field.
     *
     * @return Collection<int, DayNote>
     */
    private function dayNotes(string $key): Collection
    {
        return DayNote::query()
            ->where('body', 'like', '%'.$key.'%')
            ->orderByDesc('day')
            ->limit(20)
            ->get();
    }

    /**
     * Absences that fell between the first and last sign of life. A ticket that lay still while
     * I was away says so, instead of counting as three days of neglect.
     *
     * @param  Collection<int, array<string, mixed>>  $commits
     * @param  Collection<int, TimeEntry>  $entries
     * @return Collection<int, Absence>
     */
    private function absences(Collection $commits, Collection $entries): Collection
    {
        $dates = [
            ...$commits->map(fn (array $commit): Carbon => $commit['at'])->all(),
            ...$entries->map(fn (TimeEntry $entry): Carbon => $entry->started_at)->all(),
        ];

        if ($dates === []) {
            return collect();
        }

        $from = collect($dates)->min();
        $to = collect($dates)->max();

        return Absence::query()
            ->overlapping($from, $to)
            ->get()
            ->filter(fn (Absence $absence): bool => $absence->type !== AbsenceType::HomeOffice)
            ->values();
    }

    /**
     * Everything that happened, newest first, in one stream.
     *
     * @param  Collection<int, array<string, mixed>>  $commits
     * @param  Collection<int, array<string, mixed>>  $branches
     * @param  list<array<string, mixed>>  $pulls
     * @param  Collection<int, TimeEntry>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function timeline(
        string $key,
        ?Ticket $local,
        Collection $commits,
        Collection $branches,
        array $pulls,
        Collection $entries,
    ): Collection {
        $events = [];

        foreach ($commits as $commit) {
            $events[] = [
                'at' => $commit['at'],
                'kind' => 'commit',
                'title' => $commit['subject'],
                'meta' => trim(($commit['project'] ?? '').' · '.$commit['short'], ' ·'),
            ];
        }

        foreach ($branches as $branch) {
            $events[] = [
                'at' => $branch['at'],
                'kind' => 'branch',
                'title' => $branch['name'],
                'meta' => (string) ($branch['project'] ?? ''),
            ];
        }

        foreach ($pulls as $pull) {
            $events[] = [
                'at' => $pull['updated_at'],
                'kind' => 'pull',
                'title' => $pull['title'],
                'meta' => trim(($pull['repository'] ?? '').' · #'.($pull['number'] ?? ''), ' ·#'),
            ];
        }

        foreach ($entries as $entry) {
            $events[] = [
                'at' => $entry->started_at,
                'kind' => 'time',
                'title' => $entry->type->label(),
                'meta' => Duration::human($entry->durationInSeconds()),
            ];
        }

        if ($local?->column_changed_at !== null && $local?->column !== null) {
            $events[] = [
                'at' => $local->column_changed_at,
                'kind' => 'column',
                'title' => $local->column->label(),
                'meta' => (string) ($local->waiting_reason ?? ''),
            ];
        }

        foreach ($this->dayNotes($key) as $note) {
            $events[] = [
                'at' => $note->day->copy()->setTime(12, 0),
                'kind' => 'note',
                'title' => mb_strimwidth((string) $note->body, 0, 160, '…'),
                'meta' => $note->day->isoFormat('D. MMM'),
            ];
        }

        return collect($events)
            ->sortByDesc(fn (array $event): string => $event['at']->toIso8601String())
            ->values();
    }

    /**
     * The most useful thing this area can show, and neither tool shows it alone: Linear's state
     * against what the code is doing. Silent when they agree.
     *
     * @param  array<string, mixed>|null  $issue
     * @param  list<array<string, mixed>>  $pulls
     * @param  Collection<int, array<string, mixed>>  $commits
     */
    private function contradiction(?array $issue, array $pulls, Collection $commits): ?string
    {
        $state = mb_strtolower((string) ($issue['state'] ?? ''));
        $type = (string) ($issue['state_type'] ?? '');

        if ($state === '') {
            return null;
        }

        // only open pull requests are fetched, and a pull carries no review verdict — so these
        // are the four contradictions the data can actually prove, not the ones I would like
        $ready = array_values(array_filter($pulls, fn (array $pull): bool => ($pull['draft'] ?? false) === false));
        $drafts = array_values(array_filter($pulls, fn (array $pull): bool => ($pull['draft'] ?? false) === true));

        if ($type === 'completed' && $pulls !== []) {
            return __('app.ticket.clash.done_open');
        }

        if ($drafts !== [] && $ready === [] && str_contains($state, 'review')) {
            return __('app.ticket.clash.draft_review');
        }

        if (in_array($type, ['backlog', 'unstarted'], true) && $ready !== []) {
            return __('app.ticket.clash.todo_ready');
        }

        if (in_array($type, ['backlog', 'unstarted'], true) && $commits->isNotEmpty()) {
            return __('app.ticket.clash.todo_commits');
        }

        return null;
    }
}
