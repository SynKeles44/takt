<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Backup;
use App\Services\PreferenceFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    public function __construct(
        private readonly Backup $backup,
        private readonly PreferenceFile $preferences,
    ) {}

    public function download(Request $request): Response
    {
        return response($this->backup->json($request->user()), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s-backup-%s.json"',
                Str::slug((string) config('app.name')),
                Carbon::now()->format('Y-m-d'),
            ),
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:20480'],
        ], [], ['backup' => __('app.backup.file')]);

        $payload = json_decode((string) file_get_contents($request->file('backup')->getRealPath()), true);

        if (! is_array($payload)) {
            return back()->withErrors(['backup' => __('app.backup.invalid')]);
        }

        $report = $this->backup->import($request->user(), $payload);

        $imported = collect($report)->sum(fn (array $section): int => $section['imported']);
        $skipped = collect($report)->sum(fn (array $section): int => $section['skipped']);

        return back()->with('status', __('app.backup.restored', ['imported' => $imported, 'skipped' => $skipped]));
    }

    public function downloadSettings(Request $request): Response
    {
        return response($this->preferences->json($request->user()), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s-einstellungen-%s.json"',
                Str::slug((string) config('app.name')),
                Carbon::now()->format('Y-m-d'),
            ),
        ]);
    }

    public function restoreSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:512'],
        ], [], ['settings' => __('app.backup.settings_file')]);

        $payload = json_decode((string) file_get_contents($request->file('settings')->getRealPath()), true);

        if (! is_array($payload)) {
            return back()->withErrors(['settings' => __('app.backup.invalid')]);
        }

        $report = $this->preferences->import($request->user(), $payload);

        if ($report['applied'] === []) {
            return back()->withErrors(['settings' => __('app.backup.settings_empty')]);
        }

        return back()->with('status', __('app.backup.settings_restored', [
            'count' => count($report['applied']),
            'skipped' => count($report['skipped']),
        ]));
    }
}
