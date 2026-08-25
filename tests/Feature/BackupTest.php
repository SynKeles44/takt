<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\Absence;
use App\Models\DayNote;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-20 18:00:00');
    }

    private function payload(): array
    {
        return [
            'version' => 1,
            'tags' => [
                ['name' => 'Kunde', 'color' => 'accent', 'warn_lead_minutes' => 60, 'auto_complete_expired' => true],
            ],
            'time_entries' => [
                ['type' => 'work', 'started_at' => '2026-08-19 09:00:00', 'ended_at' => '2026-08-19 17:00:00', 'note' => 'Angebot'],
            ],
            'todos' => [
                [
                    'title' => 'Angebot senden',
                    'body' => 'an Musterfirma',
                    'due_at' => '2026-08-21 12:00:00',
                    'due_has_time' => true,
                    'recurrence' => 'none',
                    'completed_at' => null,
                    'tags' => ['Kunde'],
                    'steps' => [['title' => 'Preise prüfen', 'completed_at' => null]],
                ],
            ],
            'absences' => [
                ['type' => 'vacation', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-05', 'note' => 'Sommer'],
            ],
            'day_notes' => [
                ['day' => '2026-08-19', 'body' => 'Guter Tag'],
            ],
        ];
    }

    private function upload(array $payload): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('backup.json', (string) json_encode($payload));
    }

    public function test_the_export_contains_every_own_record(): void
    {
        $this->login();

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-19 09:00:00',
            'ended_at' => '2026-08-19 17:00:00',
        ]);
        Todo::query()->create(['title' => 'Angebot senden']);
        Absence::query()->create(['type' => 'vacation', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-05']);
        DayNote::query()->create(['day' => '2026-08-19', 'body' => 'Guter Tag']);

        $payload = $this->get(route('backup'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="takt-backup-2026-08-20.json"')
            ->json();

        $this->assertCount(1, $payload['time_entries']);
        $this->assertSame('Angebot senden', $payload['todos'][0]['title']);
        $this->assertSame('2026-09-05', $payload['absences'][0]['ends_on']);
        $this->assertSame('Guter Tag', $payload['day_notes'][0]['body']);
    }

    public function test_a_backup_can_be_restored_into_an_empty_account(): void
    {
        $this->login();

        $this->post(route('backup.restore'), ['backup' => $this->upload($this->payload())])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('time_entries', 1);
        $this->assertDatabaseCount('absences', 1);
        $this->assertDatabaseCount('day_notes', 1);
        $this->assertDatabaseHas('tags', ['name' => 'Kunde', 'warn_lead_minutes' => 60]);

        $todo = Todo::query()->firstOrFail();

        $this->assertSame('an Musterfirma', $todo->body);
        $this->assertSame('Kunde', $todo->tags->first()->name);
        $this->assertSame('Preise prüfen', $todo->steps->first()->title);
    }

    public function test_restoring_twice_skips_everything_the_second_time(): void
    {
        $this->login();

        $this->post(route('backup.restore'), ['backup' => $this->upload($this->payload())]);
        $this->post(route('backup.restore'), ['backup' => $this->upload($this->payload())])
            ->assertSessionHas('status', '0 Einträge ergänzt, 5 übersprungen.');

        $this->assertDatabaseCount('time_entries', 1);
        $this->assertDatabaseCount('todos', 1);
        $this->assertDatabaseCount('tags', 1);
        $this->assertDatabaseCount('absences', 1);
        $this->assertDatabaseCount('day_notes', 1);
    }

    public function test_a_restore_never_touches_another_account(): void
    {
        $other = User::factory()->create();

        $this->login();
        $this->post(route('backup.restore'), ['backup' => $this->upload($this->payload())]);

        $this->assertDatabaseMissing('todos', ['user_id' => $other->id]);
        $this->assertSame(0, $other->tags()->count());
    }

    public function test_a_broken_file_is_rejected(): void
    {
        $this->login();

        $this->post(route('backup.restore'), [
            'backup' => UploadedFile::fake()->createWithContent('backup.json', 'not json'),
        ])->assertSessionHasErrors('backup');

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_the_scheduled_command_writes_one_file_per_account(): void
    {
        Storage::fake('local');

        $first = User::factory()->create(['email' => 'a@example.test']);
        User::factory()->create(['email' => 'b@example.test']);

        $this->actingAs($first);
        Todo::query()->create(['title' => 'Nur bei A']);

        $this->artisan('takt:backup')->assertSuccessful();

        $files = collect(Storage::disk('local')->allFiles('backups'));

        $this->assertCount(2, $files);

        $own = $files->first(fn (string $path): bool => str_contains($path, (string) $first->id));

        $this->assertStringContainsString('Nur bei A', Storage::disk('local')->get($own));
    }

    public function test_the_command_can_target_a_single_account(): void
    {
        Storage::fake('local');

        User::factory()->create(['email' => 'a@example.test']);
        User::factory()->create(['email' => 'b@example.test']);

        $this->artisan('takt:backup', ['--user' => 'b@example.test'])->assertSuccessful();

        $this->assertCount(1, Storage::disk('local')->allFiles('backups'));

        $this->artisan('takt:backup', ['--user' => 'nobody@example.test'])->assertFailed();
    }
}
