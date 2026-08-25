<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Todo;
use App\Services\Trash;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function index(Trash $trash): View
    {
        $trash->purgeExpired();

        return view('trash', [
            'entries' => $trash->entries(),
            'todos' => $trash->todos(),
            'keepDays' => Trash::KEEP_DAYS,
        ]);
    }

    public function restoreEntry(TimeEntry $entry): RedirectResponse
    {
        $entry->restore();

        return back()->with('status', __('app.trash.entry_restored'));
    }

    public function purgeEntry(TimeEntry $entry): RedirectResponse
    {
        $entry->forceDelete();

        return back()->with('status', __('app.trash.entry_purged'));
    }

    public function restoreTodo(Todo $todo): RedirectResponse
    {
        $todo->restore();

        return back()->with('status', __('app.trash.todo_restored'));
    }

    public function purgeTodo(Todo $todo): RedirectResponse
    {
        $todo->forceDelete();

        return back()->with('status', __('app.trash.todo_purged'));
    }

    public function empty(Trash $trash): RedirectResponse
    {
        $count = $trash->entries()->each(fn (TimeEntry $entry) => $entry->forceDelete())->count()
            + $trash->todos()->each(fn (Todo $todo) => $todo->forceDelete())->count();

        return back()->with('status', trans_choice('app.trash.emptied', $count));
    }
}
