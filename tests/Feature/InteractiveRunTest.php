<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Models\CommandRun;
use App\Models\Project;
use App\Services\CommandRunner;
use App\Support\TerminalText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A target that asks something only works with a terminal of its own. These tests skip on a
 * machine without python3, because that is what the pty helper runs on.
 */
class InteractiveRunTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        if (! app(CommandRunner::class)->interactiveAvailable()) {
            $this->markTestSkipped('No python3 for the pty helper.');
        }

        $folder = storage_path('framework/testing/pty-'.uniqid());
        File::makeDirectory($folder, recursive: true);
        File::put($folder.'/Makefile', implode("\n", [
            'ask:',
            "\t@printf \"Name? \"; read name; echo \"hallo \$\$name\"",
            '',
            'terminal:',
            "\t@test -t 0 && echo \"TTY\" || echo \"kein TTY\"",
            '',
        ]));

        $this->project = Project::query()->create(['name' => 'Probe', 'path' => $folder, 'position' => 0]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->project->path);
        File::deleteDirectory(storage_path('app/runs'));

        parent::tearDown();
    }

    private function runner(): CommandRunner
    {
        return app(CommandRunner::class);
    }

    public function test_a_target_gets_a_real_terminal(): void
    {
        $run = $this->runner()->start($this->project, 'terminal');

        $this->assertTrue($run->interactive);

        $state = $this->waitFor($run);

        $this->assertSame(RunStatus::Finished, $state['status']);
        $this->assertStringContainsString('TTY', $state['output']);
        $this->assertStringNotContainsString('kein TTY', $state['output']);
    }

    public function test_a_prompt_can_be_answered(): void
    {
        $run = $this->runner()->start($this->project, 'ask');

        $this->waitForOutput($run, 'Name?');

        $this->assertTrue($this->runner()->write($run, 'Seymen'));

        $state = $this->waitFor($run);

        $this->assertStringContainsString('hallo Seymen', $state['output']);
        $this->assertSame(0, $state['exit_code']);
    }

    public function test_the_endpoint_answers_a_prompt_and_refuses_a_finished_run(): void
    {
        $start = $this->postJson(route('commands.run', $this->project), ['target' => 'ask'])->assertOk();

        $id = $start->json('id');
        $start->assertJsonPath('interactive', true);

        $run = CommandRun::query()->findOrFail($id);
        $this->waitForOutput($run, 'Name?');

        $this->postJson(route('commands.input', $id), ['line' => 'Seymen'])
            ->assertOk()
            ->assertJsonPath('running', true);

        $this->waitFor($run);

        // once it is over there is nothing left to type into
        $this->postJson(route('commands.input', $id), ['line' => 'zu spät'])
            ->assertStatus(422)
            ->assertJsonPath('error', __('app.run.no_input'));
    }

    public function test_the_input_field_only_shows_for_a_running_interactive_run(): void
    {
        $this->get(route('commands'))
            ->assertOk()
            ->assertSee('data-run-input-form', escape: false)
            ->assertSee(__('app.run.input_placeholder'));
    }

    public function test_terminal_output_is_cleaned_up(): void
    {
        $raw = "\e[32mfertig\e[0m\nLade: 10%\rLade: 90%\rLade: 100%\nab\x08c\n";

        $this->assertSame("fertig\nLade: 100%\nac\n", TerminalText::clean($raw));
    }

    /** @return array<string, mixed> */
    private function waitFor(CommandRun $run): array
    {
        foreach (range(1, 60) as $ignored) {
            $state = $this->runner()->state($run);

            if (! $state['running']) {
                return $state;
            }

            usleep(150_000);
        }

        $this->fail('The run never finished.');
    }

    private function waitForOutput(CommandRun $run, string $needle): void
    {
        foreach (range(1, 40) as $ignored) {
            if (str_contains($this->runner()->state($run)['output'], $needle)) {
                return;
            }

            usleep(150_000);
        }

        $this->fail('The output never contained: '.$needle);
    }
}
