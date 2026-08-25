<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'name', 'path', 'repository', 'start_command', 'port', 'position'];

    protected $casts = ['port' => 'integer', 'position' => 'integer'];

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    public function absolutePath(): string
    {
        return rtrim(str_replace('~', (string) getenv('HOME'), $this->path), '/');
    }

    public function exists(): bool
    {
        return is_dir($this->absolutePath());
    }

    public function isGitRepository(): bool
    {
        return is_dir($this->absolutePath().'/.git');
    }

    /** owner/name, as GitHub needs it */
    public function slug(): ?string
    {
        if ($this->repository === null) {
            return null;
        }

        return trim(Str::of($this->repository)
            ->replaceMatches('#^(https?://github\.com/|git@github\.com:)#', '')
            ->replaceMatches('#\.git$#', '')
            ->toString(), '/');
    }

    public function pidFile(): string
    {
        return storage_path('app/projects/'.$this->getKey().'.pid');
    }
}
