<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TodoAttachmentController extends Controller
{
    private const int MAX_KILOBYTES = 10240;

    private const array ALLOWED = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'docx', 'xlsx', 'zip'];

    public function store(Request $request, Todo $todo): RedirectResponse
    {
        $request->validate([
            'file' => ['required', File::types(self::ALLOWED)->max(self::MAX_KILOBYTES)],
        ]);

        $file = $request->file('file');

        $todo->attachments()->create([
            'name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'path' => $file->store('todos/'.$todo->getKey(), 'local'),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return back()->with('status', __('app.flash.attachment_added'));
    }

    public function show(Todo $todo, TodoAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Todo $todo, TodoAttachment $attachment): RedirectResponse
    {
        $attachment->delete();

        return back()->with('status', __('app.flash.attachment_deleted'));
    }
}
