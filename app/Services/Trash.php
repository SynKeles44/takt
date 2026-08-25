<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\Todo;
use Illuminate\Support\Carbon;

final class Trash
{
    public const int KEEP_DAYS = 30;

    public function purgeExpired(): int
    {
        $limit = Carbon::now()->subDays(self::KEEP_DAYS);
        $purged = 0;

        foreach ([TimeEntry::class, Todo::class] as $model) {
            $purged += $model::query()->onlyTrashed()->where('deleted_at', '<', $limit)->get()
                ->each(fn ($record) => $record->forceDelete())
                ->count();
        }

        return $purged;
    }

    public function entries()
    {
        return TimeEntry::query()->onlyTrashed()->latest('deleted_at')->get();
    }

    public function todos()
    {
        return Todo::query()->onlyTrashed()->with('tags')->latest('deleted_at')->get();
    }
}
