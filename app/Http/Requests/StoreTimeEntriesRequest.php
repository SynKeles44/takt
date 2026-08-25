<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ManualEntryPlanner;
use App\Services\TimeTracker;
use App\Support\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreTimeEntriesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'work_starts_at' => ['nullable', 'date_format:H:i', 'required_with:work_ends_at'],
            'work_ends_at' => ['nullable', 'date_format:H:i', 'required_with:work_starts_at', 'different:work_starts_at'],
            'break_starts_at' => ['nullable', 'date_format:H:i', 'required_with:break_ends_at'],
            'break_ends_at' => ['nullable', 'date_format:H:i', 'required_with:break_starts_at', 'different:break_starts_at'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $work = $this->period('work');
                $break = $this->period('break');

                if ($work === null && $break === null) {
                    $validator->errors()->add('work_starts_at', __('app.validation.at_least_one'));

                    return;
                }

                if ($work !== null && $break !== null && ! app(ManualEntryPlanner::class)->canCombine($work, $break)) {
                    $validator->errors()->add('break_starts_at', __('app.validation.break_outside'));

                    return;
                }

                $tracker = app(TimeTracker::class);
                $limit = Carbon::now()->addDay();

                foreach ($this->plan() as $entry) {
                    if ($entry['ended_at']->isAfter($limit)) {
                        $validator->errors()->add('date', __('app.validation.too_far_ahead'));

                        return;
                    }

                    $conflict = $tracker->overlapping($entry['started_at'], $entry['ended_at']);

                    if ($conflict !== null) {
                        $validator->errors()->add('date', __('app.validation.overlap', [
                            'type' => $conflict->type->label(),
                            'from' => $conflict->started_at->format('d.m.Y H:i'),
                            'to' => $conflict->ended_at?->format('H:i') ?? __('app.running'),
                        ]));

                        return;
                    }
                }
            },
        ];
    }

    public function plan(): array
    {
        return app(ManualEntryPlanner::class)->plan(
            $this->period('work'),
            $this->period('break'),
            $this->string('note')->trim()->value() ?: null,
        );
    }

    private function period(string $prefix): ?Period
    {
        $start = $this->string($prefix.'_starts_at')->trim()->toString();
        $end = $this->string($prefix.'_ends_at')->trim()->toString();

        if ($start === '' || $end === '') {
            return null;
        }

        return Period::fromDateAndTimes($this->string('date')->toString(), $start, $end);
    }
}
