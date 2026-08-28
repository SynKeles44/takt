<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The events the app shell read out of the Mac's calendars, kept for the day they belong to.
 * They are proposals, never bookings: Takt shows them and books only what is clicked.
 *
 * Nothing but strings goes into the store, and nothing leaves the machine.
 */
final class CalendarEvents
{
    /** A day: the shell reports again on every wake and every launch. */
    public const int CACHE_SECONDS = 86400;

    /**
     * @param  list<array{title: string, starts_at: string, ends_at: string, calendar: ?string}>  $events
     * @return list<array<string, mixed>>
     */
    public function store(User $user, Carbon $day, array $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            $from = Carbon::parse($event['starts_at']);
            $to = Carbon::parse($event['ends_at']);

            if (! $from->isSameDay($day) || $to->lessThanOrEqualTo($from)) {
                continue;
            }

            $rows[] = [
                'title' => mb_substr(trim((string) $event['title']), 0, 200),
                'starts_at' => $from->toIso8601String(),
                'ends_at' => $to->toIso8601String(),
                'calendar' => isset($event['calendar']) ? mb_substr((string) $event['calendar'], 0, 80) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['starts_at'], $b['starts_at']));

        Cache::put($this->key($user, $day), $rows, self::CACHE_SECONDS);

        return $rows;
    }

    /** @return list<array{title: string, from: Carbon, to: Carbon, calendar: ?string, seconds: int}> */
    public function forDay(User $user, Carbon $day): array
    {
        $stored = Cache::get($this->key($user, $day));

        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            $from = Carbon::parse($row['starts_at']);
            $to = Carbon::parse($row['ends_at']);

            return [
                'title' => (string) $row['title'],
                'from' => $from,
                'to' => $to,
                'calendar' => $row['calendar'] ?? null,
                'seconds' => (int) $from->diffInSeconds($to),
            ];
        }, $stored));
    }

    private function key(User $user, Carbon $day): string
    {
        return 'calendar.'.$user->getKey().'.'.$day->toDateString();
    }
}
