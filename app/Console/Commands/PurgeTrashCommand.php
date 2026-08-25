<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CommandRunner;
use App\Services\Trash;
use Illuminate\Console\Command;

class PurgeTrashCommand extends Command
{
    protected $signature = 'takt:purge-trash';

    protected $description = 'Permanently remove trashed entries and tasks older than the retention window';

    public function handle(Trash $trash, CommandRunner $runs): int
    {
        $purged = $trash->purgeExpired();

        $this->components->info(sprintf('%d records purged (older than %d days).', $purged, Trash::KEEP_DAYS));

        // the output of finished make runs goes with it
        $this->components->info(sprintf('%d command runs pruned.', $runs->prune()));

        return self::SUCCESS;
    }
}
