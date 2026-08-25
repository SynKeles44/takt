<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Duration;
use PHPUnit\Framework\TestCase;

class DurationTest extends TestCase
{
    public function test_human_reads_as_hours_and_minutes(): void
    {
        $this->assertSame('0m', Duration::human(0));
        $this->assertSame('45m', Duration::human(45 * 60));
        $this->assertSame('1h 00m', Duration::human(3600));
        $this->assertSame('8h 07m', Duration::human(8 * 3600 + 7 * 60));
        $this->assertSame('0m', Duration::human(-500), 'negative input never leaks into the output');
    }

    public function test_compact_drops_the_zero_minutes(): void
    {
        $this->assertSame('8h', Duration::compact(8 * 3600));
        $this->assertSame('8h30', Duration::compact(8 * 3600 + 30 * 60));
        $this->assertSame('45m', Duration::compact(45 * 60));
    }

    public function test_clock_pads_every_part(): void
    {
        $this->assertSame('00:00:00', Duration::clock(0));
        $this->assertSame('01:02:03', Duration::clock(3600 + 120 + 3));
        $this->assertSame('27:00:00', Duration::clock(27 * 3600), 'past a day it keeps counting hours');
    }

    public function test_signed_carries_the_direction(): void
    {
        $this->assertSame("\u{00b1}0m", Duration::signed(0));
        $this->assertSame('+1h 30m', Duration::signed(5400));
        $this->assertSame("\u{2212}1h 30m", Duration::signed(-5400));
    }

    public function test_decimal_hours_round_to_two_places(): void
    {
        $this->assertSame(8.0, Duration::decimalHours(8 * 3600));
        $this->assertSame(7.51, Duration::decimalHours(7 * 3600 + 30 * 60 + 45));
        $this->assertSame(0.0, Duration::decimalHours(-3600));
    }
}
