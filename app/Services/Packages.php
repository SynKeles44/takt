<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Support\Parallel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What every project depends on, and what of it is behind.
 *
 * Two layers, deliberately kept apart. The manifests — composer.json, package.json and their
 * lock files — are on disk and cost nothing: they answer "what is installed" instantly, on every
 * page load. Whether a newer version exists is a network question that takes tens of seconds per
 * project, so it is fetched on demand and cached, never on the way to rendering a page.
 *
 * The cache holds arrays and ISO strings only. This app runs with `serializable_classes` off,
 * so a cached Carbon comes back as __PHP_Incomplete_Class and takes the page with it.
 */
final class Packages
{
    public const int CACHE_SECONDS = 1800;

    /** The one scale both tools' gradings are mapped onto, so a page can sort by it. */
    private const array SEVERITY_ORDER = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    /** `composer outdated` reaches packagist; `npm outdated` reaches the registry. Both are slow. */
    private const int CHECK_TIMEOUT = 180;

    /** A package name as the two ecosystems actually spell them — the allowlist for any update. */
    private const string NAME_PATTERN = '/^(@[a-z0-9._-]+\/)?[a-z0-9]([a-z0-9._-]*)(\/[a-z0-9]([a-z0-9._-]*))?$/i';

    /**
     * Every project with its declared dependencies, read from disk.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function overview(): Collection
    {
        return Project::query()
            ->inOrder()
            ->get()
            ->map(fn (Project $project): array => $this->forProject($project))
            ->filter(fn (array $row): bool => $row['managers'] !== [])
            ->values();
    }

    /** @return array<string, mixed> */
    public function forProject(Project $project): array
    {
        $managers = [];

        foreach (['composer', 'npm'] as $manager) {
            $packages = $this->declared($project, $manager);

            if ($packages !== []) {
                $managers[$manager] = $packages;
            }
        }

        $checked = $this->checked($project);

        $merged = $this->merge($managers, $checked['packages'] ?? []);
        $advisories = $checked['advisories'] ?? [];

        return [
            'project' => $project,
            'managers' => $this->withAdvisories($merged, $advisories),
            'advisories' => $advisories,
            'checked_at' => isset($checked['at']) ? Carbon::parse((string) $checked['at']) : null,
            'error' => $checked['error'] ?? null,
            'counts' => [...$this->counts($merged), 'vulnerable' => count($advisories)],
        ];
    }

    /**
     * Read what the project declares and what the lock file pinned. No network, no vendor
     * directory needed — a freshly cloned project still lists its dependencies.
     *
     * @return list<array<string, mixed>>
     */
    private function declared(Project $project, string $manager): array
    {
        $manifest = $this->json($project, $manager === 'composer' ? 'composer.json' : 'package.json');

        if ($manifest === null) {
            return [];
        }

        $locked = $manager === 'composer' ? $this->composerLock($project) : $this->npmLock($project);

        $sections = $manager === 'composer'
            ? ['require' => false, 'require-dev' => true]
            : ['dependencies' => false, 'devDependencies' => true, 'optionalDependencies' => true];

        $packages = [];

        foreach ($sections as $section => $isDev) {
            foreach ($manifest[$section] ?? [] as $name => $constraint) {
                // composer's `php` and `ext-*` entries are platform requirements, not packages
                if ($manager === 'composer' && ($name === 'php' || str_starts_with((string) $name, 'ext-'))) {
                    continue;
                }

                $packages[] = [
                    'name' => (string) $name,
                    'constraint' => (string) $constraint,
                    'current' => $locked[$name] ?? null,
                    'latest' => null,
                    'status' => 'unknown',
                    'dev' => $isDev,
                    'abandoned' => false,
                ];
            }
        }

        usort($packages, static fn (array $a, array $b): int => [$a['dev'], $a['name']] <=> [$b['dev'], $b['name']]);

        return $packages;
    }

    /** @return array<string, string> package => installed version */
    private function composerLock(Project $project): array
    {
        $lock = $this->json($project, 'composer.lock');

        if ($lock === null) {
            return [];
        }

        $versions = [];

        foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
            if (is_array($package) && is_string($package['name'] ?? null)) {
                $versions[$package['name']] = ltrim((string) ($package['version'] ?? ''), 'v');
            }
        }

