<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\EntryType;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Services\TimeTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        Date::setLocale(config('app.locale'));
        Number::useLocale(config('app.locale'));

        View::composer('components.app-layout', function ($view): void {
            if (! Auth::check()) {
                return;
            }

            $view->with('dueWatch', Todo::query()
                ->open()
                ->dated()
                ->with('tags')
                ->get()
                ->map(fn (Todo $todo): array => [
                    'id' => $todo->getKey(),
                    'title' => $todo->title,
                    'due' => $todo->due_at->toIso8601String(),
                    'lead' => (int) $todo->tags->max(fn (Tag $tag): int => $tag->warn_lead_minutes),
                ])
                ->values()
                ->all());

            $user = Auth::user();

            if (! $user->notify_worktime) {
                return;
            }

            $tracker = app(TimeTracker::class);
            $today = Carbon::today();
            $entries = TimeEntry::query()->onDay($today)->get();
            $running = $tracker->running();

            $view->with('workWatch', [
                'day' => $today->toDateString(),
                'target' => $user->dailyTargetSeconds(),
                'work' => (int) $entries->where('type', EntryType::Work)
                    ->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()),
                'break' => (int) $entries->where('type', EntryType::Break)
                    ->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()),
                'since' => $running?->type === EntryType::Work ? $running->started_at->toIso8601String() : null,
            ]);
        });
    }
}
