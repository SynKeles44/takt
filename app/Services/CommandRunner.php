<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RunStatus;
use App\Models\CommandRun;
use App\Models\Project;
use App\Support\ShellEnvironment;
use App\Support\TerminalText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs one make target of a project and keeps its output where the page can follow it.
 *
 * The target is never taken from the request as a command: it is looked up in the project's
 * own Makefile first, and only its name is passed on. The run itself is detached — a build
 * that takes a minute must not hold a request open — and writes into a log file, with its
 * exit code in a second file, which is how a finished run is told from a crashed one.
 *
 * It runs through a login shell with the usual tool directories on the PATH. Takt is normally
 * started by a login item, which passes down a bare PATH — a Makefile calling docker then
 * fails with "docker: No such file or directory" while the same target works in a terminal.
 *
 * Where possible the command gets a real pseudo terminal (bin/takt-pty), because a target
 * doing `docker compose exec` without -T refuses to run without one, and because only then
 * can a prompt be answered: input written into the run's FIFO reaches the command's stdin.
 */
final class CommandRunner
{
    /** How much of the log the page gets at once. */
    public const int TAIL = 60_000;

    public function __construct(private readonly MakeTargets $targets) {}

    /** Whether this machine can give a run a terminal of its own. */
    public function interactiveAvailable(): bool
    {
        return $this->python() !== null && is_file($this->helper());
    }

    public function start(Project $project, string $target): ?CommandRun
    {
        if (! $this->targets->has($project, $target)) {
            return null;
        }

        File::ensureDirectoryExists(storage_path('app/runs'));

        $interactive = $this->interactiveAvailable();

        $run = CommandRun::query()->create([
            'project_id' => $project->getKey(),
            'target' => $target,
            'interactive' => $interactive,
            'status' => RunStatus::Running,
            'started_at' => Carbon::now(),
        ]);

        File::put($run->logPath(), '');
        File::delete([$run->exitPath(), $run->inputPath()]);

        if ($interactive) {
            Process::run(['/usr/bin/mkfifo', $run->inputPath()]);
        }

        /*
         * Detached on purpose, and with its own log: a server that waits for `make` would
         * hold the request until the build is done, and a pipe the child keeps open never
         * closes. The exit code lands in its own file, so the state survives a restart.
         */
        $script = $interactive
            ? sprintf(
                'cd %s && nohup %s %s %s %s %s %s %s </dev/null >/dev/null 2>&1 & echo $!',
                escapeshellarg($project->absolutePath()),
                escapeshellarg((string) $this->python()),
                escapeshellarg($this->helper()),
                escapeshellarg($run->logPath()),
                escapeshellarg($run->exitPath()),
                escapeshellarg($run->inputPath()),
                escapeshellarg(ShellEnvironment::shell()),
                escapeshellarg('make '.escapeshellarg($target)),
            )
            : sprintf(
                'cd %s && nohup %s -lc %s </dev/null >/dev/null 2>&1 & echo $!',
                escapeshellarg($project->absolutePath()),
                escapeshellarg(ShellEnvironment::shell()),
                escapeshellarg(sprintf(
                    'make %s >>%s 2>&1; printf "%%s" "$?" >%s',
                    escapeshellarg($target),
                    escapeshellarg($run->logPath()),
                    escapeshellarg($run->exitPath()),
                )),
            );

        $result = Process::timeout(15)->env(ShellEnvironment::variables())->run($script);
        $pid = (int) trim($result->output());

        if (! $result->successful() || $pid <= 0) {
            $run->update(['status' => RunStatus::Failed, 'finished_at' => Carbon::now()]);

            return $run;
        }

        $run->update(['pid' => $pid]);

        return $run;
    }

