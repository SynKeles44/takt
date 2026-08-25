<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\FolderBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The folder list reads the machine's own file system, so the interesting part is where it
 * refuses to look: everything outside the home directory.
 */
class FolderPickerTest extends TestCase
{
    use RefreshDatabase;

    private string $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = (string) getenv('HOME');
    }

    public function test_it_lists_the_folders_below_the_home_directory(): void
    {
        $this->login();

        $response = $this->getJson(route('projects.folders'))->assertOk();

        $response->assertJsonPath('label', '~');
        $response->assertJsonPath('parent', null);
        $response->assertJsonStructure(['path', 'label', 'parent', 'home', 'entries' => [['name', 'path', 'git']]]);
    }

    public function test_a_folder_below_home_reports_its_parent_and_marks_repositories(): void
    {
        $this->login();

        $folder = $this->home.'/takt-folder-test-'.uniqid();
        File::makeDirectory($folder.'/with-git/.git', recursive: true);
        File::makeDirectory($folder.'/plain');

        try {
            $response = $this->getJson(route('projects.folders', ['pfad' => $folder]))->assertOk();

            $entries = collect($response->json('entries'))->keyBy('name');

            $this->assertTrue($entries['with-git']['git']);
            $this->assertFalse($entries['plain']['git']);
            $this->assertSame($this->home, $response->json('parent'));
            $this->assertStringStartsWith('~/takt-folder-test-', $response->json('label'));
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_anything_outside_the_home_directory_falls_back_to_home(): void
    {
        $this->login();

        foreach (['/etc', '/', '/usr/local', $this->home.'/../..'] as $path) {
            $this->getJson(route('projects.folders', ['pfad' => $path]))
                ->assertOk()
                ->assertJsonPath('path', $this->home);
        }
    }

    public function test_files_and_hidden_folders_stay_out_of_the_list(): void
    {
        $this->login();

        $folder = $this->home.'/takt-folder-test-'.uniqid();
        File::makeDirectory($folder.'/.hidden', recursive: true);
        File::makeDirectory($folder.'/visible');
        File::put($folder.'/a-file.txt', 'x');

        try {
            $names = collect($this->getJson(route('projects.folders', ['pfad' => $folder]))->json('entries'))
                ->pluck('name');

            $this->assertSame(['visible'], $names->all());
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_the_list_needs_a_login(): void
    {
        $this->getJson(route('projects.folders'))->assertUnauthorized();
    }

    public function test_the_page_carries_the_dialog_and_the_button_inside_the_field(): void
    {
        $this->login();

        $this->get(route('projects'))
            ->assertOk()
            ->assertSee('data-folder-dialog', escape: false)
            ->assertSee('data-pick-folder', escape: false)
            ->assertSee('field-with-action', escape: false)
            ->assertSee(route('projects.folders'), escape: false)
            ->assertSee(__('app.dev.folder_choose'));
    }

    public function test_the_browser_shortens_and_expands_the_home_path(): void
    {
        $browser = app(FolderBrowser::class);

        $this->assertSame('~', $browser->shorten($this->home));
        $this->assertSame('~/Documents', $browser->shorten($this->home.'/Documents'));
        $this->assertSame($this->home, $browser->resolve('~'));
        $this->assertNull($browser->resolve('/etc'));
        $this->assertNull($browser->resolve($this->home.'/does-not-exist-'.uniqid()));
    }
}
