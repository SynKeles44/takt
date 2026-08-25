<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommandRun;
use App\Models\Project;
use App\Services\CommandRunner;
use App\Services\MakeTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The make targets of every registered project, and the runs started from them.
 */
class CommandController extends Controller
{
    public function index(Request $request, MakeTargets $targets): View
    {
        $projects = Project::query()->inOrder()->get();

        return view('commands', [
            'projects' => $projects,
            'targets' => $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $targets->forProject($project),
            ]),
            'missing' => $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $targets->file($project) === null,
            ]),
            'runs' => CommandRun::query()->with('project')->recent()->take(12)->get(),
        ]);
    }

    public function store(Request $request, Project $project, CommandRunner $runner): JsonResponse
    {
        $data = $request->validate([
            // a name, never a command: the runner looks it up in the project's own Makefile
            'target' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z][a-zA-Z0-9_.\/-]*$/'],
        ]);

        $run = $runner->start($project, $data['target']);

        if ($run === null) {
            return response()->json(['error' => __('app.run.unknown_target')], 422);
        }

        return response()->json($this->payload($run, $runner));
    }

    public function show(CommandRun $run, CommandRunner $runner): JsonResponse
    {
        return response()->json($this->payload($run, $runner));
    }

    /** Answers a prompt of a running target. */
    public function input(Request $request, CommandRun $run, CommandRunner $runner): JsonResponse
    {
        $data = $request->validate(['line' => ['present', 'string', 'max:500']]);

        if (! $runner->write($run, $data['line'])) {
            return response()->json(['error' => __('app.run.no_input')], 422);
        }

        return response()->json($this->payload($run, $runner));
    }

    public function destroy(CommandRun $run, CommandRunner $runner): JsonResponse
    {
        if ($run->status->isOpen()) {
            $runner->stop($run);
        }

        return response()->json($this->payload($run, $runner));
    }

    /** @return array<string, mixed> */
    private function payload(CommandRun $run, CommandRunner $runner): array
    {
        $state = $runner->state($run->refresh());

        return [
            'id' => $run->getKey(),
            'project' => $run->project->name,
            'command' => $run->command(),
            'status' => $state['status']->value,
            'label' => $state['status']->label(),
            'classes' => $state['status']->classes(),
            'exit_code' => $state['exit_code'],
            'running' => $state['running'],
            'interactive' => $run->interactive,
            'input_url' => route('commands.input', $run),
            'output' => $state['output'],
            'url' => route('commands.show', $run),
            'stop_url' => route('commands.stop', $run),
            'started_at' => $run->started_at->format('H:i:s'),
        ];
    }
}
