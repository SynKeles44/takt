<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DueState;
use App\Enums\Recurrence;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Todo extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'due_at',
        'due_has_time',
        'recurrence',
        'position',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'due_has_time' => 'boolean',
        'recurrence' => Recurrence::class,
        'completed_at' => 'datetime',
        'position' => 'integer',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'todo_tag');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TodoStep::class)->orderBy('position')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TodoAttachment::class)->latest('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeDated(Builder $query): Builder
    {
        return $query->whereNotNull('due_at');
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function toggle(): ?self
    {
        if ($this->isDone()) {
            $this->update(['completed_at' => null]);

            return null;
        }

        $this->update(['completed_at' => Carbon::now()]);

        return $this->spawnNextOccurrence();
    }

    public function spawnNextOccurrence(): ?self
    {
        if (! $this->recurrence->repeats() || $this->due_at === null) {
            return null;
        }

        $next = $this->recurrence->next($this->due_at);

        if ($next === null) {
            return null;
        }

        $follower = static::query()->create([
            'user_id' => $this->user_id,
            'title' => $this->title,
            'body' => $this->body,
            'due_at' => $next,
            'due_has_time' => $this->due_has_time,
            'recurrence' => $this->recurrence,
            'position' => (int) static::query()->max('position') + 1,
        ]);

        $follower->tags()->sync($this->tags->modelKeys());

        foreach ($this->steps as $step) {
            $follower->steps()->create([
                'title' => $step->title,
                'position' => $step->position,
            ]);
        }

        return $follower;
    }

    public function stepProgress(): ?array
    {
        $total = $this->steps->count();

        if ($total === 0) {
            return null;
        }

        $done = $this->steps->filter(fn (TodoStep $step): bool => $step->isDone())->count();

        return ['done' => $done, 'total' => $total, 'percent' => (int) round($done / $total * 100)];
    }

    public function warnLeadMinutes(): int
    {
        return (int) ($this->tags->max('warn_lead_minutes') ?? 0);
    }

    public function autoCompletes(): bool
    {
        return $this->tags->contains(fn (Tag $tag): bool => $tag->auto_complete_expired);
    }

    public function dueState(?Carbon $now = null): DueState
    {
        if ($this->isDone()) {
            return DueState::Done;
        }

        if ($this->due_at === null) {
            return DueState::Undated;
        }

        $now ??= Carbon::now();

        if ($this->due_at->isBefore($now)) {
            return DueState::Overdue;
        }

        $lead = $this->warnLeadMinutes();

        if ($lead > 0 && $this->due_at->isBefore($now->copy()->addMinutes($lead))) {
            return DueState::Warning;
        }

        if ($this->due_at->isSameDay($now)) {
            return DueState::Today;
        }

        if ($this->due_at->isBefore($now->copy()->endOfWeek())) {
            return DueState::Week;
        }

        return DueState::Later;
    }

    public function dueLabel(): string
    {
        if ($this->due_at === null) {
            return '';
        }

        $date = $this->due_at->isSameDay(Carbon::today())
            ? __('app.due.today_word')
            : ($this->due_at->isSameDay(Carbon::tomorrow())
                ? __('app.due.tomorrow_word')
                : $this->due_at->isoFormat('dd, D. MMM'));

        return $this->due_has_time
            ? $date.' · '.$this->due_at->format('H:i')
            : $date;
    }
}
