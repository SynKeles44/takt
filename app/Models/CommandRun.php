<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandRun extends Model
{
    use BelongsToUser;

    protected $fillable = ['project_id', 'target', 'interactive', 'status', 'exit_code', 'pid', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'interactive' => 'boolean',
            'exit_code' => 'integer',
            'pid' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('started_at')->orderByDesc('id');
    }

    public function command(): string
    {
        return 'make '.$this->target;
    }

    public function logPath(): string
    {
        return storage_path('app/runs/'.$this->getKey().'.log');
    }

    public function exitPath(): string
    {
        return storage_path('app/runs/'.$this->getKey().'.exit');
    }

    /** The FIFO an answer to a prompt is written into. */
    public function inputPath(): string
    {
        return storage_path('app/runs/'.$this->getKey().'.in');
    }
}
