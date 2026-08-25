<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TodoStep extends Model
{
    protected $fillable = ['todo_id', 'title', 'position', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime', 'position' => 'integer'];

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function toggle(): bool
    {
        return $this->update(['completed_at' => $this->isDone() ? null : Carbon::now()]);
    }
}
