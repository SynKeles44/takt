<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Docker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Docker is faked throughout: these tests must never start or stop a real container.
 */
class DockerTest extends TestCase
{
    use RefreshDatabase;

    private const string LIST = "abc123456789\x1fgalawork-web-galawork-php-1\x1fgalawork-web-php\x1frunning\x1fUp 8 days\x1f9000/tcp\x1fgalawork-web\x1fgalawork-php\x1f8 days ago
def987654321\x1fgalawork-web-galawork-nginx-1\x1fnginx:alpine\x1frunning\x1fUp 8 days\x1f0.0.0.0:80->80/tcp, [::]:80->80/tcp\x1fgalawork-web\x1fgalawork-nginx\x1f8 days ago
aaa111222333\x1fdata-helpers-php82-1\x1fphp:8.2\x1fexited\x1fExited (0) 2 days ago\x1f\x1fdata-helpers\x1fphp82\x1f2 days ago
bbb444555666\x1fphpstorm_helpers\x1fphpstorm/helpers\x1fexited\x1fExited (0) 5 days ago\x1f\x1f\x1f\x1f5 days ago";

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
    }

    private function fake(string $list = self::LIST, int $exit = 0): void
    {
        /*
         * The arguments are quoted in the command line, so the patterns match the quoted
         * argument itself — "*ps*" alone would also match "--timestamps".
         */
        Process::fake([
            "*'ps'*" => Process::result(output: $list, exitCode: $exit),
            "*'logs'*" => Process::result(output: "2026-08-25T10:00:00Z \e[32mready\e[0m\n"),
            "*'start'*" => Process::result(output: 'abc123456789'),
            "*'stop'*" => Process::result(output: 'abc123456789'),
            "*'restart'*" => Process::result(output: 'abc123456789'),
            '*' => Process::result(output: ''),
        ]);
    }

    public function test_the_containers_are_grouped_by_compose_project(): void
    {
        $this->fake();

        $overview = app(Docker::class)->overview();

        $this->assertTrue($overview['ok']);
        $this->assertSame(2, $overview['running']);
        $this->assertSame(4, $overview['total']);

        $groups = $overview['groups'];

        // running groups come first, then the rest by name
        $this->assertSame(['galawork-web', 'data-helpers', ''], $groups->pluck('project')->all());
        $this->assertSame(__('app.docker.standalone'), $groups->last()['label']);
        $this->assertSame(2, $groups->first()['running']);
    }

    public function test_published_ports_are_listed_once(): void
    {
        $this->fake();

        $nginx = collect(app(Docker::class)->overview()['groups']->first()['containers'])
            ->firstWhere('service', 'galawork-nginx');

        $this->assertSame([['host' => '80', 'container' => '80/tcp']], $nginx['ports']);
    }

    public function test_a_stopped_daemon_is_reported_in_plain_words(): void
    {
        Process::fake([
            "*'ps'*" => Process::result(
                errorOutput: 'Cannot connect to the Docker daemon at unix:///var/run/docker.sock.',
                exitCode: 1,
            ),
            '*' => Process::result(output: ''),
        ]);

        $overview = app(Docker::class)->overview();

        $this->assertFalse($overview['ok']);
        $this->assertSame(__('app.docker.not_running'), $overview['error']);

        $this->get(route('docker'))->assertOk()->assertSee(__('app.docker.not_running'));
    }

    public function test_an_action_runs_only_for_a_container_from_the_list(): void
    {
        $this->fake();

        $this->postJson(route('docker.act'), ['id' => 'abc123456789', 'action' => 'stop'])
            ->assertOk()
            ->assertJsonPath('message', __('app.docker.stopped', ['name' => 'galawork-php']));

        Process::assertRan(function ($process): bool {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, 'stop') && str_contains($command, 'abc123456789');
        });

        // an id that is not in the list never reaches docker
        $this->postJson(route('docker.act'), ['id' => 'deadbeef', 'action' => 'stop'])
            ->assertStatus(422)
            ->assertJsonPath('error', __('app.docker.unknown_container'));
    }

    public function test_nothing_that_looks_like_a_command_is_accepted(): void
    {
        $this->fake();

        foreach (['abc; rm -rf /tmp/x', '$(whoami)', '../escape', 'abc def'] as $attempt) {
            $this->postJson(route('docker.act'), ['id' => $attempt, 'action' => 'stop'])
                ->assertJsonValidationErrors('id');
        }

        $this->postJson(route('docker.act'), ['id' => 'abc123456789', 'action' => 'remove'])
            ->assertJsonValidationErrors('action');
    }

    public function test_logs_come_back_without_colour_codes(): void
    {
        $this->fake();

        $this->getJson(route('docker.logs', ['id' => 'abc123456789']))
            ->assertOk()
            ->assertJsonPath('output', '2026-08-25T10:00:00Z ready')
            ->assertJsonPath('title', __('app.docker.log_title', ['name' => 'galawork-php']));
    }

    public function test_the_page_lists_the_groups_and_the_list_can_be_refreshed_on_its_own(): void
    {
        $this->fake();

        $this->get(route('docker'))
            ->assertOk()
            ->assertSee('galawork-web')
            ->assertSee('data-region="docker-list"', escape: false)
            ->assertSee(route('docker.list'), escape: false);

        $this->get(route('docker.list'))
            ->assertOk()
            ->assertSee('galawork-web')
            ->assertDontSee('<html', escape: false);
    }

    public function test_the_pages_need_a_login(): void
    {
        auth()->logout();

        $this->get(route('docker'))->assertRedirect(route('login'));
        $this->postJson(route('docker.act'), ['id' => 'abc', 'action' => 'stop'])->assertUnauthorized();
    }
}
