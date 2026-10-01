<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Releases;
use App\Support\Deferred;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    public function __invoke(Request $request, Releases $releases): View
    {
        // tags and release notes, per project, from GitHub
        $defer = Deferred::wanted($request);
        $groups = $defer ? collect() : $releases->forProjects();

        return view('releases', [
            'defer' => $defer,
            'groups' => $groups,
            'count' => $releases->count($groups),
            'projects' => Project::query()->inOrder()->get(),
        ]);
    }
}
