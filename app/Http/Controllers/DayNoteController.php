<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DayNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DayNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $body = trim((string) ($data['body'] ?? ''));

        $note = DayNote::query()->whereDate('day', $data['day'])->first();

        if ($body === '') {
            $note?->delete();

            return back()->with('status', __('app.notes.cleared'));
        }

        if ($note === null) {
            DayNote::query()->create(['day' => $data['day'], 'body' => $body]);
        } else {
            $note->update(['body' => $body]);
        }

        return back()->with('status', __('app.notes.saved'));
    }
}
