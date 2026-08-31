<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\EntryType;
use App\Models\Tag;
use App\Models\Ticket;
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

            $tracker = app(TimeTracker::class);
            $today = Carbon::today();
            $entries = TimeEntry::query()->onDay($today)->get();
            $running = $tracker->running();

            $work = (int) $entries->where('type', EntryType::Work)
                ->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

            // the app shell reads this to fill its menu bar item — always present, because the
            // menu bar exists whether or not the notifications are switched on
            /*
             * The focused ticket rides along so the menu bar can answer "what am I on right now"
             * without opening the window. It is read from the local ticket layer, not from the
             * running entry: a booking may carry no ticket, and the focus outlives the timer.
             */
            $focused = Ticket::query()->whereNotNull('focused_at')->orderByDesc('focused_at')->first();

            $view->with('shellState', [
                'running' => $running?->type->value,
                'since' => $running?->started_at->toIso8601String(),
                'work' => $work,
                'target' => $user->dailyTargetSeconds(),
                'ticket' => $focused?->key,
                'ticketTitle' => $focused === null ? null : mb_strimwidth((string) ($focused->title ?? ''), 0, 60, '…'),
            ]);

            if (! $user->notify_worktime) {
                return;
            }

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