        return $versions;
    }

    /** @return array<string, string> package => installed version */
    private function npmLock(Project $project): array
    {
        $lock = $this->json($project, 'package-lock.json');

        if ($lock === null) {
            return [];
        }

        $versions = [];

        // lockfile v2/v3: keys are paths like "node_modules/vite"; v1 nests under "dependencies"
        foreach ($lock['packages'] ?? [] as $path => $entry) {
            if (! is_string($path) || ! str_contains($path, 'node_modules/') || ! is_array($entry)) {
                continue;
            }

            $name = substr($path, strrpos($path, 'node_modules/') + 13);

            $versions[$name] = (string) ($entry['version'] ?? '');
        }

        foreach ($lock['dependencies'] ?? [] as $name => $entry) {
            if (is_string($name) && is_array($entry) && ! isset($versions[$name])) {
                $versions[$name] = (string) ($entry['version'] ?? '');
            }
        }

        return $versions;
    }

    /** @return array<string, mixed>|null */
    private function json(Project $project, string $file): ?array
    {
        $path = $project->absolutePath().'/'.$file;

        if (! $project->exists() || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Ask the registries what the newest version is. Slow by nature, so this only ever runs when
     * somebody asked for it — never on the way to rendering a page.
     *
     * @return array<string, mixed>
     */
    public function check(Project $project): array
    {
        $commands = [];

        if ($this->json($project, 'composer.json') !== null) {
            $commands['composer'] = [
                'composer', 'outdated', '--direct', '--format=json', '--no-interaction',
                '--working-dir='.$project->absolutePath(),
            ];
        }

        if ($this->json($project, 'package.json') !== null) {
            $commands['npm'] = ['npm', 'outdated', '--json', '--prefix', $project->absolutePath()];
            $commands['npmaudit'] = ['npm', 'audit', '--json', '--prefix', $project->absolutePath()];
        }

        if (isset($commands['composer'])) {
            $commands['composeraudit'] = [
                'composer', 'audit', '--format=json', '--no-interaction',
                '--working-dir='.$project->absolutePath(),
            ];
        }

        if ($commands === []) {
            return ['packages' => [], 'advisories' => [], 'at' => Carbon::now()->toIso8601String(), 'error' => null];
        }

        // outputs(), not run(): `npm outdated` exits 1 precisely when it found something
        $raw = Parallel::outputs($commands, self::CHECK_TIMEOUT);

        $result = [
            'packages' => [
                'composer' => $this->readComposer($raw['composer'] ?? ''),
                'npm' => $this->readNpm($raw['npm'] ?? ''),
            ],
            // the half that actually matters: old is a chore, vulnerable is work
            'advisories' => [
                ...$this->readComposerAudit($raw['composeraudit'] ?? ''),
                ...$this->readNpmAudit($raw['npmaudit'] ?? ''),
            ],
            'at' => Carbon::now()->toIso8601String(),
            'error' => null,
        ];

        Cache::put($this->key($project), $result, self::CACHE_SECONDS);

        return $result;
    }

    /** @return array<string, mixed> */
    private function checked(Project $project): array
    {
        $cached = Cache::get($this->key($project));

        return is_array($cached) ? $cached : [];
    }

    public function forget(Project $project): void
    {
        Cache::forget($this->key($project));
    }

    private function key(Project $project): string
    {
        return 'packages.'.$project->getKey();
    }

    /**
     * @return array<string, array<string, mixed>> package => what the registry says
     */
    private function readComposer(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (! is_array($decoded) || ! is_array($decoded['installed'] ?? null)) {
            return [];
        }

        $packages = [];

        foreach ($decoded['installed'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['name'] ?? null)) {
                continue;
            }

            $status = (string) ($entry['latest-status'] ?? '');

            $packages[$entry['name']] = [
                'current' => ltrim((string) ($entry['version'] ?? ''), 'v'),
                'latest' => ltrim((string) ($entry['latest'] ?? ''), 'v'),
                // composer names the shape of the gap: a semver-safe update fits the constraint
                'status' => match (true) {
                    ($entry['abandoned'] ?? false) !== false => 'abandoned',
                    $status === 'semver-safe-update' => 'minor',
                    $status === 'update-possible' => 'major',
                    default => 'current',
                },
                'abandoned' => ($entry['abandoned'] ?? false) !== false,
            ];
        }

        return $packages;
    }

    /** @return array<string, array<string, mixed>> */
    private function readNpm(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (! is_array($decoded)) {
            return [];
        }

        $packages = [];

        foreach ($decoded as $name => $entry) {
            if (! is_string($name) || ! is_array($entry)) {
                continue;
            }

            $current = (string) ($entry['current'] ?? '');
            $wanted = (string) ($entry['wanted'] ?? '');
            $latest = (string) ($entry['latest'] ?? '');

            $packages[$name] = [
                'current' => $current,
                'latest' => $latest,
                /*
                 * npm reports three versions and the gap between them is the information:
                 * behind `wanted` means the range already allows it, behind `latest` only
                 * means a new major is out and the range holds it back.
                 */
                'status' => match (true) {
                    $current === '' => 'missing',
                    $current !== $wanted => 'minor',
                    $wanted !== $latest => 'major',
                    default => 'current',
                },
                'abandoned' => false,
            ];
        }

        return $packages;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $managers
     * @param  array<string, array<string, array<string, mixed>>>  $checked
     * @return array<string, list<array<string, mixed>>>
     */
    private function merge(array $managers, array $checked): array
    {
        foreach ($managers as $manager => $packages) {
            foreach ($packages as $index => $package) {
                $found = $checked[$manager][$package['name']] ?? null;

                if ($found === null) {
                    // checked and not reported means the registry had nothing newer
                    $managers[$manager][$index]['status'] = $checked === [] ? 'unknown' : 'current';

                    continue;
                }

                $managers[$manager][$index] = [
                    ...$package,
                    'current' => $found['current'] !== '' ? $found['current'] : $package['current'],
                    'latest' => $found['latest'],
                    'status' => $found['status'],
                    'abandoned' => $found['abandoned'],
                ];
            }
        }

        return $managers;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $managers
     * @return array<string, int>
     */
    private function counts(array $managers): array
    {
        $counts = ['total' => 0, 'minor' => 0, 'major' => 0, 'abandoned' => 0];

        foreach ($managers as $packages) {
            foreach ($packages as $package) {
                $counts['total']++;

                if (isset($counts[$package['status']])) {
                    $counts[$package['status']]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Security advisories from `composer audit`. Its exit code is 1 when it found something, so
     * the output is read through Parallel::outputs like npm's.
     *
     * @return list<array<string, mixed>>
     */
    private function readComposerAudit(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (! is_array($decoded)) {
            return [];
        }

        $advisories = [];

        foreach ($decoded['advisories'] ?? [] as $package => $entries) {
            foreach (is_array($entries) ? $entries : [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $advisories[] = [
                    'manager' => 'composer',
                    'package' => (string) $package,
                    'title' => (string) ($entry['title'] ?? ''),
                    'severity' => $this->severity((string) ($entry['severity'] ?? '')),
                    'cve' => $entry['cve'] ?? null,
                    'link' => $entry['link'] ?? null,
                    'affected' => (string) ($entry['affectedVersions'] ?? ''),
                    'reported_at' => (string) ($entry['reportedAt'] ?? ''),
                ];
            }
        }

        // abandoned packages are a different kind of risk and composer reports them here too
        foreach ($decoded['abandoned'] ?? [] as $package => $replacement) {
            $advisories[] = [
                'manager' => 'composer',
                'package' => (string) $package,
                'title' => is_string($replacement) && $replacement !== ''
                    ? __('app.packages.abandoned_for', ['replacement' => $replacement])
                    : __('app.packages.status.abandoned'),
                'severity' => 'low',
                'cve' => null,
                'link' => null,
                'affected' => '',
                'reported_at' => '',
            ];
        }

        return $advisories;
    }

    /**
     * npm's audit report. Its shape differs from composer's in a way worth naming: a finding is
     * keyed by package and carries `via`, which is either a string (this package is affected
     * through another) or the advisory object itself.
     *
     * @return list<array<string, mixed>>
     */
    private function readNpmAudit(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (! is_array($decoded) || ! is_array($decoded['vulnerabilities'] ?? null)) {
            return [];
        }

        $advisories = [];

        foreach ($decoded['vulnerabilities'] as $package => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $source = null;

            foreach ($entry['via'] ?? [] as $via) {
                if (is_array($via)) {
                    $source = $via;

                    break;
                }
            }

            $advisories[] = [
                'manager' => 'npm',
                'package' => (string) $package,
                'title' => (string) ($source['title'] ?? __('app.packages.vulnerable_through', [
                    'package' => is_string($entry['via'][0] ?? null) ? $entry['via'][0] : (string) $package,
                ])),
                'severity' => $this->severity((string) ($entry['severity'] ?? '')),
                'cve' => null,
                'link' => $source['url'] ?? null,
                'affected' => (string) ($entry['range'] ?? ''),
                'reported_at' => '',
            ];
        }

        return $advisories;
    }

    /** The two tools grade differently; this is the one scale the page sorts and colours by. */
    private function severity(string $raw): string
    {
        return match (mb_strtolower($raw)) {
            'critical' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            default => 'low',
        };
    }

    /**
     * Mark the packages an advisory names, so a vulnerable package reads as vulnerable in the
     * list and not merely as out of date.
     *
     * @param  array<string, list<array<string, mixed>>>  $managers
     * @param  list<array<string, mixed>>  $advisories
     * @return array<string, list<array<string, mixed>>>
     */
    private function withAdvisories(array $managers, array $advisories): array
    {
        if ($advisories === []) {
            return $managers;
        }

        $worst = [];

        foreach ($advisories as $advisory) {
            $key = $advisory['manager'].'|'.$advisory['package'];
            $rank = self::SEVERITY_ORDER[$advisory['severity']] ?? 0;

            if ($rank >= (self::SEVERITY_ORDER[$worst[$key] ?? 'low'] ?? 0)) {
                $worst[$key] = $advisory['severity'];
            }
        }

        foreach ($managers as $manager => $packages) {
            foreach ($packages as $index => $package) {
                $severity = $worst[$manager.'|'.$package['name']] ?? null;

                if ($severity !== null) {
                    $managers[$manager][$index]['vulnerable'] = $severity;
                }
            }
        }

        return $managers;
    }

    /**
     * Every advisory across every project, worst first. This is the working list — the reason
     * to open this page at all, and the reason it is not a per-project accordion alone.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function advisories(Collection $rows): array
    {
        $all = [];

        foreach ($rows as $row) {
            foreach ($row['advisories'] as $advisory) {
                $all[] = [...$advisory, 'project' => $row['project']];
            }
        }

        usort($all, static fn (array $a, array $b): int => [
            self::SEVERITY_ORDER[$b['severity']] ?? 0, $a['package'],
        ] <=> [
            self::SEVERITY_ORDER[$a['severity']] ?? 0, $b['package'],
        ]);

        return $all;
    }

    /**
     * One line for the whole estate.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function summary(Collection $rows): array
    {
        $total = ['projects' => $rows->count(), 'total' => 0, 'minor' => 0, 'major' => 0, 'vulnerable' => 0, 'unchecked' => 0];

        foreach ($rows as $row) {
            foreach (['total', 'minor', 'major', 'vulnerable'] as $key) {
                $total[$key] += $row['counts'][$key] ?? 0;
            }

            if ($row['checked_at'] === null) {
                $total['unchecked']++;
            }
        }

        return $total;
    }

    /**
     * Packages several projects depend on, where the versions disagree. With four projects
     * sharing a framework this is the list that says where an upgrade actually has to happen —
     * and it exists nowhere else, because each project only ever sees its own lock file.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function shared(Collection $rows): array
    {
        $seen = [];

        foreach ($rows as $row) {
            foreach ($row['managers'] as $manager => $packages) {
                foreach ($packages as $package) {
                    if ($package['current'] === null || $package['current'] === '') {
                        continue;
                    }

                    $seen[$manager.'|'.$package['name']]['manager'] = $manager;
                    $seen[$manager.'|'.$package['name']]['name'] = $package['name'];
                    $seen[$manager.'|'.$package['name']]['versions'][$package['current']][] = $row['project']->name;
                }
            }
        }

        $divergent = [];

        foreach ($seen as $entry) {
            // one project, or one agreed version, says nothing worth a row
            if (count($entry['versions']) < 2) {
                continue;
            }

            krsort($entry['versions'], SORT_NATURAL);

            $divergent[] = [
                'manager' => $entry['manager'],
                'name' => $entry['name'],
                'versions' => $entry['versions'],
                'projects' => array_sum(array_map('count', $entry['versions'])),
            ];
        }

        usort($divergent, static fn (array $a, array $b): int => [$b['projects'], $a['name']] <=> [$a['projects'], $b['name']]);

        return $divergent;
    }

    /**
     * Whether this project really declares this package — the allowlist an update is checked
     * against. A regex alone would accept any well-formed name; this accepts only names the
     * project's own manifest carries, so a crafted request cannot reach an arbitrary package.
     */
    public function declares(Project $project, string $manager, string $package): bool
    {
        if (preg_match(self::NAME_PATTERN, $package) !== 1) {
            return false;
        }

        foreach ($this->declared($project, $manager) as $declared) {
            if ($declared['name'] === $package) {
                return true;
            }
        }

        return false;
    }
}
