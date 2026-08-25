<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectRunner;
use App\Services\ProjectScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(ProjectRunner $runner): View
    {
        $projects = Project::query()->inOrder()->get();

        return view('projects', [
            'projects' => $projects,
            'states' => $projects->mapWithKeys(fn (Project $project): array => [
                $project->getKey() => $runner->state($project),
            ]),
        ]);
    }

    /** Reads a picked folder so the form fills itself. */
    public function scan(Request $request, ProjectScanner $scanner): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:400']]);

        return response()->json($scanner->scan($data['path']));
    }

    public function store(Request $request): RedirectResponse
    {
        Project::query()->create($this->validated($request) + [
            'position' => (int) Project::query()->max('position') + 1,
        ]);

        return back()->with('status', __('app.dev.project_saved'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validated($request, $project));

        return back()->with('status', __('app.dev.project_saved'));
    }

    public function destroy(Project $project, ProjectRunner $runner): RedirectResponse
    {
        $runner->stop($project);
        $project->delete();

        return back()->with('status', __('app.dev.project_deleted'));
    }

    public function start(Project $project, ProjectRunner $runner): RedirectResponse
    {
        return $runner->start($project)
            ? back()->with('status', __('app.dev.started', ['name' => $project->name]))
            : back()->with('status', __('app.dev.start_failed', ['name' => $project->name]));
    }

    public function stop(Project $project, ProjectRunner $runner): RedirectResponse
    {
        $runner->stop($project);

        return back()->with('status', __('app.dev.stopped', ['name' => $project->name]));
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'path' => [
                'required', 'string', 'max:400',
                Rule::unique('projects', 'path')
                    ->where('user_id', $request->user()->getKey())
                    ->ignore($project?->getKey()),
            ],
            'repository' => ['nullable', 'string', 'max:200'],
            'start_command' => ['nullable', 'string', 'max:400'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);
    }
}
