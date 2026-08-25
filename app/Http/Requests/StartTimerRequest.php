<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTimerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EntryType::class)],
        ];
    }

    public function type(): EntryType
    {
        return EntryType::from($this->string('type')->toString());
    }
}
