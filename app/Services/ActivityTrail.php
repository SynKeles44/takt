<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivitySpan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which application was in front, and for how long — recorded by the app shell, kept on this
 * machine, and never part of any export that leaves it.
 *
 * The trail proposes bookings; it never books. That is the whole boundary: Takt may know that
 * PhpStorm was in front for 55 minutes, and only the user decides what that was.
 */
final class ActivityTrail
{
    /** Below this a span is noise: switching to a browser for ten seconds is not an activity. */
    public const int MINIMUM_SECONDS = 60;

    /** How long a proposal has to be to be worth offering. */
    public const int PROPOSAL_SECONDS = 900;

    public function enabled(User $user): bool
    {
        return (bool) $user->activity_trail;
    }

    /**
     * @param  list<array{app: string, title: ?string, starts_at: string, ends_at: string}>  $spans
     */
    public function record(User $user, array $spans): int
    {
        if (! $this->enabled($user)) {
            return 0;
        }

        $rows = [];

        foreach ($spans as $span) {
            $from = Carbon::parse($span['starts_at']);
            $to = Carbon::parse($span['ends_at']);

            if ($to->lessThanOrEqualTo($from) || $from->diffInSeconds($to) < self::MINIMUM_SECONDS) {
                continue;
            }

            $rows[] = [
                'user_id' => $user->getKey(),
                'app' => mb_substr(trim((string) $span['app']), 0, 120),
                'title' => isset($span['title']) ? mb_substr(trim((string) $span['title']), 0, 200) ?: null : null,
                'started_at' => $from,
                'ended_at' => $to,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        if ($rows === []) {
            return 0;
        }

        ActivitySpan::query()->insert($rows);

        return count($rows);
    }

    /**
     * The day's spans per application, longest first.
     *
     * @return Collection<int, array{app: string, seconds: int, titles: list<string>}>
     */
    public function forDay(User $user, Carbon $day): Collection
    {
        return ActivitySpan::query()
            ->onDay($day)
            ->orderBy('started_at')
            ->get()
            ->groupBy('app')
            ->map(fn (Collection $spans, string $app): array => [
                'app' => $app,
                'seconds' => (int) $spans->sum(fn (ActivitySpan $span): int => $span->seconds()),
                'titles' => $spans->pluck('title')->filter()->unique()->take(4)->values()->all(),
            ])
            ->sortByDesc('seconds')
            ->values();
    }

    /**
     * Stretches long enough to be worth booking, and not already covered by a booking.
     *
     * @return Collection<int, array{app: string, title: ?string, from: Carbon, to: Carbon, seconds: int}>
     */
    public function proposals(User $user, Carbon $day, TimeTracker $tracker): Collection
    {
        if (! $this->enabled($user)) {
            return collect();
        }

        $booked = $tracker->totalsForDay($day)['gross'];

        return ActivitySpan::query()
            ->onDay($day)
            ->orderBy('started_at')
            ->get()
            ->filter(fn (ActivitySpan $span): bool => $span->seconds() >= self::PROPOSAL_SECONDS)
            ->when($booked > 0, fn (Collection $spans) => $spans->filter(
                fn (ActivitySpan $span): bool => $tracker->overlapping($span->started_at, $span->ended_at) === null,
            ))
            ->map(fn (ActivitySpan $span): array => [
                'app' => $span->app,
                'title' => $span->title,
                'from' => $span->started_at,
                'to' => $span->ended_at,
                'seconds' => $span->seconds(),
            ])
            ->values();
    }

    /** Retention is the user's setting; everything older simply goes. */
    public function prune(User $user): int
    {
        $days = max(1, (int) $user->activity_retention_days);

        return ActivitySpan::query()
            ->where('started_at', '<', Carbon::today()->subDays($days))
            ->delete();
    }
}
