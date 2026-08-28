<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/**
 * Runs a set of commands at once and hands back their output per key. Four repositories then
 * cost the same wall clock as one, which is the whole point: the page waits for all of them.
 *
 * Keys must not be numeric strings — PHP would turn them into ints and Pool::as refuses those.
 */
final class Parallel
{
    /**
     * @param  array<string, list<string>>  $commands
     * @return array<string, string> the output per key, empty for a failed call
     */
    public static function run(array $commands, int $timeout = 15): array
    {
        if ($commands === []) {
            return [];
        }

        $results = Process::pool(function (Pool $pool) use ($commands, $timeout): void {
            foreach ($commands as $key => $arguments) {
                $pool->as($key)->timeout($timeout)->command($arguments);
            }
        })->start()->wait();

        $outputs = [];

        foreach (array_keys($commands) as $key) {
            $result = $results[$key] ?? null;

            $outputs[$key] = $result !== null && $result->successful() ? $result->output() : '';
        }

        return $outputs;
    }
}
