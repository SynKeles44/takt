<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DesignStyle;
use App\Enums\Theme;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'weekly_hours', 'working_days', 'theme', 'design_style', 'locale', 'notify_worktime', 'github_token', 'ticket_url_template', 'pr_url_template', 'instance_url_template', 'holiday_region', 'vacation_days'])]
#[Hidden(['password', 'remember_token', 'github_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'weekly_hours' => 40,
        'working_days' => 5,
        'theme' => 'midnight',
        'design_style' => 'soft',
        'locale' => 'de',
        'notify_worktime' => true,
        'holiday_region' => 'NW',
        'vacation_days' => 30,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'weekly_hours' => 'float',
            'working_days' => 'integer',
            'notify_worktime' => 'boolean',
            'dashboard_arranged' => 'boolean',
            'theme' => Theme::class,
            'design_style' => DesignStyle::class,
            'vacation_days' => 'float',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function snippets(): HasMany
    {
        return $this->hasMany(Snippet::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function stepTemplates(): HasMany
    {
        return $this->hasMany(StepTemplate::class);
    }

    public function workingDays(): int
    {
        return max(1, $this->working_days);
    }

    public function weeklyTargetSeconds(): int
    {
        return (int) round($this->weekly_hours * 3600);
    }

    public function dailyTargetSeconds(): int
    {
        return (int) round($this->weeklyTargetSeconds() / $this->workingDays());
    }

    public function icalToken(): string
    {
        if ($this->ical_token === null) {
            $this->forceFill(['ical_token' => Str::random(48)])->save();
        }

        return $this->ical_token;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function firstName(): string
    {
        return Str::before($this->name, ' ');
    }
}
