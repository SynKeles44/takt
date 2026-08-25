<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Snippet extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'title', 'body', 'label', 'uses'];

    protected $casts = ['uses' => 'integer'];

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderByDesc('uses')->orderBy('title');
    }

    public function used(): void
    {
        $this->increment('uses');
    }
}
