<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AbsenceType;
use App\Enums\EntryType;
use App\Enums\Recurrence;
use App\Enums\TagColor;
use App\Enums\Widget;
use App\Models\Absence;
use App\Models\DashboardWidget;
use App\Models\DayNote;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\TodoStep;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class Backup
{
    public function export(User $user): array
    {
        return [
            'exported_at' => Carbon::now()->toIso8601String(),
            'app' => config('app.name'),
            'version' => 1,
            'user' => $user->only(['name', 'email', 'weekly_hours', 'working_days']),
            'time_entries' => TimeEntry::query()->orderBy('started_at')->get()
                ->map(fn (TimeEntry $entry): array => [
                    'type' => $entry->type->value,
                    'started_at' => $entry->started_at->toDateTimeString(),
                    'ended_at' => $entry->ended_at?->toDateTimeString(),
                    'note' => $entry->note,
                ])->all(),
            'tags' => $user->tags()->orderBy('name')->get()
                ->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'color' => $tag->color->value,
                    'warn_lead_minutes' => $tag->warn_lead_minutes,
                    'auto_complete_expired' => $tag->auto_complete_expired,
                ])->all(),
            'todos' => Todo::query()->with(['tags', 'steps'])->orderBy('id')->get()
                ->map(fn (Todo $todo): array => [
                    'title' => $todo->title,
                    'body' => $todo->body,
                    'due_at' => $todo->due_at?->toDateTimeString(),
                    'due_has_time' => $todo->due_has_time,
                    'recurrence' => $todo->recurrence->value,
                    'completed_at' => $todo->completed_at?->toDateTimeString(),
                    'tags' => $todo->tags->pluck('name')->all(),
                    'steps' => $todo->steps->map(fn (TodoStep $step): array => [
                        'title' => $step->title,
                        'completed_at' => $step->completed_at?->toDateTimeString(),
                    ])->all(),
                ])->all(),
            'absences' => Absence::query()->orderBy('starts_on')->get()
                ->map(fn (Absence $absence): array => [
                    'type' => $absence->type->value,
                    'starts_on' => $absence->starts_on->toDateString(),
                    'ends_on' => $absence->ends_on->toDateString(),
                    'note' => $absence->note,
                ])->all(),
            'day_notes' => DayNote::query()->orderBy('day')->get()
                ->map(fn (DayNote $note): array => [
                    'day' => $note->day->toDateString(),
                    'body' => $note->body,
                ])->all(),
            // the arranged board is work too: rebuilding it by hand is exactly the loss a
            // backup is supposed to prevent
            'dashboard' => DashboardWidget::query()->inOrder()->get()
                ->map(fn (DashboardWidget $widget): array => [
                    'widget' => $widget->widget->value,
                    'span' => $widget->span,
                    'rows' => $widget->rows,
                    'position' => $widget->position,
                ])->all(),
        ];
    }

    public function json(User $user): string
    {
        return (string) json_encode(
            $this->export($user),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Additive import: existing records are never overwritten, only skipped.
     */
    public function import(User $user, array $payload): array
    {
        $report = [
            'time_entries' => ['imported' => 0, 'skipped' => 0],
            'tags' => ['imported' => 0, 'skipped' => 0],
            'todos' => ['imported' => 0, 'skipped' => 0],
            'absences' => ['imported' => 0, 'skipped' => 0],
            'day_notes' => ['imported' => 0, 'skipped' => 0],
            'dashboard' => ['imported' => 0, 'skipped' => 0],
        ];

        DB::transaction(function () use ($user, $payload, &$report): void {
            foreach ($this->rows($payload, 'tags') as $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '' || $user->tags()->where('name', $name)->exists()) {
                    $report['tags']['skipped']++;

                    continue;
                }

                $user->tags()->create([
                    'name' => $name,
                    'color' => TagColor::tryFrom((string) ($row['color'] ?? '')) ?? TagColor::Neutral,
                    'warn_lead_minutes' => (int) ($row['warn_lead_minutes'] ?? 0),
                    'auto_complete_expired' => (bool) ($row['auto_complete_expired'] ?? false),
                ]);

                $report['tags']['imported']++;
            }

            foreach ($this->rows($payload, 'time_entries') as $row) {
                $type = EntryType::tryFrom((string) ($row['type'] ?? ''));
                $startedAt = $this->date($row['started_at'] ?? null);

                if ($type === null || $startedAt === null) {
                    $report['time_entries']['skipped']++;

                    continue;
                }

                $exists = TimeEntry::query()
                    ->where('type', $type)
                    ->where('started_at', $startedAt->toDateTimeString())
                    ->exists();

                if ($exists) {
                    $report['time_entries']['skipped']++;

                    continue;
                }

                TimeEntry::query()->create([
                    'type' => $type,
                    'started_at' => $startedAt,
                    'ended_at' => $this->date($row['ended_at'] ?? null),
                    'note' => $row['note'] ?? null,
                ]);

                $report['time_entries']['imported']++;
            }

            foreach ($this->rows($payload, 'todos') as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                $dueAt = $this->date($row['due_at'] ?? null);

                if ($title === '') {
                    $report['todos']['skipped']++;

                    continue;
                }

                $exists = Todo::query()
                    ->where('title', $title)
                    ->where('due_at', $dueAt?->toDateTimeString())
                    ->exists();

                if ($exists) {
                    $report['todos']['skipped']++;

                    continue;
                }

                $todo = Todo::query()->create([
                    'title' => $title,
                    'body' => $row['body'] ?? null,
                    'due_at' => $dueAt,
                    'due_has_time' => (bool) ($row['due_has_time'] ?? false),
                    'recurrence' => Recurrence::tryFrom((string) ($row['recurrence'] ?? '')) ?? Recurrence::None,
                    'completed_at' => $this->date($row['completed_at'] ?? null),
                ]);

                $tagIds = collect($row['tags'] ?? [])
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter()
                    ->map(fn (string $name): int => $user->tags()->firstOrCreate(
                        ['name' => $name],
                        ['color' => TagColor::Neutral],
                    )->id)
                    ->all();

                $todo->tags()->sync($tagIds);

                foreach (array_values($row['steps'] ?? []) as $position => $step) {
                    $stepTitle = trim((string) ($step['title'] ?? ''));

                    if ($stepTitle === '') {
                        continue;
                    }

                    $todo->steps()->create([
                        'title' => $stepTitle,
                        'position' => $position,
                        'completed_at' => $this->date($step['completed_at'] ?? null),
                    ]);
                }

                $report['todos']['imported']++;
            }

            foreach ($this->rows($payload, 'absences') as $row) {
                $type = AbsenceType::tryFrom((string) ($row['type'] ?? ''));
                $start = $this->date($row['starts_on'] ?? null);
                $end = $this->date($row['ends_on'] ?? null) ?? $start;

                if ($type === null || $start === null) {
                    $report['absences']['skipped']++;

                    continue;
                }

                $exists = Absence::query()
                    ->where('type', $type)
                    ->whereDate('starts_on', $start->toDateString())
                    ->exists();

                if ($exists) {
                    $report['absences']['skipped']++;

                    continue;
                }

                Absence::query()->create([
                    'type' => $type,
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'note' => $row['note'] ?? null,
                ]);

                $report['absences']['imported']++;
            }

            foreach ($this->rows($payload, 'day_notes') as $row) {
                $day = $this->date($row['day'] ?? null);
                $body = trim((string) ($row['body'] ?? ''));

                if ($day === null || $body === '' || DayNote::query()->whereDate('day', $day->toDateString())->exists()) {
                    $report['day_notes']['skipped']++;

                    continue;
                }

                DayNote::query()->create(['day' => $day->toDateString(), 'body' => $body]);

                $report['day_notes']['imported']++;
            }

            /*
             * The board is the one thing the import may replace rather than skip: a half-merged
             * layout is no layout. It is only touched when the backup carries one, and only when
             * the board is still the untouched default.
             */
            $board = $this->rows($payload, 'dashboard');

            if ($board !== [] && ! $user->dashboard_arranged) {
                DashboardWidget::query()->delete();

                foreach ($board as $position => $row) {
                    $widget = Widget::tryFrom((string) ($row['widget'] ?? ''));

                    if ($widget === null) {
                        $report['dashboard']['skipped']++;

                        continue;
                    }

                    DashboardWidget::query()->create([
                        'widget' => $widget,
                        'span' => (int) ($row['span'] ?? $widget->span()),
                        'rows' => (int) ($row['rows'] ?? $widget->rows()),
                        'position' => (int) ($row['position'] ?? $position),
                    ]);

                    $report['dashboard']['imported']++;
                }

                $user->forceFill(['dashboard_arranged' => true])->save();
            } elseif ($board !== []) {
                $report['dashboard']['skipped'] = count($board);
            }
        });

        return $report;
    }

    private function rows(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? [];

        return is_array($rows) ? array_filter($rows, 'is_array') : [];
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
