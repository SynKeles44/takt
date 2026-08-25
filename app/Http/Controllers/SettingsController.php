<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DesignStyle;
use App\Enums\Theme;
use App\Http\Requests\Settings\DesignStyleRequest;
use App\Http\Requests\Settings\PasswordRequest;
use App\Http\Requests\Settings\ProfileRequest;
use App\Http\Requests\Settings\ThemeRequest;
use App\Http\Requests\Settings\WorkTimeRequest;
use App\Services\Holidays;
use App\Services\Reviews;
use App\Support\Duration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(Request $request): View
    {
        $request->validate([
            'stil' => ['nullable', Rule::enum(DesignStyle::class)],
            'farbe' => ['nullable', Rule::enum(Theme::class)],
        ]);

        $user = $request->user();

        $previewedStyle = $request->filled('stil')
            ? DesignStyle::from($request->string('stil')->toString())
            : $user->design_style;

        $previewedTheme = $request->filled('farbe')
            ? Theme::from($request->string('farbe')->toString())
            : $user->theme;

        return view('settings', [
            'user' => $user,
            'regions' => Holidays::regions(),
            ...$this->carousel('style', DesignStyle::cases(), $previewedStyle),
            ...$this->carousel('theme', Theme::cases(), $previewedTheme),
        ]);
    }

    /**
     * One flip-through element per choice: what is shown, its neighbours, and where we are.
     *
     * @param  array<int, DesignStyle|Theme>  $cases
     * @return array<string, mixed>
     */
    private function carousel(string $key, array $cases, DesignStyle|Theme $previewed): array
    {
        $index = (int) array_search($previewed, $cases, true);
        $count = count($cases);

        return [
            $key.'s' => $cases,
            'previewed'.ucfirst($key) => $previewed,
            'previous'.ucfirst($key) => $cases[($index - 1 + $count) % $count],
            'next'.ucfirst($key) => $cases[($index + 1) % $count],
            $key.'Position' => $index + 1,
            $key.'Count' => $count,
        ];
    }

    public function updateProfile(ProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'email', 'locale']));

        return back()->with('status', __('app.flash.profile_saved'));
    }

    public function updateWorkTime(WorkTimeRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update($request->payload());

        return back()->with('status', __('app.flash.worktime_saved', [
            'daily' => Duration::human($user->dailyTargetSeconds()),
        ]));
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notify_worktime' => $request->boolean('notify_worktime'),
        ]);

        return back()->with('status', __('app.flash.notify_saved'));
    }

    public function updateDeveloper(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'github_token' => ['nullable', 'string', 'max:255'],
            'ticket_url_template' => ['nullable', 'string', 'max:255'],
            'pr_url_template' => ['nullable', 'string', 'max:255'],
            'instance_url_template' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // an empty token field keeps the stored one, "-" clears it
        $token = trim((string) ($data['github_token'] ?? ''));

        $user->update([
            'github_token' => match (true) {
                $token === '' => $user->github_token,
                $token === '-' => null,
                default => $token,
            },
            'ticket_url_template' => $data['ticket_url_template'] ?? null ?: null,
            'pr_url_template' => $data['pr_url_template'] ?? null ?: null,
            'instance_url_template' => $data['instance_url_template'] ?? null ?: null,
        ]);

        app(Reviews::class)->forget($user);

        return back()->with('status', __('app.flash.developer_saved'));
    }

    public function updatePassword(PasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('status', __('app.flash.password_saved'));
    }

    public function updateTheme(ThemeRequest $request): RedirectResponse
    {
        $request->user()->update(['theme' => $request->theme()]);

        return back()->with('status', __('app.flash.theme_saved', ['theme' => $request->theme()->label()]));
    }

    public function regenerateIcalToken(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['ical_token' => Str::random(48)])->save();

        return back()->with('status', __('app.flash.ical_regenerated'));
    }

    public function updateDesignStyle(DesignStyleRequest $request): RedirectResponse
    {
        $request->user()->update(['design_style' => $request->style()]);

        return back()->with('status', __('app.flash.style_saved', ['style' => $request->style()->label()]));
    }
}
