<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\Holidays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkTimeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weekly_hours' => ['required', 'numeric', 'min:1', 'max:80'],
            'working_days' => ['required', 'integer', 'min:1', 'max:7'],
            'holiday_region' => ['nullable', 'string', 'size:2', Rule::in(array_keys(Holidays::regions()))],
            'vacation_days' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'home_office_days' => ['nullable', 'integer', 'min:0', 'max:7'],
        ];
    }

    public function payload(): array
    {
        $user = $this->user();

        return [
            'weekly_hours' => (float) $this->validated('weekly_hours'),
            'working_days' => (int) $this->validated('working_days'),
            'holiday_region' => $this->validated('holiday_region') ?? $user->holiday_region,
            'vacation_days' => (float) ($this->validated('vacation_days') ?? $user->vacation_days),
            'home_office_days' => (int) ($this->validated('home_office_days') ?? $user->home_office_days),
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'weekly_hours' => str_replace(',', '.', $this->string('weekly_hours')->trim()->toString()),
            'vacation_days' => $this->filled('vacation_days')
                ? str_replace(',', '.', $this->string('vacation_days')->trim()->toString())
                : null,
        ]);
    }
}
