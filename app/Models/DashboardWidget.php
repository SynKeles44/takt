<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Widget;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    use BelongsToUser;

    protected $fillable = ['widget', 'span', 'rows', 'position'];

    protected function casts(): array
    {
        return [
            'widget' => Widget::class,
            'span' => 'integer',
            'rows' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeInOrder(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function columns(): int
    {
        return $this->span ?? $this->widget->span();
    }

    public function rowSpan(): int
    {
        return $this->rows ?? $this->widget->rows();
    }
}
