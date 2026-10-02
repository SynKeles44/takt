<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DataDirectory;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DataDirectoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/data-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        putenv(DataDirectory::ENV);

        parent::tearDown();
    }

    public function test_without_the_variable_there_is_no_data_folder(): void
    {
        putenv(DataDirectory::ENV);
        $this->assertNull(DataDirectory::fromEnvironment());

        putenv(DataDirectory::ENV.'=   ');
        $this->assertNull(DataDirectory::fromEnvironment());
    }

    public function test_the_variable_names_the_folder_and_its_parts(): void
    {
        putenv(DataDirectory::ENV.'='.$this->root.'/');

        $data = DataDirectory::fromEnvironment();

        $this->assertNotNull($data);
        $this->assertSame($this->root, $data->path);
        $this->assertSame($this->root.'/storage', $data->storage());
        $this->assertSame($this->root.'/database', $data->database());
        $this->assertSame($this->root.'/.env', $data->environmentFile());
        $this->assertSame($this->root.'/cache', $data->cache());
    }

    /** Without the passthrough, `artisan serve` strips the variable and the server writes into the bundle. */
    public function test_the_development_server_hands_the_data_folder_on(): void
    {
        $this->assertContains(DataDirectory::ENV, ServeCommand::$passthroughVariables);
        $this->assertContains('PHPRC', ServeCommand::$passthroughVariables);
    }

    public function test_prepare_builds_the_storage_tree_and_an_empty_database_once(): void
    {
        $data = new DataDirectory($this->root);

        $data->prepare();
        $data->prepare();

        foreach (['storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs', 'storage/app/private', 'storage/app/backups', 'database', 'cache'] as $folder) {
            $this->assertDirectoryExists($this->root.'/'.$folder);
        }

        $this->assertFileExists($this->root.'/database/database.sqlite');
        $this->assertSame('', file_get_contents($this->root.'/database/database.sqlite'));
    }
}
