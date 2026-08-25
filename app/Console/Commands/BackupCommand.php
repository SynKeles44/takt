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

    protected $signature = 'takt:backup {--user= : Limit the backup to one email address}';

    protected $description = 'Write a JSON backup per user and keep the newest files';

    public function handle(Backup $backup): int
    {
        $users = User::query()
            ->when($this->option('user'), fn ($query, string $email) => $query->where('email', $email))
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->components->warn('No matching account found.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');

        foreach ($users as $user) {
            Auth::setUser($user);

            $folder = 'backups/'.$user->id;
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
}
