<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\DayNote;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\StepTemplate;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Services\MakeTargets;
use App\Services\Releases;
use App\Services\Reviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.str_replace('%', '\%', $term).'%';

        $todos = Todo::query()
            ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like))
            ->orderByRaw('completed_at is not null')
            ->limit(6)
            ->get()
            ->map(fn (Todo $todo): array => [
                'group' => __('app.nav.todos'),
                'label' => $todo->title,
                'hint' => $todo->due_at !== null ? $todo->dueLabel() : ($todo->isDone() ? __('app.due.done') : ''),
                'url' => route('todos.edit', $todo),
            ]);

        $entries = TimeEntry::query()
            ->whereNotNull('note')
            ->where('note', 'like', $like)
            ->orderByDesc('started_at')
            ->limit(6)
            ->get()
            ->map(fn (TimeEntry $entry): array => [
                'group' => __('app.nav.history'),
                'label' => $entry->note,
                'hint' => $entry->started_at->isoFormat('dd, D. MMM YYYY'),
                'url' => route('history', ['from' => $entry->started_at->copy()->startOfWeek()->toDateString()]),
            ]);

        $notes = DayNote::query()
            ->where('body', 'like', $like)
            ->orderByDesc('day')
            ->limit(4)
            ->get()
            ->map(fn (DayNote $note): array => [
                'group' => __('app.notes.title'),
                'label' => Str::limit($note->body, 70),
                'hint' => $note->day->isoFormat('dd, D. MMM YYYY'),
                'url' => route('history', ['from' => $note->day->copy()->startOfWeek()->toDateString()]),
            ]);

        $snippets = Snippet::query()
            ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like))
            ->inOrder()
            ->limit(6)
            ->get()
            ->map(fn (Snippet $snippet): array => [
                'group' => __('app.dev.snippets'),
                'label' => $snippet->title,
                'hint' => Str::limit($snippet->body, 70),
                'copy' => $snippet->body,
                'ping' => route('snippets.used', $snippet),
            ]);

        return response()->json([
            'results' => $snippets
                ->concat($todos)
                ->concat($entries)
                ->concat($notes)
                ->concat($this->projects($like))
                ->concat($this->absences($like))
                ->concat($this->tags($like))
                ->concat($this->templates($like))
                ->concat($this->targets($term))
                ->concat($this->pulls($request, $term))
                ->concat($this->releases($term))
                ->values()
                ->all(),
        ]);
    }

    /** @return Collection<int, array> */
    private function projects(string $like): Collection
    {
        return Project::query()
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('path', 'like', $like))
            ->inOrder()
            ->limit(5)
            ->get()
            ->map(fn (Project $project): array => [
                'group' => __('app.dev.projects'),
                'label' => $project->name,
                'hint' => $project->path,
                'url' => route('projects'),
            ]);
    }

    /** @return Collection<int, array> */
    private function absences(string $like): Collection
    {
        return Absence::query()
            ->where('note', 'like', $like)
            ->orderByDesc('starts_on')
            ->limit(4)
            ->get()
            ->map(fn (Absence $absence): array => [
                'group' => __('app.absence.title'),
                'label' => $absence->note ?: $absence->type->label(),
                'hint' => $absence->type->label().' · '.$absence->starts_on->isoFormat('D. MMM YYYY'),
                'url' => route('absences'),
            ]);
    }

    /** @return Collection<int, array> */
    private function tags(string $like): Collection
    {
        return Tag::query()
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Tag $tag): array => [
                'group' => __('app.tags.title'),
                'label' => $tag->name,
                'hint' => $tag->name,
                'url' => route('tags.index'),
            ]);
    }

    /** @return Collection<int, array> */
    private function templates(string $like): Collection
    {
        return StepTemplate::query()
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (StepTemplate $template): array => [
                'group' => __('app.templates.title'),
                'label' => $template->name,
                'hint' => trans_choice('app.templates.count', $template->items()->count()),
                'url' => route('templates'),
            ]);
    }

    /**
     * Make targets, commits and pull requests answer from the caches the development page
     * already fills — the palette must not start a git or GitHub round trip on every keystroke.
     *
     * @return Collection<int, array>
     */
    private function targets(string $term): Collection
    {
        $needle = mb_strtolower($term);

        return Project::query()
            ->inOrder()
            ->get()
            ->flatMap(fn (Project $project): array => array_map(
                fn (array $target): array => [
                    'group' => __('app.dev.commands'),
                    'label' => $target['name'],
                    'hint' => $project->name.($target['description'] === null ? '' : ' · '.$target['description']),
                    'url' => route('commands'),
                ],
                array_filter(
                    app(MakeTargets::class)->forProject($project),
                    fn (array $target): bool => str_contains(mb_strtolower($target['name']), $needle)
                        || str_contains(mb_strtolower((string) $target['description']), $needle),
                ),
            ))
            ->take(6);
    }

    /** @return Collection<int, array> */
    private function pulls(Request $request, string $term): Collection
    {
        $reviews = app(Reviews::class)->cached($request->user());

        if ($reviews === null) {
            return collect();
        }

        $needle = mb_strtolower($term);

        return collect([...$reviews['mine'], ...$reviews['incoming']])
            ->filter(fn (array $pull): bool => str_contains(mb_strtolower($pull['title']), $needle)
                || str_contains(mb_strtolower($pull['repository'].' #'.$pull['number']), $needle))
            ->take(6)
            ->map(fn (array $pull): array => [
                'group' => __('app.dev.my_pulls'),
                'label' => $pull['title'],
                'hint' => $pull['repository'].' #'.$pull['number'],
                'url' => $pull['url'],
                'external' => true,
            ]);
    }

    /** @return Collection<int, array> */
    private function releases(string $term): Collection
    {
        $needle = mb_strtolower($term);

        return (app(Releases::class)->cached() ?? collect())
            ->flatMap(fn (array $group): array => array_map(
                fn (array $release): array => [
                    'group' => __('app.dev.releases'),
                    'label' => $release['tag'],
                    'hint' => $group['project']->name.' · '.$release['at']->isoFormat('D. MMM YYYY'),
                    'url' => route('releases'),
                ],
                array_filter(
                    $group['releases'],
                    fn (array $release): bool => str_contains(mb_strtolower($release['tag']), $needle)
                        || str_contains(mb_strtolower($release['subject']), $needle),
                ),
            ))
            ->take(5);
    }
}
