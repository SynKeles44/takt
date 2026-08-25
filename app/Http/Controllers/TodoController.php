<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DueState;
use App\Http\Requests\TodoRequest;
use App\Models\StepTemplate;
use App\Models\Tag;
use App\Models\Todo;
use App\Services\TodoInputParser;
use App\Services\TodoMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TodoController extends Controller
{
    private const array FILTERS = ['open', 'done', 'all'];

    public function index(Request $request, TodoMaintenance $maintenance): View
    {
        $maintenance->run();

        $filter = in_array($request->query('filter'), self::FILTERS, true)
            ? $request->query('filter')
            : 'open';

        $todos = Todo::query()
            ->when($filter === 'open', fn ($query) => $query->open())
            ->when($filter === 'done', fn ($query) => $query->done())
            ->with(['tags', 'steps'])
            ->inOrder()
            ->get();

        return view('todos.index', [
            'groups' => $this->group($todos),
            'filter' => $filter,
            'openCount' => Todo::query()->open()->count(),
            'doneCount' => Todo::query()->done()->count(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'templates' => StepTemplate::query()->with('items')->orderBy('name')->get(),
        ]);
    }

    public function show(Todo $todo): View
    {
        return view('todos.show', [
            'todo' => $todo->load(['tags', 'steps', 'attachments']),
            'templates' => StepTemplate::query()->with('items')->orderBy('name')->get(),
        ]);
    }

    public function store(TodoRequest $request, TodoInputParser $parser): RedirectResponse
    {
        $payload = $request->payload();
        $tagIds = $request->tagIds();

        $parsed = $parser->parse($payload['title']);
        $payload['title'] = $parsed->title;

        if ($payload['due_at'] === null && $parsed->dueAt !== null) {
            $payload['due_at'] = $parsed->dueAt;
            $payload['due_has_time'] = $parsed->hasTime;
        }

        if ($parsed->tags !== []) {
            $tagIds = array_values(array_unique([
                ...$tagIds,
                ...$parser->matchTagIds($parsed->tags, Tag::query()->get()),
            ]));
        }

        $todo = Todo::query()->create($payload + [
            'position' => (int) Todo::query()->max('position') + 1,
        ]);

        $todo->tags()->sync($tagIds);

        $template = $request->stepTemplate();

        if ($template !== null) {
            foreach ($template->items as $index => $item) {
                $todo->steps()->create(['title' => $item->title, 'position' => $index + 1]);
            }
        }

        return back()->with('status', $todo->due_at !== null && $parsed->dueAt !== null
            ? __('app.flash.todo_created_dated', ['date' => $todo->dueLabel()])
            : __('app.flash.todo_created'));
    }

    public function edit(Todo $todo): View
    {
        return view('todos.edit', [
            'todo' => $todo->load('tags'),
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(TodoRequest $request, Todo $todo): RedirectResponse
    {
        $todo->update($request->payload());
        $todo->tags()->sync($request->tagIds());

        return redirect()->route('todos.show', $todo)->with('status', __('app.flash.todo_updated'));
    }

    public function toggle(Request $request, Todo $todo): RedirectResponse|JsonResponse
    {
        $follower = $todo->load(['tags', 'steps'])->toggle();

        $status = $follower !== null
            ? __('app.flash.todo_repeated', ['date' => $follower->due_at->isoFormat('dd, D. MMM')])
            : ($todo->isDone() ? __('app.flash.todo_done') : __('app.flash.todo_reopened'));

        if ($request->expectsJson()) {
            return response()->json([
                'done' => $todo->isDone(),
                'reload' => $follower !== null,
                'status' => $status,
            ]);
        }

        return back()->with('status', $status);
    }

    public function snooze(Request $request, Todo $todo): RedirectResponse
    {
        $data = $request->validate(['by' => ['required', 'in:hour,tomorrow,week']]);

        if ($todo->due_at === null) {
            return back()->with('status', __('app.todos.snooze_needs_date'));
        }

        $due = match ($data['by']) {
            'hour' => $todo->due_at->copy()->addHour(),
            'tomorrow' => $todo->due_at->copy()->addDay(),
            'week' => $todo->due_at->copy()->addWeek(),
        };

        $todo->update(['due_at' => $due, 'due_has_time' => $data['by'] === 'hour' ? true : $todo->due_has_time]);

        return back()->with('status', __('app.todos.snoozed', ['date' => $todo->dueLabel()]));
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $todo->delete();

        // coming from the task's own page there is nothing left to go back to
        $onOwnPage = parse_url((string) url()->previous(), PHP_URL_PATH)
            === parse_url(route('todos.show', $todo), PHP_URL_PATH);

        $redirect = $onOwnPage ? redirect()->route('todos.index') : back();

        return $redirect
            ->with('status', __('app.flash.todo_deleted'))
            ->with('undo', ['url' => route('trash.todo.restore', $todo), 'label' => __('app.trash.undo')]);
    }

    public function destroyCompleted(): RedirectResponse
    {
        $todos = Todo::query()->done()->get();
        $todos->each(fn (Todo $todo) => $todo->delete());

        return back()->with('status', trans_choice('app.flash.todos_cleared', $todos->count()));
    }

    /** @return Collection<string, Collection<int, Todo>> */
    private function group(Collection $todos): Collection
    {
        $grouped = $todos->groupBy(fn (Todo $todo): string => $todo->dueState()->value);

        $ordered = collect();

        foreach (DueState::groups() as $state) {
            $slice = $grouped->get($state->value);

            if ($slice !== null && $slice->isNotEmpty()) {
                $ordered->put($state->value, $slice->sortBy(fn (Todo $todo): string => $todo->due_at?->toDateTimeString() ?? '9999')->values());
            }
        }

        $done = $grouped->get(DueState::Done->value);

        if ($done !== null && $done->isNotEmpty()) {
            $ordered->put(DueState::Done->value, $done->sortByDesc('completed_at')->values());
        }

        return $ordered;
    }
}