    /**
     * The run as it stands right now, with the tail of its output.
     *
     * @return array{status: RunStatus, exit_code: ?int, output: string, size: int, running: bool}
     */
    public function state(CommandRun $run): array
    {
        $this->settle($run);

        $log = $run->logPath();
        $size = is_file($log) ? (int) filesize($log) : 0;

        return [
            'status' => $run->status,
            'exit_code' => $run->exit_code,
            'output' => TerminalText::clean($this->tail($log, $size)),
            'size' => $size,
            'running' => $run->status->isOpen(),
        ];
    }

    /**
     * Old runs and their output do not need to pile up. Anything still marked running is left
     * alone, however old it looks — it might be a build that takes its time.
     */
    public function prune(int $days = 7): int
    {
        $stale = CommandRun::withoutGlobalScopes()
            ->where('status', '!=', RunStatus::Running)
            ->where('started_at', '<', Carbon::now()->subDays($days))
            ->get();

        foreach ($stale as $run) {
            File::delete([$run->logPath(), $run->exitPath(), $run->inputPath()]);
            $run->delete();
        }

        // files whose run is gone, from a database that was reset or a run deleted by hand
        $known = CommandRun::withoutGlobalScopes()->pluck('id')->map(fn (int $id): string => (string) $id)->all();

        foreach (File::glob(storage_path('app/runs/*')) as $file) {
            if (! in_array(pathinfo($file, PATHINFO_FILENAME), $known, true)) {
                File::delete($file);
            }
        }

        return $stale->count();
    }

    /** Answers a prompt: one line into the run's FIFO, which is the command's stdin. */
    public function write(CommandRun $run, string $line): bool
    {
        if (! $run->interactive || ! $run->status->isOpen() || ! file_exists($run->inputPath())) {
            return false;
        }

        $handle = @fopen($run->inputPath(), 'r+');

        if ($handle === false) {
            return false;
        }

        stream_set_blocking($handle, false);
        $written = fwrite($handle, rtrim($line, "\r\n").PHP_EOL);
        fclose($handle);

        return $written !== false;
    }

    public function stop(CommandRun $run): void
    {
        if ($run->pid !== null) {
            Process::run(['/usr/bin/pkill', '-P', (string) $run->pid]);
            Process::run(['/bin/kill', (string) $run->pid]);
        }

        File::delete($run->inputPath());

        $run->update(['status' => RunStatus::Stopped, 'finished_at' => Carbon::now()]);
    }

    /** Writes down what the file system already knows: finished, failed, or gone. */
    private function settle(CommandRun $run): void
    {
        if (! $run->status->isOpen()) {
            return;
        }

        if (is_file($run->exitPath())) {
            $code = (int) trim((string) File::get($run->exitPath()));

            File::delete($run->inputPath());

            $run->update([
                'status' => $code === 0 ? RunStatus::Finished : RunStatus::Failed,
                'exit_code' => $code,
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        if ($run->pid !== null && ! $this->alive($run->pid)) {
            File::delete($run->inputPath());

            $run->update(['status' => RunStatus::Failed, 'finished_at' => Carbon::now()]);
        }
    }

    private function alive(int $pid): bool
    {
        return Process::run(['/bin/kill', '-0', (string) $pid])->successful();
    }

    private function helper(): string
    {
        return base_path('bin/takt-pty');
    }

    /** The interpreter for the terminal helper — Homebrew's first, then the system one. */
    private function python(): ?string
    {
        foreach (['/opt/homebrew/bin/python3', '/usr/local/bin/python3', '/usr/bin/python3'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $found = trim(Process::run(['/usr/bin/which', 'python3'])->output());

        return $found !== '' && is_executable($found) ? $found : null;
    }

    private function tail(string $path, int $size): string
    {
        if (! is_file($path) || $size === 0) {
            return '';
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        if ($size > self::TAIL) {
            fseek($handle, $size - self::TAIL);
        }

        $output = (string) stream_get_contents($handle);
        fclose($handle);

        return $size > self::TAIL ? '…'.PHP_EOL.ltrim($output) : $output;
    }
}
