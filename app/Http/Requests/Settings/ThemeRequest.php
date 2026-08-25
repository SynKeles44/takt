<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThemeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'theme' => ['required', Rule::enum(Theme::class)],
        ];
    }

    public function theme(): Theme
    {
        return Theme::from($this->string('theme')->toString());
    }
}
