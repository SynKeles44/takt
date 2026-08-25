<?php

declare(strict_types=1);

namespace App\Enums;

enum WidgetGroup: string
{
    case Time = 'time';
    case Tasks = 'tasks';
    case Development = 'development';

    public function label(): string
    {
        return __('app.widget.group.'.$this->value);
    }
}
