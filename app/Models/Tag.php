<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TagColor;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'warn_lead_minutes',
        'auto_complete_expired',
    ];

    protected $casts = [
        'color' => TagColor::class,
        'warn_lead_minutes' => 'integer',
        'auto_complete_expired' => 'boolean',
    ];

    public function todos(): BelongsToMany
    {
        return $this->belongsToMany(Todo::class, 'todo_tag');
    }
}
