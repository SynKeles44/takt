<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\Theme;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 18:00:00');

        $this->user = $this->login(['name' => 'Seymen Keles', 'email' => 'seymen@example.test']);
    }

    public function test_the_settings_page_shows_every_card(): void
    {
        $this->get(route('settings'))
            ->assertOk()
            ->assertSee(__('app.settings.worktime_title'))
            ->assertSee(__('app.settings.profile_title'))
            ->assertSee(__('app.settings.password_title'))
            ->assertSee(__('app.settings.theme_title'))
            ->assertSee(Theme::Sage->label());
    }

    public function test_working_time_is_saved_and_drives_the_daily_target(): void
    {
        $this->put(route('settings.worktime'), ['weekly_hours' => '35,5', 'working_days' => 5])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->user->refresh();

        $this->assertSame(35.5, $this->user->weekly_hours);
        $this->assertSame(25_560, $this->user->dailyTargetSeconds());
        $this->assertSame(127_800, $this->user->weeklyTargetSeconds());
    }

    public function test_the_dashboard_follows_the_configured_target(): void
    {
        $this->user->update(['weekly_hours' => 20, 'working_days' => 5]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-24 09:00:00',
            'ended_at' => '2026-08-24 14:00:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('+1h 00m')
            ->assertSee(__('app.stats.target', ['hours' => 20]));
    }

    public function test_working_time_is_validated(): void
    {
        foreach ([
            ['weekly_hours' => '0', 'working_days' => 5],
            ['weekly_hours' => '81', 'working_days' => 5],
            ['weekly_hours' => 'viel', 'working_days' => 5],
            ['weekly_hours' => '40', 'working_days' => 0],
            ['weekly_hours' => '40', 'working_days' => 8],
        ] as $payload) {
            $this->put(route('settings.worktime'), $payload)->assertSessionHasErrors();
        }

        $this->assertSame(40.0, $this->user->refresh()->weekly_hours);
    }

    public function test_the_profile_is_saved(): void
    {
        $this->put(route('settings.profile'), ['name' => 'Neuer Name', 'email' => 'Neu@Example.test'])
            ->assertSessionHasNoErrors();

        $this->user->refresh();

        $this->assertSame('Neuer Name', $this->user->name);
        $this->assertSame('neu@example.test', $this->user->email);
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        User::factory()->create(['email' => 'belegt@example.test']);

        $this->put(route('settings.profile'), ['name' => 'Seymen Keles', 'email' => 'belegt@example.test'])
            ->assertSessionHasErrors('email');

        $this->assertSame('seymen@example.test', $this->user->refresh()->email);
    }

    public function test_the_own_email_can_be_kept(): void
    {
        $this->put(route('settings.profile'), ['name' => 'Anders', 'email' => 'seymen@example.test'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Anders', $this->user->refresh()->name);
    }

    public function test_the_password_changes_only_with_the_current_one(): void
    {
        $user = $this->login(['password' => 'altes-passwort-42']);

        $this->put(route('settings.password'), [
            'current_password' => 'falsch',
            'password' => 'neues-passwort-42',
            'password_confirmation' => 'neues-passwort-42',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('altes-passwort-42', $user->refresh()->password));

        $this->put(route('settings.password'), [
            'current_password' => 'altes-passwort-42',
            'password' => 'neues-passwort-42',
            'password_confirmation' => 'neues-passwort-42',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('neues-passwort-42', $user->refresh()->password));
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->login(['password' => 'altes-passwort-42']);

        $this->put(route('settings.password'), [
            'current_password' => 'altes-passwort-42',
            'password' => 'neues-passwort-42',
            'password_confirmation' => 'anderes-passwort-42',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('altes-passwort-42', $user->refresh()->password));
    }

    public function test_the_theme_is_stored_and_rendered(): void
    {
        $this->put(route('settings.theme'), ['theme' => Theme::Sage->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(Theme::Sage, $this->user->refresh()->theme);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="sage"', escape: false);
    }

    public function test_an_unknown_theme_is_rejected(): void
    {
        $this->put(route('settings.theme'), ['theme' => 'neon'])->assertSessionHasErrors('theme');

        $this->assertSame(Theme::Midnight, $this->user->refresh()->theme);
    }

    public function test_settings_do_not_leak_between_users(): void
    {
        $other = User::factory()->create(['weekly_hours' => 40, 'theme' => Theme::Midnight]);

        $this->put(route('settings.worktime'), ['weekly_hours' => '10', 'working_days' => 2]);
        $this->put(route('settings.theme'), ['theme' => Theme::Onyx->value]);

        $other->refresh();

        $this->assertSame(40.0, $other->weekly_hours);
        $this->assertSame(Theme::Midnight, $other->theme);
        $this->assertSame(10.0, $this->user->refresh()->weekly_hours);
    }

    public function test_the_default_theme_applies_to_a_fresh_account(): void
    {
        $this->post(route('logout'));

        $this->post(route('register'), [
            'name' => 'Frisch',
            'email' => 'frisch@example.test',
            'password' => 'gutes-passwort-42',
            'password_confirmation' => 'gutes-passwort-42',
        ]);

        $this->get(route('dashboard'))->assertSee('data-theme="midnight"', escape: false);
    }
}
