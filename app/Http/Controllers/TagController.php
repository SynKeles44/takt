<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TagColor;
use App\Http\Requests\Todos\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TagController extends Controller
{
    private const array LEAD_OPTIONS = [0, 15, 30, 60, 120, 240, 1440, 2880, 10080];

    public function index(): View
    {
        return view('tags.index', [
            'tags' => Tag::query()->withCount('todos')->orderBy('name')->get(),
            'tagColors' => TagColor::cases(),
            'leadOptions' => self::LEAD_OPTIONS,
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        Tag::query()->create($request->payload());

        return back()->with('status', __('app.flash.tag_created'));
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->payload());

        return back()->with('status', __('app.flash.tag_saved'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return back()->with('status', __('app.flash.tag_deleted'));
    }
}
