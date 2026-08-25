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
        ]);

        $user = $request->user();
        $styles = DesignStyle::cases();

        $previewed = $request->filled('stil')
            ? DesignStyle::from($request->string('stil')->toString())
            : $user->design_style;

        $index = array_search($previewed, $styles, true);
        $count = count($styles);

        return view('settings', [
            'user' => $user,
            'themes' => Theme::cases(),
            'regions' => Holidays::regions(),
            'previewedStyle' => $previewed,
            'previousStyle' => $styles[($index - 1 + $count) % $count],
            'nextStyle' => $styles[($index + 1) % $count],
            'stylePosition' => $index + 1,
            'styleCount' => $count,
        ]);
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
