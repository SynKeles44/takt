<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FrontendBuild;
use Illuminate\Http\JsonResponse;

class BuildController extends Controller
{
    public function __invoke(FrontendBuild $build): JsonResponse
    {
        $result = $build->run();

        if ($result['ok']) {
            return response()->json(['reload' => true]);
        }

        return response()->json(['status' => __('app.build.failed', ['reason' => $result['reason']])]);
    }
}
