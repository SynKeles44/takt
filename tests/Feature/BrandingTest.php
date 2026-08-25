<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The mentions of the old name in this file are on purpose: they are the regression
     * guard that keeps it from creeping back in.
     */
    public function test_the_app_carries_the_new_name_everywhere(): void
    {
        $this->login();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Takt')
            ->assertSee('taktBadge', false)
            ->assertDontSee('Werkbank');

        $this->assertSame('Takt', config('app.name'));
        $this->assertStringNotContainsString('Werkbank', (string) file_get_contents(public_path('logo.svg')));
        $this->assertStringNotContainsString('Werkbank', (string) file_get_contents(public_path('favicon.svg')));

        foreach (['de', 'en'] as $locale) {
            $this->assertStringNotContainsString('erkbank', (string) file_get_contents(lang_path($locale.'/app.php')));
        }
    }

    public function test_the_maintenance_commands_use_the_takt_prefix(): void
    {
        $this->artisan('takt:purge-trash')->assertSuccessful();

        $commands = array_keys(Artisan::all());

        foreach (['takt:backup', 'takt:icons', 'takt:history', 'takt:assign-owner', 'takt:purge-trash'] as $command) {
            $this->assertContains($command, $commands);
        }

        $this->assertSame([], array_filter($commands, fn (string $name): bool => str_starts_with($name, 'werkbank:')));
    }

    public function test_the_sidebar_can_be_collapsed(): void
    {
        $this->login();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-nav-toggle', false)
            ->assertSee('nav-shell', false)
            ->assertSee('nav-label', false)
            ->assertSee('nav-brand', false)
            ->assertSee('nav-list', false)
            ->assertSee('nav-account', false)
            ->assertSee('nav-logo-slot', false)
            ->assertSee('nav-expand', false)
            ->assertSee('nav-collapse', false)
            ->assertSee('Seitenleiste ein-/ausklappen')
            ->assertSee('Seitenleiste ausklappen')
            ->assertSee('nav-main', false);
    }

    public function test_the_account_menu_carries_settings_trash_and_logout(): void
    {
        $this->login();

        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('data-account-toggle', false)
            ->assertSee('data-account-menu', false)
            ->assertSee('Mein Konto')
            ->assertSee('Einstellungen')
            ->assertSee('Papierkorb')
            ->assertSee('Abmelden');

        // exactly one logout form, and it lives inside the account menu
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, route('logout')));
        $this->assertStringContainsString(route('logout'), explode('data-account-menu', $html)[1]);
        $this->assertStringNotContainsString('nav-item', explode('data-account-menu', $html)[1]);

        // the popover sits outside the sidebar, so no backdrop filter can clip it
        $this->assertStringContainsString('nav-menu fixed', $html);
        $this->assertStringNotContainsString('data-account-menu', explode('</aside>', $html)[0]);
    }

    public function test_the_app_bundle_is_built_with_icon_launcher_and_plist(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('The bundle is macOS only.');
        }

        $target = sys_get_temp_dir().'/takt-bundle-'.bin2hex(random_bytes(4));

        $this->artisan('takt:app', ['--path' => $target, '--port' => 8123])->assertSuccessful();

        $bundle = $target.'/Takt.app';

        $this->assertFileExists($bundle.'/Contents/Info.plist');
        $this->assertFileExists($bundle.'/Contents/Resources/AppIcon.icns');
        $this->assertFileExists($bundle.'/Contents/MacOS/Takt');
        $this->assertTrue(is_executable($bundle.'/Contents/MacOS/Takt'));

        $plist = (string) file_get_contents($bundle.'/Contents/Info.plist');

        $this->assertStringContainsString('<key>CFBundleName</key><string>Takt</string>', $plist);
        $this->assertStringContainsString('de.takt.app', $plist);

        $plist = (string) file_get_contents($bundle.'/Contents/Info.plist');

        $this->assertStringContainsString('<key>TaktPort</key><integer>8123</integer>', $plist);
        $this->assertStringContainsString('<key>TaktRoot</key><string>'.base_path().'</string>', $plist);
        $this->assertStringContainsString('<key>TaktPhp</key><string>'.PHP_BINARY.'</string>', $plist);

        // a real Mach-O binary where Xcode's toolchain exists, the shell launcher otherwise
        $binary = (string) file_get_contents($bundle.'/Contents/MacOS/Takt');

        if (is_file('/usr/bin/swiftc')) {
            $this->assertStringStartsWith("\xcf\xfa\xed\xfe", $binary);
        } else {
            $this->assertStringStartsWith('#!/bin/bash', $binary);
        }

        exec('/bin/rm -rf '.escapeshellarg($target));
    }

    public function test_the_login_item_points_at_this_installation(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('Autostart is macOS only.');
        }

        $exit = Artisan::call('takt:autostart', ['--dry-run' => true, '--port' => 8123]);
        $plist = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('de.takt.server', $plist);
        $this->assertStringContainsString('--port=8123', $plist);
        $this->assertStringContainsString(base_path().'/artisan', $plist);
        $this->assertStringContainsString('<key>KeepAlive</key><true/>', $plist);
    }

    public function test_confirmations_are_rendered_as_an_in_app_dialog(): void
    {
        $this->login();

        $this->get(route('trash'))
            ->assertOk()
            ->assertSee('data-dialog', false)
            ->assertSee('data-dialog-accept', false)
            ->assertSee('Bitte bestätigen');
    }
}
