<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StartTimerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EntryType::class)],
            // a start that already happened: same day only, and never in the future
            'ab' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function type(): EntryType
    {
        return EntryType::from($this->string('type')->toString());
    }

    /**
     * The moment the timer should count from. Null means now.
     *
     * Bounded to today and to the past on purpose: a start time in the future would run the clock
     * backwards, and one on an earlier day belongs in a booking, not in a running timer.
     */
    public function startedAt(): ?Carbon
    {
        $value = $this->string('ab')->toString();

        if ($value === '') {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));

        $at = Carbon::today()->setTime($hour, $minute);

        return $at->isFuture() ? null : $at;
    }
}
