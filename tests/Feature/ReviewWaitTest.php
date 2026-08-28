<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReviewWaitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-28 12:00:00');
    }

    /** @param  list<int>  $ages */
    private function pulls(array $ages): array
    {
        return array_map(fn (int $days): array => [
            'title' => 'Etwas',
            'number' => 1,
            'url' => 'https://github.test/pr/1',
            'repository' => 'acme/web',
            'draft' => false,
            'updated_at' => Carbon::now(),
            'created_at' => Carbon::now()->subDays($days),
        ], $ages);
    }

    public function test_an_odd_number_of_pull_requests_takes_the_middle_one(): void
    {
        $stats = app(Reviews::class)->waitStats([
            'mine' => $this->pulls([1, 12, 4]),
            'incoming' => [],
        ]);

        $this->assertSame(3, $stats['mine']['count']);
        $this->assertSame(4, $stats['mine']['median']);
        $this->assertSame(12, $stats['mine']['oldest']);
        $this->assertSame(1, $stats['mine']['stale']);
    }

    public function test_an_even_number_averages_the_two_in_the_middle(): void
    {
        $stats = app(Reviews::class)->waitStats([
            'mine' => $this->pulls([1, 2, 8, 10]),
            'incoming' => [],
        ]);

        $this->assertSame(5, $stats['mine']['median']);
        $this->assertSame(2, $stats['mine']['stale']);
    }

    public function test_an_empty_list_reports_nothing_instead_of_zero(): void
    {
        $stats = app(Reviews::class)->waitStats(['mine' => [], 'incoming' => []]);

        $this->assertSame(0, $stats['mine']['count']);
        $this->assertNull($stats['mine']['median']);
        $this->assertNull($stats['mine']['oldest']);
        $this->assertSame(0, $stats['mine']['stale']);
    }

    public function test_both_lists_are_measured(): void
    {
        $stats = app(Reviews::class)->waitStats([
            'mine' => $this->pulls([3]),
            'incoming' => $this->pulls([21, 1]),
        ]);

        $this->assertSame(3, $stats['mine']['oldest']);
        $this->assertSame(21, $stats['incoming']['oldest']);
        $this->assertSame(1, $stats['incoming']['stale']);
    }
}
