<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        foreach (['/', '/verlauf', '/todo', '/einstellungen'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_guests_cannot_book_time(): void
    {
        $this->post(route('timer.start'), ['type' => 'work'])->assertRedirect(route('login'));
        $this->post(route('entries.store'), ['date' => '2026-08-20', 'work_starts_at' => '09:00', 'work_ends_at' => '17:00'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_the_login_screen_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('app.auth.login_title'))
            ->assertSee(__('app.auth.register_action'));
    }

    public function test_a_user_can_log_in(): void
    {
        $user = User::factory()->create(['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42']);

        $this->post(route('login'), ['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_email_is_matched_case_insensitively(): void
    {
        User::factory()->create(['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42']);

        $this->post(route('login'), ['email' => 'Seymen@Example.test', 'password' => 'gutes-passwort-42'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_a_wrong_password_is_rejected_without_revealing_the_account(): void
    {
        User::factory()->create(['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42']);

        $this->post(route('login'), ['email' => 'seymen@example.test', 'password' => 'falsch'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->post(route('login'), ['email' => 'niemand@example.test', 'password' => 'falsch'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('seymen@example.test|127.0.0.1');
        User::factory()->create(['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), ['email' => 'seymen@example.test', 'password' => 'falsch']);
        }

        $this->post(route('login'), ['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString(
            'Zu viele Versuche',
            session('errors')->getBag('default')->first('email'),
        );
    }

    public function test_the_session_id_changes_on_login(): void
    {
        User::factory()->create(['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42']);

        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login'), ['email' => 'seymen@example.test', 'password' => 'gutes-passwort-42']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_a_user_can_register_and_lands_on_the_dashboard(): void
    {
        $this->post(route('register'), [
            'name' => 'Seymen Keles',
            'email' => 'Neu@Example.test',
            'password' => 'gutes-passwort-42',
            'password_confirmation' => 'gutes-passwort-42',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->sole();

        $this->assertSame('neu@example.test', $user->email);
        $this->assertSame(40.0, $user->weekly_hours);
        $this->assertSame(28_800, $user->dailyTargetSeconds());
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'seymen@example.test']);

        $this->post(route('register'), [
            'name' => 'Zweiter',
            'email' => 'seymen@example.test',
            'password' => 'gutes-passwort-42',
            'password_confirmation' => 'gutes-passwort-42',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->count());
    }

    public function test_a_user_can_log_out(): void
    {
        $this->login();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        Auth::guard('web')->check();
    }

    public function test_logged_in_users_are_kept_away_from_the_login_screen(): void
    {
        $this->login();

        $this->get(route('login'))->assertRedirect();
    }
}
