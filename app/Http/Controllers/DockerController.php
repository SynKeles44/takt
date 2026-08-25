<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Docker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DockerController extends Controller
{
    public function index(Docker $docker): View
    {
        return view('docker', ['docker' => $docker->overview()]);
    }

    /** The list on its own, so the page can refresh it without reloading. */
    public function list(Docker $docker): View
    {
        return view('partials.docker-list', ['docker' => $docker->overview()]);
    }

    public function act(Request $request, Docker $docker): JsonResponse
    {
        $data = $request->validate([
            // an id from the list, never a command
            'id' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'action' => ['required', 'in:start,stop,restart'],
        ]);

        $result = $docker->act($data['id'], $data['action']);

        if (! $result['ok']) {
            return response()->json(['error' => $result['error']], 422);
        }

        $container = $docker->find($data['id']);

        return response()->json([
            'message' => __('app.docker.'.match ($data['action']) {
                'start' => 'started',
                'stop' => 'stopped',
                default => 'restarted',
            }, ['name' => $container['service'] ?? $data['id']]),
        ]);
    }

    public function logs(Request $request, Docker $docker): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_.-]+$/']]);

        $container = $docker->find($data['id']);
        $logs = $docker->logs($data['id']);

        if ($container === null) {
            return response()->json(['error' => $logs['error']], 422);
        }

        return response()->json([
            'title' => __('app.docker.log_title', ['name' => $container['service']]),
            'name' => $container['name'],
            'output' => $logs['output'] === '' ? __('app.docker.no_logs') : $logs['output'],
        ]);
    }
}
