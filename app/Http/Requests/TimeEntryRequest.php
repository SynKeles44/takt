<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EntryType;
use App\Services\EntryAdjuster;
use App\Support\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimeEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EntryType::class)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
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

                $period = $this->period();

                if ($period->end->isAfter(Carbon::now()->addDay())) {
                    $validator->errors()->add('ends_at', __('app.validation.too_far_ahead'));

                    return;
                }

                $blocker = app(EntryAdjuster::class)->blocker($period, $this->ignoredId());

                if ($blocker !== null) {
                    $validator->errors()->add('starts_at', __('app.validation.overlap_blocked', [
                        'type' => $blocker->type->label(),
                        'from' => $blocker->started_at->format('d.m.Y H:i'),
                        'to' => $blocker->ended_at?->format('H:i') ?? __('app.running'),
                    ]));
                }
            },
        ];
    }

    public function period(): Period
    {
        return Period::fromDateAndTimes(
            $this->string('date')->toString(),
            $this->string('starts_at')->toString(),
            $this->string('ends_at')->toString(),
        );
    }

    public function payload(): array
    {
        $period = $this->period();

        return [
            'type' => EntryType::from($this->string('type')->toString()),
            'started_at' => $period->start,
            'ended_at' => $period->end,
            'note' => $this->string('note')->trim()->value() ?: null,
        ];
    }

    private function ignoredId(): ?int
    {
        return $this->route('entry')?->getKey();
    }
}
