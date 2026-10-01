<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\TagColor;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every page belongs to exactly one section of the sidebar.
 *
 * A sub-page whose section is not in that section's `match` list leaves the sidebar dark: no
 * highlight, no marker, nothing saying where you are. Five pages were in that state — packages, a
 * ticket, an entry being edited, the absences and the checklist templates — and each one was added
 * long after the list it belonged in. Asking every page is the only version of this check that
 * does not go stale the next time a page is added.
 */
class SidebarSectionTest extends TestCase
{
    use RefreshDatabase;

    /** The two pages that live in the account menu rather than in a section. */
    private const array OUTSIDE = ['settings', 'trash'];

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Process::preventStrayProcesses();

        $this->login();
    }

    public function test_every_page_lights_exactly_one_section(): void
    {
        $this->seedContent();

        foreach ($this->pages() as $name => $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $lit = substr_count($html, 'nav-item nav-item-active');

            $this->assertSame(
                in_array($name, self::OUTSIDE, true) ? 0 : 1,
                $lit,
                "{$name} ({$url}) lights {$lit} sections in the sidebar",
            );
        }
    }

    public function test_a_lit_section_carries_the_marker(): void
    {
        $this->assertStringContainsString(
            'class="nav-marker"',
            (string) $this->get(route('dashboard'))->assertOk()->getContent(),
        );
    }

    /** @return Collection<string, string> */
    private function pages(): Collection
    {
        // downloads, JSON endpoints and the print documents carry no sidebar at all
        $skip = [
            'calendar.export', 'backup', 'settings.export', 'logout', 'search',
            'month.csv', 'month.timesheet', 'projects.folders', 'dev.reviews.sections',
            'docker.list', 'docker.logs', 'insights.report',
        ];

        return collect(Route::getRoutes()->getRoutesByMethod()['GET'] ?? [])
            ->filter(fn (\Illuminate\Routing\Route $route): bool => $route->getName() !== null)
            ->reject(fn (\Illuminate\Routing\Route $route): bool => in_array($route->getName(), $skip, true))
            ->reject(fn (\Illuminate\Routing\Route $route): bool => in_array('guest', $route->gatherMiddleware(), true))
            ->mapWithKeys(fn (\Illuminate\Routing\Route $route): array => [
                $route->getName() => $this->url($route),
            ])
            ->filter();
    }

    /** Routes with a parameter need a real record behind them, or they 404 instead of answering. */
    private function url(\Illuminate\Routing\Route $route): ?string
    {
        $name = $route->getName();

        return match (true) {
            ! str_contains($route->uri(), '{') => '/'.ltrim($route->uri(), '/'),
            $name === 'tickets.show' => route('tickets.show', 'TAKT-1', false),
            $name === 'todos.show' => route('todos.show', 1, false),
            $name === 'todos.edit' => route('todos.edit', 1, false),
            $name === 'entries.edit' => route('entries.edit', 1, false),
            $name === 'commands.show' => null,
            default => null,
        };
    }

    private function seedContent(): void
    {
        Tag::query()->create(['name' => 'Backend', 'color' => TagColor::Accent]);
        Todo::query()->create(['title' => 'Migration prüfen', 'due_at' => now()->addDay()]);
        Ticket::query()->create(['key' => 'TAKT-1', 'source' => 'local', 'title' => 'Lokal', 'position' => 0]);
        Project::query()->create(['name' => 'Takt', 'path' => base_path(), 'position' => 0]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => now()->startOfDay()->addHours(9),
            'ended_at' => now()->startOfDay()->addHours(12),
        ]);
    }
}
