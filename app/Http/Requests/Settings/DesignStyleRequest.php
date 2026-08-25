<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\DesignStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignStyleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'design_style' => ['required', Rule::enum(DesignStyle::class)],
        ];
    }

    public function style(): DesignStyle
    {
        return DesignStyle::from($this->string('design_style')->toString());
    }
}
