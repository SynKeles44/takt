<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\NetworkAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
        app(NetworkAccess::class)->disable();
    }

    protected function tearDown(): void
    {
        app(NetworkAccess::class)->disable();

        parent::tearDown();
    }

    public function test_it_is_off_until_it_is_switched_on(): void
    {
        $network = app(NetworkAccess::class);

        $this->assertFalse($network->enabled());

        $network->enable();
        $this->assertTrue($network->enabled());
        $this->assertFileExists($network->path());

        $network->disable();
        $this->assertFalse($network->enabled());
    }

    public function test_the_switch_travels_through_the_settings(): void
    {
        $this->put(route('settings.network'), ['enabled' => 1])->assertRedirect();
        $this->assertTrue(app(NetworkAccess::class)->enabled());

        $this->put(route('settings.network'), ['enabled' => 0])->assertRedirect();
        $this->assertFalse(app(NetworkAccess::class)->enabled());
    }

    public function test_the_settings_page_states_what_the_switch_does(): void
    {
        $this->get(route('settings'))
            ->assertOk()
            ->assertSee(__('app.network.title'))
            ->assertSee(__('app.network.hint'));
    }

    public function test_the_address_is_only_shown_while_it_is_on(): void
    {
        $this->get(route('settings'))->assertOk()->assertDontSee(__('app.network.needs_restart'));

        app(NetworkAccess::class)->enable();

        $this->get(route('settings'))->assertOk()->assertSee(__('app.network.needs_restart'));
    }

    public function test_an_address_carries_the_port_and_a_real_ip(): void
    {
        $network = app(NetworkAccess::class);
        $address = $network->address(8000);

        if ($address === null) {
            $this->markTestSkipped('no network on this machine');
        }

        $this->assertMatchesRegularExpression('#^http://(\d{1,3}\.){3}\d{1,3}:8000$#', $address);
    }

    public function test_a_missing_value_is_rejected(): void
    {
        $this->put(route('settings.network'), [])->assertSessionHasErrors('enabled');
    }
}
