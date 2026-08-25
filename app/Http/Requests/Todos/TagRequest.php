<?php

declare(strict_types=1);

namespace App\Http\Requests\Todos;

use App\Enums\TagColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('tags', 'name')
                    ->where('user_id', $this->user()->getKey())
                    ->ignore($this->route('tag')?->getKey()),
            ],
            'color' => ['required', Rule::enum(TagColor::class)],
            'warn_lead_minutes' => ['required', 'integer', 'min:0', 'max:20160'],
            'auto_complete_expired' => ['nullable', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'auto_complete_expired' => $this->boolean('auto_complete_expired'),
        ]);
    }

    public function payload(): array
    {
        return [
            'name' => $this->validated('name'),
            'color' => $this->validated('color'),
            'warn_lead_minutes' => (int) $this->validated('warn_lead_minutes'),
            'auto_complete_expired' => (bool) $this->validated('auto_complete_expired'),
        ];
    }
}
