<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AbsenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function payload(): array
    {
        return [
            'type' => AbsenceType::from($this->string('type')->toString()),
            'starts_on' => $this->string('starts_on')->toString(),
            'ends_on' => $this->string('ends_on')->toString(),
            'note' => $this->string('note')->trim()->value() ?: null,
        ];
    }
}
