<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\TodoStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TodoDetailTest extends TestCase
{
    use RefreshDatabase;

    private Todo $todo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 12:00:00');
        Storage::fake('local');

        $this->login();

        $this->todo = Todo::query()->create(['title' => 'Angebot bauen']);
    }

    public function test_steps_are_added_ticked_and_removed(): void
    {
        $this->post(route('steps.store', $this->todo), ['title' => '  Positionen sammeln  '])
            ->assertRedirect()
            ->assertSessionHas('status', __('app.flash.step_created'));

        $step = $this->todo->steps()->sole();

        $this->assertSame('Positionen sammeln', $step->title);
        $this->assertSame(1, $step->position);
        $this->assertFalse($step->isDone());

        $this->patch(route('steps.toggle', [$this->todo, $step]));
        $this->assertTrue($step->refresh()->isDone());

        $this->patch(route('steps.toggle', [$this->todo, $step]));
        $this->assertFalse($step->refresh()->isDone());

        $this->delete(route('steps.destroy', [$this->todo, $step]));
        $this->assertSame(0, $this->todo->steps()->count());
    }

    public function test_the_progress_is_reported(): void
    {
        $this->post(route('steps.store', $this->todo), ['title' => 'Eins']);
        $this->post(route('steps.store', $this->todo), ['title' => 'Zwei']);
        $this->post(route('steps.store', $this->todo), ['title' => 'Drei']);

        $step = $this->todo->steps()->first();
        $this->patch(route('steps.toggle', [$this->todo, $step]));

        $progress = $this->todo->load('steps')->stepProgress();

        $this->assertSame(['done' => 1, 'total' => 3, 'percent' => 33], $progress);

        $this->get(route('todos.index'))->assertOk()->assertSee('1/3');
    }

    public function test_an_empty_step_is_rejected(): void
    {
        $this->post(route('steps.store', $this->todo), ['title' => ''])->assertSessionHasErrors('title');

        $this->assertSame(0, $this->todo->steps()->count());
    }

    public function test_steps_of_another_users_task_are_unreachable(): void
    {
        $foreign = Todo::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'title' => 'Fremd',
        ]);
        $foreignStep = $foreign->steps()->create(['title' => 'Fremder Schritt', 'position' => 1]);

        $this->post(route('steps.store', $foreign), ['title' => 'Eindringling'])->assertNotFound();
        $this->patch(route('steps.toggle', [$foreign, $foreignStep]))->assertNotFound();
        $this->delete(route('steps.destroy', [$foreign, $foreignStep]))->assertNotFound();

        $this->assertFalse($foreignStep->refresh()->isDone());
    }

    public function test_a_step_cannot_be_addressed_through_a_foreign_task(): void
    {
        $other = Todo::query()->create(['title' => 'Andere Aufgabe']);
        $step = $other->steps()->create(['title' => 'Gehört zu andere', 'position' => 1]);

        $this->patch(route('steps.toggle', [$this->todo, $step]))->assertNotFound();

        $this->assertFalse($step->refresh()->isDone());
    }

    public function test_an_attachment_is_stored_listed_downloaded_and_deleted(): void
    {
        $this->post(route('attachments.store', $this->todo), [
            'file' => UploadedFile::fake()->create('angebot.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHas('status', __('app.flash.attachment_added'));

        $attachment = $this->todo->attachments()->sole();

        $this->assertSame('angebot.pdf', $attachment->name);
        $this->assertStringStartsWith('todos/'.$this->todo->getKey().'/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $this->get(route('todos.show', $this->todo))->assertOk()->assertSee('angebot.pdf');

        $this->get(route('attachments.show', [$this->todo, $attachment]))
            ->assertOk()
            ->assertDownload('angebot.pdf');

        $this->delete(route('attachments.destroy', [$this->todo, $attachment]))->assertRedirect();

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertSame(0, $this->todo->attachments()->count());
    }

    public function test_oversized_and_forbidden_files_are_rejected(): void
    {
        $this->post(route('attachments.store', $this->todo), [
            'file' => UploadedFile::fake()->create('riesig.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->post(route('attachments.store', $this->todo), [
            'file' => UploadedFile::fake()->create('skript.php', 10, 'text/x-php'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, $this->todo->attachments()->count());
    }

    public function test_another_users_attachment_is_not_served(): void
    {
        $foreign = Todo::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'title' => 'Fremd',
        ]);

        $attachment = $foreign->attachments()->create([
            'name' => 'geheim.pdf',
            'path' => 'todos/'.$foreign->getKey().'/geheim.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
        ]);

        Storage::disk('local')->put($attachment->path, 'inhalt');

        $this->get(route('attachments.show', [$foreign, $attachment]))->assertNotFound();
        $this->delete(route('attachments.destroy', [$foreign, $attachment]))->assertNotFound();

        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_a_deleted_task_keeps_its_parts_until_it_is_purged(): void
    {
        $this->post(route('steps.store', $this->todo), ['title' => 'Schritt']);
        $this->post(route('attachments.store', $this->todo), [
            'file' => UploadedFile::fake()->create('datei.txt', 5, 'text/plain'),
        ]);

        $this->delete(route('todos.destroy', $this->todo))
            ->assertRedirect()
            ->assertSessionHas('undo');

        $this->assertSoftDeleted($this->todo);
        $this->assertSame(1, TodoStep::query()->count());
        $this->assertSame(1, TodoAttachment::query()->count());

        $this->patch(route('trash.todo.restore', $this->todo))->assertRedirect();
        $this->assertNotSoftDeleted($this->todo);

        $this->delete(route('todos.destroy', $this->todo));
        $this->delete(route('trash.todo.purge', $this->todo))->assertRedirect();

        $this->assertSame(0, TodoStep::query()->count());
        $this->assertSame(0, TodoAttachment::query()->count());
        $this->assertSame(0, Todo::query()->withTrashed()->count());
    }
}
