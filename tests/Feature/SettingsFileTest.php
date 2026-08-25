<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DesignStyle;
use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SettingsFileTest extends TestCase
{
    use RefreshDatabase;

    private function upload(array $payload, string $name = 'settings.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) json_encode($payload));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 18:00:00');
    }

    public function test_the_settings_are_offered_as_a_json_download(): void
    {
        $this->login([
            'weekly_hours' => 38.5,
            'working_days' => 5,
            'holiday_region' => 'BY',
            'vacation_days' => 28,
            'theme' => Theme::Onyx,
            'design_style' => DesignStyle::Bento,
            'locale' => 'en',
        ]);

        $payload = $this->get(route('settings.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="takt-einstellungen-2026-08-24.json"')
            ->json();

        $this->assertSame('settings', $payload['kind']);
        $this->assertSame([
            'weekly_hours' => 38.5,
            'working_days' => 5,
            'holiday_region' => 'BY',
            'vacation_days' => 28,
            'theme' => 'onyx',
            'design_style' => 'bento',
            'locale' => 'en',
        ], $payload['settings']);
    }

    public function test_an_exported_file_can_be_imported_again(): void
    {
        $user = $this->login(['weekly_hours' => 40, 'theme' => Theme::Midnight, 'locale' => 'de']);

        $file = $this->upload([
            'kind' => 'settings',
            'settings' => [
                'weekly_hours' => '37,5',
                'working_days' => 4,
                'holiday_region' => 'HH',
                'vacation_days' => 26,
                'theme' => 'sage',
                'design_style' => 'terminal',
                'locale' => 'en',
            ],
        ]);

        $this->post(route('settings.import'), ['settings' => $file])
            ->assertRedirect()
            ->assertSessionHas('status', '7 Einstellungen übernommen, 0 übersprungen.');

        $user->refresh();

        $this->assertSame(37.5, (float) $user->weekly_hours);
        $this->assertSame(4, $user->working_days);
        $this->assertSame('HH', $user->holiday_region);
        $this->assertSame(Theme::Sage, $user->theme);
        $this->assertSame(DesignStyle::Terminal, $user->design_style);
        $this->assertSame('en', $user->locale);
        $this->assertSame(4 * 9.375 * 3600, (float) $user->weeklyTargetSeconds());
    }

    public function test_invalid_values_are_skipped_and_the_rest_is_applied(): void
    {
        $user = $this->login(['weekly_hours' => 40, 'holiday_region' => 'NW']);

        $this->post(route('settings.import'), [
            'settings' => $this->upload([
                'settings' => [
                    'weekly_hours' => 500,
                    'working_days' => 3,
                    'holiday_region' => 'XX',
                    'theme' => 'neon',
                    'locale' => 'fr',
                    'unknown_key' => 'ignored',
                ],
            ]),
        ])->assertRedirect()->assertSessionHas('status', '1 Einstellungen übernommen, 4 übersprungen.');

        $user->refresh();

        $this->assertSame(40.0, (float) $user->weekly_hours);
        $this->assertSame(3, $user->working_days);
        $this->assertSame('NW', $user->holiday_region);
    }

    public function test_a_file_without_a_single_valid_setting_is_rejected(): void
    {
        $this->login();

        $this->post(route('settings.import'), ['settings' => $this->upload(['settings' => ['theme' => 'neon']])])
            ->assertSessionHasErrors('settings');

        $this->post(route('settings.import'), [
            'settings' => UploadedFile::fake()->createWithContent('settings.json', 'not json'),
        ])->assertSessionHasErrors('settings');
    }

    public function test_the_import_only_ever_touches_the_own_account(): void
    {
        $other = User::factory()->create(['weekly_hours' => 40, 'theme' => Theme::Midnight]);

        $this->login();
        $this->post(route('settings.import'), ['settings' => $this->upload(['settings' => ['theme' => 'sage']])])
            ->assertRedirect();

        $this->assertSame(Theme::Midnight, $other->refresh()->theme);
    }

    public function test_the_settings_page_offers_both_directions(): void
    {
        $this->login();

        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('Einstellungen als JSON')
            ->assertSee(route('settings.export'), false)
            ->assertSee(route('settings.import'), false);
    }
}
