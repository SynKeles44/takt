<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tag;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Collection;

final class CalendarFeed
{
    public function build(User $user, Collection $todos): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Takt//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape(config('app.name').' · '.$user->firstName()),
            'X-WR-TIMEZONE:'.config('app.timezone'),
        ];

        foreach ($todos as $todo) {
            array_push($lines, ...$this->event($todo));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** @return list<string> */
    private function event(Todo $todo): array
    {
        $stamp = $todo->updated_at->clone()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VEVENT',
            'UID:todo-'.$todo->getKey().'@takt',
            'DTSTAMP:'.$stamp,
            'SUMMARY:'.$this->escape($todo->title),
        ];

        if ($todo->due_has_time) {
            $lines[] = 'DTSTART:'.$todo->due_at->clone()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$todo->due_at->clone()->addMinutes(30)->utc()->format('Ymd\THis\Z');
        } else {
            $lines[] = 'DTSTART;VALUE=DATE:'.$todo->due_at->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$todo->due_at->clone()->addDay()->format('Ymd');
        }

        if ($todo->body !== null) {
            $lines[] = 'DESCRIPTION:'.$this->escape($todo->body);
        }

        if ($todo->tags->isNotEmpty()) {
            $lines[] = 'CATEGORIES:'.$this->escape($todo->tags->pluck('name')->implode(','));
        }

        $lines[] = 'STATUS:'.($todo->isDone() ? 'COMPLETED' : 'CONFIRMED');

        $lead = (int) $todo->tags->max(fn (Tag $tag): int => $tag->warn_lead_minutes);

        if ($lead > 0 && ! $todo->isDone()) {
            array_push(
                $lines,
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'DESCRIPTION:'.$this->escape($todo->title),
                'TRIGGER:-PT'.$lead.'M',
                'END:VALARM',
            );
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ';', ','],
            ['\\\\', '\\n', '\\n', '\;', '\\,'],
            $value,
        );
    }

    private function fold(string $line): string
    {
        if (mb_strlen($line) <= 73) {
            return $line;
        }

        $parts = mb_str_split($line, 72);

        return array_shift($parts).implode('', array_map(fn (string $part): string => "\r\n ".$part, $parts));
    }
}
