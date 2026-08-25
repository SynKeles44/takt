<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\TagColor;
use App\Enums\Widget;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every page the app offers, called once with real content in the database.
 * A view that breaks on a detail — a missing variable, a renamed key — shows up here.
 */
class EveryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_answers_with_a_complete_document(): void
    {
        $this->login();
        $this->seedContent();

        foreach ($this->pages() as $name => $url) {
            $response = $this->get($url);

            $this->assertSame(200, $response->status(), "GET {$name} ({$url}) failed");
            $content = (string) $response->getContent();

            $this->assertStringContainsString('</html>', $content, "{$name} is truncated");
            $this->assertStringNotContainsString('app.', strip_tags($content), "{$name} shows a raw translation key");
        }
    }

    public function test_no_page_still_carries_the_old_name(): void
    {
        $this->login();
        $this->seedContent();

        foreach ($this->pages() as $name => $url) {
            $this->assertStringNotContainsStringIgnoringCase(
                'werkbank',
                (string) $this->get($url)->getContent(),
                "{$name} still mentions the old name",
            );
        }
    }

    /** @return Collection<string, string> route name => url */
    private function pages(): Collection
    {
        // downloads and JSON endpoints are not pages
        $skip = [
            'calendar.export', 'backup', 'settings.export', 'logout', 'search',
            'month.csv', 'month.timesheet', 'projects.folders', 'dev.reviews.sections',
            'docker.list', 'docker.logs',
        ];

        return collect(Route::getRoutes()->getRoutesByMethod()['GET'] ?? [])
            ->filter(fn (\Illuminate\Routing\Route $route): bool => $route->getName() !== null)
            ->reject(fn (\Illuminate\Routing\Route $route): bool => in_array($route->getName(), $skip, true))
            ->reject(fn (\Illuminate\Routing\Route $route): bool => str_contains($route->uri(), '{'))
            ->reject(fn (\Illuminate\Routing\Route $route): bool => in_array('guest', $route->gatherMiddleware(), true))
            ->mapWithKeys(fn (\Illuminate\Routing\Route $route): array => [$route->getName() => '/'.ltrim($route->uri(), '/')]);
    }

    /** Enough content that every list, chart and widget has something to draw. */
    private function seedContent(): void
    {
        $tag = Tag::query()->create(['name' => 'Backend', 'color' => TagColor::Accent]);

        $todo = Todo::query()->create(['title' => 'Migration prüfen', 'due_at' => now()->addDay()]);
        $todo->tags()->attach($tag);
        $todo->steps()->create(['title' => 'Schema lesen', 'position' => 0]);

        Todo::query()->create(['title' => 'Erledigt', 'completed_at' => now()]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => now()->startOfDay()->addHours(9),
            'ended_at' => now()->startOfDay()->addHours(12),
        ]);

        Project::query()->create([
            'name' => 'Takt',
            'path' => base_path(),
            'repository' => 'example/takt',
            'start_command' => 'make start',
            'position' => 0,
        ]);

        Snippet::query()->create(['title' => 'Tests', 'body' => 'php artisan test', 'position' => 0]);

        // every widget on one dashboard, so the dashboard page draws them all
        foreach (Widget::cases() as $position => $widget) {
            DashboardWidget::query()->create(['widget' => $widget, 'position' => $position]);
        }
    }
}
