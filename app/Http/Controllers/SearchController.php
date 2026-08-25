<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DayNote;
use App\Models\Snippet;
use App\Models\TimeEntry;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'results' => $snippets->concat($todos)->concat($entries)->concat($notes)->values()->all(),
        ]);
    }
}
