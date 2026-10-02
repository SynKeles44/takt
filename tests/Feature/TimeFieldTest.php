<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The time field is two inputs wearing one field's clothes.
 *
 * Its whole reason to exist is that a native `input[type=time]` keeps its segments in the shadow
 * DOM: a browser that will not advance after a digit cannot be made to, and the browsers disagree
 * about when they do. What a PHP test can hold still is the contract the rest of the app depends
 * on — the NAME stays on one hidden field carrying `HH:MM`, so every form, validator and
 * controller that reads it is untouched.
 */
class TimeFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_name_sits_on_one_hidden_field_in_the_submitted_format(): void
    {
        $html = Blade::render('<x-time-field name="von" value="09:30"/>');

        $this->assertStringContainsString('type="hidden" name="von" value="09:30"', $html);
        $this->assertSame(1, substr_count($html, 'name="von"'), 'the name must appear exactly once');
    }

    public function test_the_halves_are_pre_split_so_a_value_shows_up(): void
    {
        $html = Blade::render('<x-time-field name="von" value="14:05"/>');

        $this->assertStringContainsString('value="14"', $html);
        $this->assertStringContainsString('value="05"', $html);
    }

    public function test_an_empty_value_leaves_both_halves_empty(): void
    {
        $html = Blade::render('<x-time-field name="von"/>');

        // both halves and the hidden field: three in total
        $this->assertSame(3, substr_count($html, 'value=""'), 'nothing is pre-filled when there is no value');
    }

    /**
     * The backdate field lives outside the form it posts to, which is what `form=` is for — and
     * it has to land on the HIDDEN field, because that is the one carrying the name.
     */
    public function test_a_form_attribute_lands_on_the_field_that_carries_the_name(): void
    {
        $html = Blade::render('<x-time-field name="ab" form="timer-work" value="08:00"/>');

        $this->assertMatchesRegularExpression('/type="hidden" name="ab"[^>]*form="timer-work"/', $html);
    }

    /** Every page that had a native time field still renders one of these, and no native one. */
    public function test_no_view_ships_a_native_time_input_any_more(): void
    {
        $native = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_contains((string) $file->getContents(), 'type="time"')) {
                $native[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $native, 'these views still use a native time input, whose segments cannot be driven');
    }
}
