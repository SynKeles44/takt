<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CommandRunner;
use App\Services\Packages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dependencies per project: what is declared, what is installed, what is behind — and one
 * button per package to close the gap.
 */
class PackageController extends Controller
{
    public function index(Packages $packages): View
    {
        $rows = $packages->overview();

        return view('packages', [
            'rows' => $rows,
            'summary' => $packages->summary($rows),
            'advisories' => $packages->advisories($rows),
            'shared' => $packages->shared($rows),
        ]);
    }

    /**
     * Every project at once. Each project's two registry calls already run in parallel; the
     * projects themselves run one after another, because a fan-out of fan-outs opens as many
     * network calls as there are projects times managers, and the registries throttle.
     */
    public function checkAll(Packages $packages): RedirectResponse
    {
        $projects = Project::query()->inOrder()->get();
        $checked = 0;

        foreach ($projects as $project) {
            $packages->forget($project);
            $packages->check($project);
            $checked++;
        }

        return back()->with('status', __('app.packages.checked_all', ['count' => $checked]));
    }

    /** Ask the registries for one project. Slow on purpose — never on a page load. */
    public function check(Project $project, Packages $packages): RedirectResponse
    {
        $packages->forget($project);
        $packages->check($project);

        return back()->with('status', __('app.packages.checked', ['project' => $project->name]));
    }

    public function update(Request $request, Project $project, Packages $packages, CommandRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'manager' => ['required', 'in:composer,npm'],
            'package' => ['required', 'string', 'max:120'],
        ]);

        /*
         * The name is checked against what this project's own manifest declares, not against a
         * pattern. A pattern would accept any well-formed package; this accepts only the ones
         * already on the page, so a crafted request cannot reach a package nobody asked for.
         */
        if (! $packages->declares($project, $data['manager'], $data['package'])) {
            return back()->with('status', __('app.packages.unknown', ['package' => $data['package']]));
        }

        $run = $runner->startPackageUpdate($project, $data['manager'], $data['package']);

        if ($run === null) {
            return back()->with('status', __('app.packages.failed'));
        }

        // the cached answer is about to be wrong — the next visit asks again
        $packages->forget($project);

        return redirect()->route('commands.show', $run);
    }
}
