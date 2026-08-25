<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->getKey())],
            'locale' => ['nullable', 'in:de,en'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->lower()->trim()->toString(),
            'locale' => $this->filled('locale') ? $this->string('locale')->toString() : $this->user()->locale,
        ]);
    }
}
