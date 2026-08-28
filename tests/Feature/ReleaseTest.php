<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Services\Releases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
    }

    /** A throwaway repository with two tags, so the reader works on real git output. */
    private function repository(): string
    {
        $path = sys_get_temp_dir().'/takt-tags-'.bin2hex(random_bytes(4));

        mkdir($path, 0o755, true);
        file_put_contents($path.'/README.md', "hello\n");

        foreach ([
            ['git', 'init', '-q', '-b', 'main'],
            ['git', 'config', 'user.email', 'dev@example.test'],
            ['git', 'config', 'user.name', 'Dev'],
            ['git', 'add', '-A'],
            ['git', 'commit', '-q', '-m', 'Erster Stand'],
            ['git', 'tag', '-a', 'release-1.0.0', '-m', 'Erstes Release'],
            ['git', 'commit', '-q', '--allow-empty', '-m', 'Weiter'],
            ['git', 'tag', '-a', 'release-1.1.0', '-m', 'Zweites Release'],
        ] as $command) {
            Process::path($path)->run($command)->throw();
        }

        return $path;
    }

    public function test_tags_come_back_newest_first_with_their_message(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $groups = app(Releases::class)->forProjects();
        $releases = $groups->first()['releases'];

        $this->assertCount(2, $releases);
        $this->assertSame('release-1.1.0', $releases[0]['tag']);
        $this->assertSame('Zweites Release', $releases[0]['subject']);
        $this->assertSame('release-1.0.0', $releases[1]['tag']);
        $this->assertTrue($releases[0]['at']->greaterThanOrEqualTo($releases[1]['at']));

        Process::run(['rm', '-rf', $path]);
    }

    public function test_a_project_without_tags_says_so_instead_of_failing(): void
    {
        Project::query()->create(['name' => 'Leer', 'path' => sys_get_temp_dir()]);

        $groups = app(Releases::class)->forProjects();

        $this->assertSame([], $groups->first()['releases']);
        $this->assertSame(0, app(Releases::class)->count($groups));
    }

    public function test_a_missing_folder_is_reported_as_an_error(): void
    {
        Project::query()->create(['name' => 'Weg', 'path' => '/tmp/takt-does-not-exist-'.bin2hex(random_bytes(3))]);

        $this->assertNotNull(app(Releases::class)->forProjects()->first()['error']);
    }

    public function test_the_page_lists_the_releases_and_carries_the_tab(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $this->get(route('releases'))
            ->assertOk()
            ->assertSee(__('app.dev.releases_intro'))
            ->assertSee('release-1.1.0')
            ->assertSee('Zweites Release')
            ->assertSee(route('releases'), escape: false);

        Process::run(['rm', '-rf', $path]);
    }

    public function test_no_projects_at_all_says_so(): void
    {
        $this->get(route('releases'))->assertOk()->assertSee(__('app.dev.releases_none'));
    }
}
