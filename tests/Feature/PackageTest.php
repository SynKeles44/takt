<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommandRun;
use App\Models\Project;
use App\Services\Packages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Dependencies per project. The manifests are read from disk — no network, no vendor directory —
 * and the registry answer is a separate, cached step.
 */
class PackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Nothing here may start a real process. An update run is detached by design, so a test
         * that let it through would leave a composer job running on the machine after the suite
         * finished — and would update a temp directory nobody asked it to touch.
         */
        Process::fake();

        $this->login(['email' => 'dev@example.test']);
    }

    /** A project directory carrying both ecosystems, with lock files that pin real versions. */
    private function project(array $files = []): Project
    {
        $path = sys_get_temp_dir().'/takt-packages-'.bin2hex(random_bytes(4));

        mkdir($path, 0o755, true);

        $defaults = [
            'composer.json' => [
                'require' => ['php' => '^8.3', 'ext-json' => '*', 'laravel/framework' => '^13.0'],
                'require-dev' => ['phpunit/phpunit' => '^12.0'],
            ],
            'composer.lock' => [
                'packages' => [['name' => 'laravel/framework', 'version' => 'v13.26.1']],
                'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '12.5.33']],
            ],
            'package.json' => [
                'devDependencies' => ['vite' => '^8.0.0'],
            ],
            'package-lock.json' => [
                'packages' => ['node_modules/vite' => ['version' => '8.2.2']],
            ],
        ];

        foreach ([...$defaults, ...$files] as $name => $content) {
            file_put_contents($path.'/'.$name, json_encode($content));
        }

        return Project::query()->create(['name' => 'Testprojekt', 'path' => $path]);
    }

    private function remove(Project $project): void
    {
        $path = $project->absolutePath();

        // not Process::run: processes are faked in this suite, so a faked rm removes nothing
        foreach (glob($path.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($path)) {
            rmdir($path);
        }
    }

    public function test_the_manifests_are_read_without_network_or_vendor_directory(): void
    {
        $project = $this->project();

        $row = app(Packages::class)->forProject($project);

        $composer = collect($row['managers']['composer'])->keyBy('name');
        $npm = collect($row['managers']['npm'])->keyBy('name');

        $this->assertSame('13.26.1', $composer['laravel/framework']['current']);
        $this->assertSame('12.5.33', $composer['phpunit/phpunit']['current']);
        $this->assertTrue($composer['phpunit/phpunit']['dev']);
        $this->assertSame('8.2.2', $npm['vite']['current']);

        // php and ext-* are platform requirements, not packages anybody can update
        $this->assertFalse($composer->has('php'));
        $this->assertFalse($composer->has('ext-json'));

        $this->remove($project);
    }

    public function test_a_project_without_manifests_is_left_out_of_the_overview(): void
    {
        $path = sys_get_temp_dir().'/takt-empty-'.bin2hex(random_bytes(4));
        mkdir($path, 0o755, true);

        Project::query()->create(['name' => 'Leer', 'path' => $path]);

        $this->assertCount(0, app(Packages::class)->overview());

        rmdir($path);
    }

    public function test_the_registry_answer_is_merged_onto_the_manifest(): void
    {
        $project = $this->project();

        /*
         * The shapes below are what the two tools actually print — checked against a real run.
         * npm's three versions carry the meaning: behind `wanted` is inside the range, behind
         * only `latest` means a new major the range holds back.
         */
        cache()->put('packages.'.$project->getKey(), [
            'packages' => [
                'composer' => [
                    'laravel/framework' => ['current' => '13.26.1', 'latest' => '13.34.0', 'status' => 'minor', 'abandoned' => false],
                    'phpunit/phpunit' => ['current' => '12.5.33', 'latest' => '13.3.6', 'status' => 'major', 'abandoned' => false],
                ],
                'npm' => [
                    'vite' => ['current' => '8.2.2', 'latest' => '8.3.1', 'status' => 'minor', 'abandoned' => false],
                ],
            ],
            'at' => now()->toIso8601String(),
            'error' => null,
        ], 600);

        $row = app(Packages::class)->forProject($project);
        $composer = collect($row['managers']['composer'])->keyBy('name');

        $this->assertSame('13.34.0', $composer['laravel/framework']['latest']);
        $this->assertSame('major', $composer['phpunit/phpunit']['status']);
        $this->assertSame(2, $row['counts']['minor']);
        $this->assertSame(1, $row['counts']['major']);

        $this->remove($project);
    }

    public function test_an_update_is_refused_for_a_package_the_project_does_not_declare(): void
    {
        $project = $this->project();

        $this->post(route('packages.update', $project), [
            'manager' => 'composer',
            'package' => 'evil/backdoor',
        ])->assertRedirect();

        // the allowlist is the manifest itself, so no run was ever started
        $this->assertSame(0, CommandRun::query()->count());

        $this->remove($project);
    }

    public function test_a_shell_metacharacter_never_reaches_a_command(): void
    {
        $project = $this->project();

        foreach (['laravel/framework; rm -rf /', '$(whoami)', 'vite && curl evil.test'] as $attempt) {
            $this->post(route('packages.update', $project), [
                'manager' => 'composer',
                'package' => $attempt,
            ]);
        }

        $this->assertSame(0, CommandRun::query()->count());

        $this->remove($project);
    }

    public function test_a_declared_package_starts_a_run_that_names_the_real_command(): void
    {
        $project = $this->project();

        $this->post(route('packages.update', $project), [
            'manager' => 'composer',
            'package' => 'laravel/framework',
        ])->assertRedirect();

        $run = CommandRun::query()->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('composer', $run->kind);
        $this->assertSame('laravel/framework', $run->target);
        $this->assertSame('composer update laravel/framework', $run->command());
        $this->assertTrue($run->isPackageUpdate());

        // a make run keeps its own wording
        $this->assertSame('make build', (new CommandRun(['kind' => 'make', 'target' => 'build']))->command());

        $this->remove($project);
    }

    /** The shape `composer audit --format=json` really prints, checked against a live run. */
    private function composerAudit(): array
    {
        return [
            'advisories' => [
                'league/commonmark' => [
                    [
                        'advisoryId' => 'PKSA-1',
                        'packageName' => 'league/commonmark',
                        'affectedVersions' => '>=2.0.0,<=2.10.1',
                        'title' => 'Quadratic-time denial of service',
                        'cve' => null,
                        'link' => 'https://github.com/advisories/GHSA-x',
                        'reportedAt' => '2026-09-30T15:36:00+00:00',
                        'severity' => 'high',
                    ],
                ],
                'laravel/framework' => [
                    [
                        'advisoryId' => 'PKSA-2',
                        'packageName' => 'laravel/framework',
                        'affectedVersions' => '>=13.0.0,<13.30.0',
                        'title' => 'XSS in Debug Page Information',
                        'cve' => 'CVE-2026-102279',
                        'link' => 'https://github.com/advisories/GHSA-y',
                        'reportedAt' => '2026-09-29T18:24:25+00:00',
                        'severity' => 'low',
                    ],
                ],
            ],
            'abandoned' => ['old/package' => 'new/package'],
        ];
    }

    public function test_advisories_are_read_and_sorted_worst_first(): void
    {
        $project = $this->project();

        cache()->put('packages.'.$project->getKey(), [
            'packages' => [],
            'advisories' => (function (): array {
                $service = new \ReflectionMethod(Packages::class, 'readComposerAudit');

                return $service->invoke(app(Packages::class), json_encode($this->composerAudit()));
            })(),
            'at' => now()->toIso8601String(),
            'error' => null,
        ], 600);

        $rows = app(Packages::class)->overview();
        $advisories = app(Packages::class)->advisories($rows);

        // high before low, and the abandoned package rides along as its own finding
        $this->assertSame('high', $advisories[0]['severity']);
        $this->assertSame('league/commonmark', $advisories[0]['package']);
        $this->assertSame('CVE-2026-102279', collect($advisories)->firstWhere('package', 'laravel/framework')['cve']);
        $this->assertNotNull(collect($advisories)->firstWhere('package', 'old/package'));

        // and the count reaches the project summary
        $this->assertSame(3, app(Packages::class)->summary($rows)['vulnerable']);

        $this->remove($project);
    }

    public function test_npm_audit_severity_moderate_is_mapped_to_the_one_scale(): void
    {
        $read = new \ReflectionMethod(Packages::class, 'readNpmAudit');

        $advisories = $read->invoke(app(Packages::class), json_encode([
            'vulnerabilities' => [
                'esbuild' => [
                    'severity' => 'moderate',
                    'range' => '<=0.24.2',
                    'via' => [['title' => 'esbuild enables any website to send requests', 'url' => 'https://gh.test/a']],
                ],
                'vite' => ['severity' => 'high', 'range' => '<=6.1.0', 'via' => ['esbuild']],
            ],
        ]));

        $this->assertSame('medium', collect($advisories)->firstWhere('package', 'esbuild')['severity']);
        $this->assertSame('https://gh.test/a', collect($advisories)->firstWhere('package', 'esbuild')['link']);

        // a package vulnerable only through another still has to say something readable
        $this->assertNotSame('', collect($advisories)->firstWhere('package', 'vite')['title']);
    }

    public function test_packages_shared_across_projects_with_different_versions_are_surfaced(): void
    {
        $first = $this->project();
        $second = $this->project([
            'composer.lock' => [
                'packages' => [['name' => 'laravel/framework', 'version' => 'v12.4.0']],
                'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '12.5.33']],
            ],
        ]);

        $shared = app(Packages::class)->shared(app(Packages::class)->overview());
        $names = array_column($shared, 'name');

        // the framework disagrees between the two projects; phpunit does not
        $this->assertContains('laravel/framework', $names);
        $this->assertNotContains('phpunit/phpunit', $names);

        $entry = collect($shared)->firstWhere('name', 'laravel/framework');
        $this->assertSame(['13.26.1', '12.4.0'], array_keys($entry['versions']));

        $this->remove($first);
        $this->remove($second);
    }

    public function test_every_section_starts_collapsed(): void
    {
        $project = $this->project();

        $html = $this->page(route('packages'))->assertOk()->getContent();

        /*
         * Nothing on this page opens itself — not a project, not the shared-versions block, and
         * not after a check. The summary line is what most visits need, and a card that opens
         * because the page decided so is the page choosing what to look at.
         */
        $this->assertStringNotContainsString('<details open', $html);
        $this->assertStringContainsString('<details>', $html);

        $this->remove($project);
    }

    public function test_the_page_lists_every_project_that_declares_something(): void
    {
        $project = $this->project();

        $this->page(route('packages'))
            ->assertOk()
            ->assertSee('Testprojekt')
            ->assertSee('laravel/framework')
            ->assertSee('vite');

        $this->remove($project);
    }
}
