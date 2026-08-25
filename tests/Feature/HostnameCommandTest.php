<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\LocalUrl;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The command touches two files that must never be the real ones in a test — both
 * paths are options for exactly that reason.
 */
class HostnameCommandTest extends TestCase
{
    private string $hosts;

    private string $env;

    protected function setUp(): void
    {
        parent::setUp();

        $folder = storage_path('framework/testing/hostname-'.uniqid());
        File::makeDirectory($folder, recursive: true);

        $this->hosts = $folder.'/hosts';
        $this->env = $folder.'/.env';

        File::put($this->hosts, "127.0.0.1\tlocalhost\n255.255.255.255\tbroadcasthost\n");
        File::put($this->env, "APP_NAME=Takt\nAPP_URL=http://localhost:8000\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->hosts));

        parent::tearDown();
    }

    private function hostname(array $arguments = []): int
    {
        return $this->artisan('takt:hostname', array_merge([
            '--hosts-file' => $this->hosts,
            '--env-file' => $this->env,
        ], $arguments))->run();
    }

    public function test_it_writes_the_name_into_the_hosts_file_and_the_env(): void
    {
        $this->assertSame(0, $this->hostname(['host' => 'local.takt.de']));

        $this->assertStringContainsString('127.0.0.1 local.takt.de # takt', File::get($this->hosts));
        $this->assertStringContainsString('APP_URL=http://local.takt.de:8000', File::get($this->env));
    }

    public function test_a_name_that_is_already_there_is_left_alone(): void
    {
        File::append($this->hosts, "127.0.0.1\tlocal.takt.de\n");

        $this->hostname(['host' => 'local.takt.de']);

        $this->assertSame(1, substr_count(File::get($this->hosts), 'local.takt.de'));
    }

    public function test_the_port_reaches_the_url(): void
    {
        $this->hostname(['host' => 'local.takt.de', '--port' => 8080]);

        $this->assertStringContainsString('APP_URL=http://local.takt.de:8080', File::get($this->env));
    }

    public function test_port_eighty_needs_no_port_in_the_url(): void
    {
        $this->hostname(['host' => 'local.takt.de', '--port' => 80]);

        $this->assertStringContainsString('APP_URL=http://local.takt.de'."\n", File::get($this->env));
    }

    public function test_remove_puts_localhost_back_and_drops_our_line(): void
    {
        $this->hostname(['host' => 'local.takt.de']);
        $this->hostname(['--remove' => true]);

        $hosts = File::get($this->hosts);

        $this->assertStringNotContainsString('local.takt.de', $hosts);
        $this->assertStringContainsString("127.0.0.1\tlocalhost", $hosts);
        $this->assertStringContainsString('APP_URL=http://localhost:8000', File::get($this->env));
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $before = [File::get($this->hosts), File::get($this->env)];

        $this->hostname(['host' => 'local.takt.de', '--dry-run' => true]);

        $this->assertSame($before, [File::get($this->hosts), File::get($this->env)]);
    }

    public function test_nonsense_is_refused(): void
    {
        $this->assertSame(1, $this->hostname(['host' => 'not a host name']));

        $this->assertStringNotContainsString('not a host', File::get($this->hosts));
    }

    public function test_the_address_helper_reads_the_configured_url(): void
    {
        config(['app.url' => 'http://local.takt.de:8123']);

        $this->assertSame('local.takt.de', LocalUrl::host());
        $this->assertSame(8123, LocalUrl::port());
        $this->assertSame('http://local.takt.de:8123', LocalUrl::url());
        $this->assertSame('http://local.takt.de', LocalUrl::url(80));
        $this->assertTrue(LocalUrl::isLoopbackName('localhost'));
        $this->assertFalse(LocalUrl::isLoopbackName('local.takt.de'));
    }

    public function test_the_helper_falls_back_to_the_default_name(): void
    {
        config(['app.url' => '']);

        $this->assertSame(LocalUrl::DEFAULT_HOST, LocalUrl::host());
        $this->assertSame(LocalUrl::DEFAULT_PORT, LocalUrl::port());
    }
}
