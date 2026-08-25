<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Console\Command;

class AssignOwnerCommand extends Command
{
    protected $signature = 'takt:assign-owner {email : The user who adopts the ownerless records} {--force}';

    protected $description = 'Assign time entries and todos without an owner to a user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $accounts = User::query()->orderBy('id')->pluck('email');

            $this->components->error($accounts->isEmpty()
                ? 'No account exists yet — register one first, then run this command again.'
                : sprintf(
                    'No user with email %s. Existing accounts: %s',
                    $this->argument('email'),
                    $accounts->implode(', '),
                ));

            if ($accounts->isEmpty()) {
                $this->components->bulletList([sprintf('Registration: %s/registrieren', rtrim(config('app.url'), '/'))]);
            }

            return self::FAILURE;
        }

        $entries = TimeEntry::query()->whereNull('user_id')->count();
        $todos = Todo::query()->whereNull('user_id')->count();

        if ($entries === 0 && $todos === 0) {
            $this->components->info('Nothing to adopt — every record already has an owner.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->components->confirm(
            sprintf('Assign %d time entries and %d todos to %s?', $entries, $todos, $user->email),
        )) {
            return self::FAILURE;
        }

        TimeEntry::query()->whereNull('user_id')->update(['user_id' => $user->getKey()]);
        Todo::query()->whereNull('user_id')->update(['user_id' => $user->getKey()]);

        $this->components->info(sprintf('%d time entries and %d todos now belong to %s.', $entries, $todos, $user->email));

        return self::SUCCESS;
    }
}
