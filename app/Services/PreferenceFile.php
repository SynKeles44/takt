<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DesignStyle;
use App\Enums\Theme;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

final class PreferenceFile
{
    public function export(User $user): array
    {
        return [
            'exported_at' => Carbon::now()->toIso8601String(),
            'app' => config('app.name'),
            'kind' => 'settings',
            'version' => 1,
            'settings' => [
                'weekly_hours' => (float) $user->weekly_hours,
                'working_days' => $user->working_days,
                'holiday_region' => $user->holiday_region,
                'vacation_days' => (float) $user->vacation_days,
                'theme' => $user->theme->value,
                'design_style' => $user->design_style->value,
                'locale' => $user->locale,
            ],
        ];
    }

    public function json(User $user): string
    {
        return (string) json_encode(
            $this->export($user),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Applies every value that validates and reports the keys it had to skip.
     *
     * @return array{applied: list<string>, skipped: list<string>}
     */
    public function import(User $user, array $payload): array
    {
        $values = $payload['settings'] ?? $payload;

        if (! is_array($values)) {
            return ['applied' => [], 'skipped' => []];
        }

        $applied = [];
        $skipped = [];
        $changes = [];

        foreach ($this->rules() as $key => $rule) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if (is_string($value) && in_array($key, ['weekly_hours', 'vacation_days'], true)) {
                $value = str_replace(',', '.', trim($value));
            }

            if (Validator::make([$key => $value], [$key => $rule])->fails()) {
                $skipped[] = $key;

                continue;
            }

            $changes[$key] = match ($key) {
                'weekly_hours', 'vacation_days' => (float) $value,
                'working_days' => (int) $value,
                default => (string) $value,
            };

            $applied[] = $key;
        }

        if ($changes !== []) {
            $user->update($changes);
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'weekly_hours' => ['required', 'numeric', 'min:1', 'max:80'],
            'working_days' => ['required', 'integer', 'min:1', 'max:7'],
            'holiday_region' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(Holidays::regions()))],
            'vacation_days' => ['required', 'numeric', 'min:0', 'max:99'],
            'theme' => ['required', 'in:'.implode(',', array_column(Theme::cases(), 'value'))],
            'design_style' => ['required', 'in:'.implode(',', array_column(DesignStyle::cases(), 'value'))],
            'locale' => ['required', 'in:de,en'],
        ];
    }
}
