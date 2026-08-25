<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Process;

/**
 * Starts and stops the local environment of a registered project. The command is the
 * one the owner configured — Takt only keeps the pid and reports the port.
 */
final class ProjectRunner
{
    public function state(Project $project): array
    {
        $pid = $this->pid($project);

        return [
            'pid' => $pid,
            'running' => $pid !== null,
            'port_open' => $project->port !== null && $this->portOpen($project->port),
        ];
    }

    public function start(Project $project): bool
    {
        if ($project->start_command === null || ! $project->exists() || $this->pid($project) !== null) {
            return false;
        }

        if (! is_dir(dirname($project->pidFile()))) {
            mkdir(dirname($project->pidFile()), 0o755, true);
        }

        $log = storage_path('logs/project-'.$project->getKey().'.log');

        // detached: the process must outlive this request
        $command = sprintf(
            'cd %s && nohup %s >> %s 2>&1 & echo $!',
            escapeshellarg($project->absolutePath()),
            $project->start_command,
            escapeshellarg($log),
        );

        $result = Process::timeout(20)->run(['/bin/bash', '-lc', $command]);

        if ($result->failed()) {
            return false;
        }

        $pid = (int) trim($result->output());

        if ($pid <= 0) {
            return false;
        }

        file_put_contents($project->pidFile(), $pid);

        return true;
    }

    public function stop(Project $project): bool
    {
        $pid = $this->pid($project);

        if ($pid === null) {
            return false;
        }

        // the recorded pid is the shell that owns the process group
        Process::run(['/bin/kill', '-TERM', '-'.$pid]);
        Process::run(['/bin/kill', '-TERM', (string) $pid]);

        @unlink($project->pidFile());

        return true;
    }

    private function pid(Project $project): ?int
    {
        if (! is_file($project->pidFile())) {
            return null;
        }

        $pid = (int) trim((string) file_get_contents($project->pidFile()));

        if ($pid <= 0) {
            return null;
        }

        if (Process::run(['/bin/kill', '-0', (string) $pid])->failed()) {
            @unlink($project->pidFile());

            return null;
        }

        return $pid;
    }

    private function portOpen(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $code, $message, 0.25);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
