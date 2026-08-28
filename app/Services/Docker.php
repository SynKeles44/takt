<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Support\ShellEnvironment;
use App\Support\TerminalText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * The containers on this machine, grouped the way compose groups them.
 *
 * Only ids that came out of `docker ps` are ever acted on: the page sends an id, it is looked
 * up in the current list, and the looked-up id is what reaches docker. Start, stop and restart
 * are all reversible — removing a container is deliberately not offered here.
 */
final class Docker
{
    /** Eight seconds: long enough for a healthy daemon, short enough to not stall the page. */
    private const int LIST_TIMEOUT = 8;

    public const int LOG_LINES = 300;

    /** A real 0x1f between the fields — a literal "\x1f" would just be printed. */
    private const string FORMAT = "{{.ID}}\x1f{{.Names}}\x1f{{.Image}}\x1f{{.State}}\x1f{{.Status}}\x1f{{.Ports}}\x1f{{.Label \"com.docker.compose.project\"}}\x1f{{.Label \"com.docker.compose.service\"}}\x1f{{.RunningFor}}";

    public function available(): bool
    {
        return ShellEnvironment::binary('docker') !== null;
    }

    /** @return array{ok: bool, error: ?string, groups: Collection<int, array>, running: int, total: int} */
    public function overview(): array
    {
        if (! $this->available()) {
            return $this->problem(__('app.docker.missing'));
        }

        $result = $this->docker(['ps', '-a', '--no-trunc', '--format', self::FORMAT], self::LIST_TIMEOUT);

        if ($result === null) {
            return $this->problem(__('app.docker.unreachable'));
        }

        if (! $result->successful()) {
            return $this->problem($this->explain($result->errorOutput()));
        }

        $containers = $this->parse($result->output());

        return [
            'ok' => true,
            'error' => null,
            'groups' => $this->group($containers),
            'running' => $containers->where('running', true)->count(),
            'total' => $containers->count(),
        ];
    }

    /** @return array{id: string, name: string, running: bool}|null */
    public function find(string $id): ?array
    {
        $overview = $this->overview();

        if (! $overview['ok']) {
            return null;
        }

        foreach ($overview['groups'] as $group) {
            foreach ($group['containers'] as $container) {
                if ($container['id'] === $id || $container['short'] === $id) {
                    return $container;
                }
            }
        }

        return null;
    }

    /** @return array{ok: bool, error: ?string} */
    public function act(string $id, string $action): array
    {
        if (! in_array($action, ['start', 'stop', 'restart'], true)) {
            return ['ok' => false, 'error' => __('app.docker.unknown_action')];
        }

        $container = $this->find($id);

        if ($container === null) {
            return ['ok' => false, 'error' => __('app.docker.unknown_container')];
        }

        $result = $this->docker([$action, $container['id']], 90);

        if ($result === null || ! $result->successful()) {
            return ['ok' => false, 'error' => $this->explain($result?->errorOutput() ?? '')];
        }

        return ['ok' => true, 'error' => null];
    }

    /** @return array{ok: bool, error: ?string, output: string} */
    public function logs(string $id, int $lines = self::LOG_LINES): array
    {
        $container = $this->find($id);

        if ($container === null) {
            return ['ok' => false, 'error' => __('app.docker.unknown_container'), 'output' => ''];
        }

        $result = $this->docker(['logs', '--tail', (string) $lines, '--timestamps', $container['id']], 30);

        if ($result === null) {
            return ['ok' => false, 'error' => __('app.docker.unreachable'), 'output' => ''];
        }

        // docker writes a container's stderr to stderr, and both belong in the same view
        $output = TerminalText::clean($result->output().$result->errorOutput());

        return ['ok' => $result->successful(), 'error' => null, 'output' => trim($output)];
    }

    /**
     * A hung docker daemon must not take a page down with it: the timeout throws, and a page
     * that says "docker is not answering" is worth more than a 500.
     */
    private function docker(array $arguments, int $timeout)
    {
        $binary = ShellEnvironment::binary('docker');

        if ($binary === null) {
            return null;
        }

        try {
            return Process::timeout($timeout)
                ->env(ShellEnvironment::variables())
                ->run([$binary, ...$arguments]);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function parse(string $output): Collection
    {
        return collect(preg_split('/\R/', trim($output)) ?: [])
            ->filter()
            ->map(function (string $line): array {
                [$id, $name, $image, $state, $status, $ports, $project, $service, $age] =
                    array_pad(explode("\x1f", $line), 9, '');

                return [
                    'id' => $id,
                    'short' => substr($id, 0, 12),
                    'name' => $name,
                    'image' => $image,
                    'state' => $state,
                    'status' => $status,
                    'ports' => $this->ports($ports),
                    'project' => $project,
                    'service' => $service === '' ? $name : $service,
                    'age' => $age,
                    'running' => $state === 'running',
                ];
            })
            ->values();
    }

    /**
     * Grouped by compose project, running groups first, and inside a group the same order
     * docker reported — that is how OrbStack reads, and it is the order that helps.
     *
     * @param  Collection<int, array<string, mixed>>  $containers
     * @return Collection<int, array<string, mixed>>
     */
    private function group(Collection $containers): Collection
    {
        return $containers
            ->groupBy(fn (array $container): string => $container['project'])
            ->map(fn (Collection $group, string $project): array => [
                'project' => $project,
                'label' => $project === '' ? __('app.docker.standalone') : $project,
                'containers' => $group->sortBy('service')->values()->all(),
                'running' => $group->where('running', true)->count(),
                'total' => $group->count(),
                'path' => $project === '' ? null : $this->workingDir($group),
            ])
            ->sortBy(fn (array $group): string => sprintf(
                '%d-%s',
                $group['running'] > 0 ? 0 : 1,
                strtolower($group['label']),
            ))
            ->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $group */
    private function workingDir(Collection $group): ?string
    {
        $project = Project::query()->get()->first(
            fn (Project $candidate): bool => $candidate->slug() !== null
                && str_contains(strtolower((string) $candidate->name), strtolower($group->first()['project'])),
        );

        return $project?->path;
    }

    /** Only the published ports, and each one once — the raw string repeats IPv4 and IPv6. */
    private function ports(string $raw): array
    {
        $ports = [];

        foreach (explode(', ', $raw) as $part) {
            if (! str_contains($part, '->')) {
                continue;
            }

            [$from, $to] = explode('->', $part, 2);
            $host = trim(substr($from, (int) strrpos($from, ':') + 1));

            $ports[$host.'->'.$to] = ['host' => $host, 'container' => $to];
        }

        return array_values($ports);
    }

    private function explain(string $error): string
    {
        $error = trim($error);

        if (str_contains($error, 'Cannot connect to the Docker daemon') || str_contains($error, 'daemon is not running')) {
            return __('app.docker.not_running');
        }

        return $error === '' ? __('app.docker.unreachable') : $error;
    }

    /** @return array{ok: bool, error: string, groups: Collection<int, array>, running: int, total: int} */
    private function problem(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'groups' => collect(), 'running' => 0, 'total' => 0];
    }
}
