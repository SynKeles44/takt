<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Snippet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SnippetController extends Controller
{
    public function index(): View
    {
        return view('snippets', [
            'snippets' => Snippet::query()->inOrder()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Snippet::query()->create($this->validated($request));

        return back()->with('status', __('app.dev.snippet_saved'));
    }

    public function update(Request $request, Snippet $snippet): RedirectResponse
    {
        $snippet->update($this->validated($request));

        return back()->with('status', __('app.dev.snippet_saved'));
    }

    public function destroy(Snippet $snippet): RedirectResponse
    {
        $snippet->delete();

        return back()->with('status', __('app.dev.snippet_deleted'));
    }

    /** The palette and the list report a copy, so the order follows what gets used. */
    public function used(Snippet $snippet): JsonResponse
    {
        $snippet->used();

        return response()->json(['uses' => $snippet->uses]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'label' => ['nullable', 'string', 'max:40'],
        ]);
    }
}
