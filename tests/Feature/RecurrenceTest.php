<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Recurrence;
use App\Enums\TagColor;
use App\Models\Tag;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 12:00:00');

        $this->login();
    }

    public function test_each_rule_advances_to_the_expected_date(): void
    {
        $cases = [
            [Recurrence::Daily, '2026-08-24 09:00', '2026-08-25 09:00'],
            [Recurrence::Weekly, '2026-08-24 09:00', '2026-08-31 09:00'],
            [Recurrence::Biweekly, '2026-08-24 09:00', '2026-09-07 09:00'],
            [Recurrence::Monthly, '2026-08-31 09:00', '2026-09-30 09:00'],
            [Recurrence::Yearly, '2026-08-24 09:00', '2027-08-24 09:00'],
            [Recurrence::Weekdays, '2026-08-28 09:00', '2026-08-31 09:00'],
        ];

        foreach ($cases as [$rule, $from, $expected]) {
            $next = $rule->next(Carbon::parse($from));

            $this->assertSame($expected, $next->format('Y-m-d H:i'), $rule->value.' misses');
        }
    }

    public function test_a_long_overdue_series_jumps_past_today(): void
    {
        $next = Recurrence::Weekly->next(Carbon::parse('2026-06-01 08:00'));

        $this->assertTrue($next->isFuture());
        $this->assertSame(1, $next->isoWeekday());
    }

    public function test_none_never_produces_a_follower(): void
    {
        $this->assertNull(Recurrence::None->next(Carbon::parse('2026-08-24 09:00')));
    }

    public function test_completing_a_repeating_task_creates_the_next_one(): void
    {
        $tag = Tag::query()->create(['name' => 'Routine', 'color' => TagColor::Work]);

        $todo = Todo::query()->create([
            'title' => 'Wochenbericht',
            'body' => 'Zahlen zusammenstellen',
            'due_at' => '2026-08-24 17:00',
            'due_has_time' => true,
            'recurrence' => Recurrence::Weekly,
        ]);
        $todo->tags()->attach($tag);
        $todo->steps()->create(['title' => 'Zahlen holen', 'position' => 1]);

        $this->patch(route('todos.toggle', $todo))
            ->assertRedirect()
            ->assertSessionHas('status', __('app.flash.todo_repeated', ['date' => 'Mo, 31. Aug']));

        $this->assertTrue($todo->refresh()->isDone());

        $follower = Todo::query()->open()->sole();

        $this->assertSame('Wochenbericht', $follower->title);
        $this->assertSame('Zahlen zusammenstellen', $follower->body);
        $this->assertSame('2026-08-31 17:00:00', $follower->due_at->toDateTimeString());
        $this->assertTrue($follower->due_has_time);
        $this->assertSame(Recurrence::Weekly, $follower->recurrence);
        $this->assertSame(['Routine'], $follower->tags->pluck('name')->all());
        $this->assertSame(['Zahlen holen'], $follower->steps->pluck('title')->all());
        $this->assertFalse($follower->steps->first()->isDone());
    }

    public function test_a_repeating_task_without_a_date_does_not_multiply(): void
    {
        $todo = Todo::query()->create(['title' => 'Ohne Termin', 'recurrence' => Recurrence::Daily]);

        $this->patch(route('todos.toggle', $todo));

        $this->assertSame(1, Todo::query()->count());
    }

    public function test_reopening_does_not_create_another_occurrence(): void
    {
        $todo = Todo::query()->create([
            'title' => 'Täglich',
            'due_at' => '2026-08-24 09:00',
            'due_has_time' => true,
            'recurrence' => Recurrence::Daily,
        ]);

        $this->patch(route('todos.toggle', $todo));
        $this->assertSame(2, Todo::query()->count());

        $this->patch(route('todos.toggle', $todo));
        $this->assertSame(2, Todo::query()->count());
        $this->assertFalse($todo->refresh()->isDone());
    }

    public function test_the_rule_is_saved_from_the_form_and_shown_on_the_row(): void
    {
        $this->post(route('todos.store'), [
            'title' => 'Monatsabschluss',
            'due_date' => '2026-08-31',
            'recurrence' => Recurrence::Monthly->value,
        ])->assertSessionHasNoErrors();

        $todo = Todo::query()->sole();

        $this->assertSame(Recurrence::Monthly, $todo->recurrence);

        $this->get(route('todos.index'))->assertOk()->assertSee(Recurrence::Monthly->label());
    }

    public function test_an_unknown_rule_is_rejected(): void
    {
        $this->post(route('todos.store'), ['title' => 'Test', 'recurrence' => 'hourly'])
            ->assertSessionHasErrors('recurrence');

        $this->assertSame(0, Todo::query()->count());
    }
}
