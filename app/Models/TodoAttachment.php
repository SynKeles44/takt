<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TodoAttachment extends Model
{
    protected $fillable = ['todo_id', 'name', 'path', 'mime', 'size'];

    protected $casts = ['size' => 'integer'];

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            Storage::disk('local')->delete($attachment->path);
        });
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function humanSize(): string
    {
        return $this->size >= 1_048_576
            ? number_format($this->size / 1_048_576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }
}
