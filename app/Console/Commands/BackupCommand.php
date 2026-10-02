<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupCommand extends Command
{
    public const KEEP_FILES = 30;

    /** Younger than this and `--if-due` leaves the account alone: one backup a day, not one per launch. */
    public const DUE_AFTER_HOURS = 20;

    protected $signature = 'takt:backup
                            {--user= : Limit the backup to one email address}
                            {--if-due : Only accounts whose newest backup is older than a day — what the app runs on its own}';

    protected $description = 'Write a JSON backup per user and keep the newest files';

    public function handle(Backup $backup): int
    {
        $users = User::query()
            ->when($this->option('user'), fn ($query, string $email) => $query->where('email', $email))
            ->orderBy('id')
            ->get();

        $due = (bool) $this->option('if-due');

        if ($users->isEmpty()) {
            // a fresh app before the first account is not a failure, just nothing to back up yet
            if ($due) {
                return self::SUCCESS;
            }

            $this->components->warn('No matching account found.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');

        foreach ($users as $user) {
            $folder = 'backups/'.$user->id;

            if ($due && ! $this->isDue($disk->files($folder))) {
                continue;
            }

            Auth::setUser($user);
            $path = sprintf('%s/%s-%s.json', $folder, Str::slug($user->email), Carbon::now()->format('Y-m-d-His'));

            $disk->put($path, $backup->json($user));

            $stale = collect($disk->files($folder))
                ->sortDesc()
                ->slice(self::KEEP_FILES);

            $disk->delete($stale->all());

            $this->components->twoColumnDetail($user->email, $path.($stale->isEmpty() ? '' : sprintf(' (-%d)', $stale->count())));
        }

        Auth::forgetUser();

        return self::SUCCESS;
    }

    /**
     * Whether the newest backup is old enough for another one. Read from the timestamp in the file
     * name rather than the file time: a restored or copied folder keeps the names, not the times.
     *
     * @param  list<string>  $files
     */
    private function isDue(array $files): bool
    {
        $newest = collect($files)
            ->map(fn (string $file): ?Carbon => preg_match('/(\d{4}-\d{2}-\d{2}-\d{6})\.json$/', $file, $match) === 1
                ? Carbon::createFromFormat('Y-m-d-His', $match[1])
                : null)
            ->filter()
            ->max();

        return $newest === null || $newest->lt(Carbon::now()->subHours(self::DUE_AFTER_HOURS));
    }
}
