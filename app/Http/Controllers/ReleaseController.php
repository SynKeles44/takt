<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Releases;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    public function __invoke(Releases $releases): View
    {
        $groups = $releases->forProjects();

        return view('releases', [
            'groups' => $groups,
            'count' => $releases->count($groups),
            'projects' => Project::query()->inOrder()->get(),
        ]);
    }
}
