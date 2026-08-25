<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Models\CommandRun;
use App\Models\Project;
use App\Models\User;
use App\Services\CommandRunner;
use App\Services\MakeTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CommandRunTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        $folder = storage_path('framework/testing/make-'.uniqid());
        File::makeDirectory($folder, recursive: true);
        File::put($folder.'/Makefile', <<<'MAKE'
            .PHONY: hello boom slow
            PORT ?= 8000

            hello: ## says hello
            	@echo "hallo aus make"

            boom:
            	@exit 3

            slow: ## takes a while
            	@sleep 5

            %.o: %.c
            	@true
            MAKE);

        // the heredoc above is indented; make needs real tabs, which File::put keeps
        File::put($folder.'/Makefile', str_replace('    	', "\t", File::get($folder.'/Makefile')));
        File::put($folder.'/Makefile', preg_replace('/^ {12}/m', '', (string) File::get($folder.'/Makefile')) ?? '');

        $this->project = Project::query()->create([
            'name' => 'Probe',
            'path' => $folder,
            'position' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->project->path).'/'.basename($this->project->path));
        File::deleteDirectory(storage_path('app/runs'));

        parent::tearDown();
    }

    private function runner(): CommandRunner
    {
        return app(CommandRunner::class);
    }

    public function test_the_targets_are_read_with_their_descriptions(): void
    {
        $targets = app(MakeTargets::class)->forProject($this->project);
        $names = array_column($targets, 'name');

        $this->assertSame(['hello', 'boom', 'slow'], $names);
        $this->assertSame('says hello', $targets[0]['description']);
        $this->assertNull($targets[1]['description']);

        // no special targets, no pattern rules, no variables
        $this->assertNotContains('.PHONY', $names);
        $this->assertNotContains('%.o', $names);
        $this->assertNotContains('PORT', $names);
    }

    public function test_a_target_runs_and_its_output_is_kept(): void
    {
        $run = $this->runner()->start($this->project, 'hello');

        $this->assertNotNull($run);

        $state = $this->waitFor($run);

        $this->assertSame(RunStatus::Finished, $state['status']);
        $this->assertSame(0, $state['exit_code']);
        $this->assertStringContainsString('hallo aus make', $state['output']);
    }

    public function test_a_failing_target_is_reported_with_its_exit_code(): void
    {
        $run = $this->runner()->start($this->project, 'boom');

        $state = $this->waitFor($run);

        $this->assertSame(RunStatus::Failed, $state['status']);

        // make answers with 2 when a recipe fails, not with the recipe's own code
        $this->assertSame(2, $state['exit_code']);
    }

    public function test_a_running_target_can_be_stopped(): void
    {
        $run = $this->runner()->start($this->project, 'slow');

        $this->assertTrue($this->runner()->state($run)['running']);

        $this->runner()->stop($run);

        $this->assertSame(RunStatus::Stopped, $run->refresh()->status);
        $this->assertFalse($this->runner()->state($run)['running']);
    }

    public function test_only_a_target_from_the_makefile_is_ever_run(): void
    {
        $this->assertNull($this->runner()->start($this->project, 'rm'));

        $this->postJson(route('commands.run', $this->project), ['target' => 'gibt-es-nicht'])
            ->assertStatus(422)
            ->assertJsonPath('error', __('app.run.unknown_target'));

        // and nothing that looks like a command gets through validation
        foreach (['hello; rm -rf /tmp/x', 'hello && whoami', '$(whoami)', '../escape'] as $attempt) {
            $this->postJson(route('commands.run', $this->project), ['target' => $attempt])
                ->assertJsonValidationErrors('target');
        }

        $this->assertSame(0, CommandRun::query()->count());
    }

    public function test_the_endpoints_report_the_run_and_stop_it(): void
    {
        $start = $this->postJson(route('commands.run', $this->project), ['target' => 'slow'])->assertOk();

        $id = $start->json('id');

        $start->assertJsonPath('command', 'make slow')
            ->assertJsonPath('running', true)
            ->assertJsonPath('project', 'Probe');

        $this->getJson(route('commands.show', $id))->assertOk()->assertJsonPath('running', true);

        $this->deleteJson(route('commands.stop', $id))
            ->assertOk()
            ->assertJsonPath('running', false)
            ->assertJsonPath('status', RunStatus::Stopped->value);
    }

    public function test_the_page_lists_the_targets_and_the_recent_runs(): void
    {
        $this->runner()->start($this->project, 'hello');

        $this->get(route('commands'))
            ->assertOk()
            ->assertSee('make hello', escape: false)
            ->assertSee('says hello')
            ->assertSee(__('app.run.recent'))
            ->assertSee('data-run-dialog', escape: false);
    }

    public function test_the_projects_are_collapsed_and_filterable(): void
    {
        $response = $this->get(route('commands'))->assertOk();

        // collapsed by default, and the filter needs something to search in
        $response->assertSee('data-remember="commands.', escape: false);
        $response->assertSee('data-command-filter', escape: false);
        $response->assertSee('data-search="hello says hello"', escape: false);
        $response->assertDontSee('<details data-remember="commands.'.$this->project->getKey().'" open', escape: false);
    }

    public function test_old_runs_and_their_output_are_pruned(): void
    {
        $old = CommandRun::query()->create([
            'project_id' => $this->project->getKey(),
            'target' => 'hello',
            'status' => RunStatus::Finished,
            'started_at' => now()->subDays(9),
        ]);

        $running = CommandRun::query()->create([
            'project_id' => $this->project->getKey(),
            'target' => 'slow',
            'status' => RunStatus::Running,
            'started_at' => now()->subDays(9),
        ]);

        File::ensureDirectoryExists(storage_path('app/runs'));
        File::put($old->logPath(), 'alt');
        File::put(storage_path('app/runs/9999.log'), 'waise');

        $this->assertSame(1, $this->runner()->prune());

        $this->assertNull(CommandRun::query()->find($old->getKey()));
        $this->assertFalse(File::exists($old->logPath()));
        $this->assertFalse(File::exists(storage_path('app/runs/9999.log')));

        // a run that is still going is never pruned, however old it looks
        $this->assertNotNull(CommandRun::query()->find($running->getKey()));
    }

    public function test_a_target_finds_the_tools_a_terminal_would_find(): void
    {
        File::append($this->project->absolutePath().'/Makefile', "\ntools:\n\t@command -v make\n");

        $run = $this->runner()->start($this->project, 'tools');
        $state = $this->waitFor($run);

        $this->assertSame(RunStatus::Finished, $state['status']);
        $this->assertNotSame('', trim($state['output']));
    }

    public function test_another_users_run_is_out_of_reach(): void
    {
        $other = User::factory()->create();

        $foreign = new CommandRun(['project_id' => $this->project->getKey(), 'target' => 'hello', 'started_at' => now()]);
        $foreign->user_id = $other->getKey();
        $foreign->save();

        $this->getJson(route('commands.show', $foreign))->assertNotFound();
        $this->deleteJson(route('commands.stop', $foreign))->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function waitFor(CommandRun $run): array
    {
        foreach (range(1, 40) as $ignored) {
            $state = $this->runner()->state($run);

            if (! $state['running']) {
                return $state;
            }

            usleep(150_000);
        }

        $this->fail('The run never finished.');
    }
}
