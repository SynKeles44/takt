<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DesignStyle;
use App\Enums\TagColor;
use App\Enums\Theme;
use App\Models\Tag;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignAndTagSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->login();
    }

    public function test_the_carousel_ships_every_slide_and_shows_the_current_one(): void
    {
        $response = $this->get(route('settings'))->assertOk();

        foreach (DesignStyle::cases() as $style) {
            $response->assertSee('data-slide="'.$style->value.'"', escape: false);
        }

        $response
            ->assertSeeInOrder(['data-slide-name', DesignStyle::Soft->label()], escape: false)
            ->assertSee(__('app.settings.style_active'));
    }

    public function test_the_colour_scheme_is_flipped_through_the_same_way(): void
    {
        $response = $this->get(route('settings'))->assertOk();

        foreach (Theme::cases() as $theme) {
            $response->assertSee('data-slide="'.$theme->value.'"', escape: false);
        }

        $response
            ->assertSee('data-param="farbe"', escape: false)
            ->assertSee(__('app.settings.theme_active'));
    }

    public function test_the_previewed_colour_scheme_follows_the_query_parameter(): void
    {
        $this->get(route('settings', ['farbe' => Theme::Sage->value]))
            ->assertOk()
            ->assertSeeInOrder(['data-slide-name', Theme::Sage->label()], escape: false)
            ->assertSee(__('app.settings.theme_choose'))
            ->assertSee('value="sage"', escape: false);
    }

    public function test_a_previewed_scheme_shows_its_own_colours(): void
    {
        $this->get(route('settings', ['farbe' => Theme::Daylight->value]))
            ->assertOk()
            ->assertSee('data-theme="daylight"', escape: false);
    }

    public function test_the_slides_stay_in_the_layout_so_the_card_keeps_its_height(): void
    {
        $response = $this->get(route('settings'))->assertOk();

        // stacked in one grid cell and only hidden by visibility — display:none would
        // let the card resize with every slide
        $response->assertSee('carousel-stack', escape: false);
        $response->assertSee('class="is-off"', escape: false);
        $response->assertDontSee('data-position="2"'."\n".'     class="hidden"', escape: false);
    }

    public function test_the_value_field_is_marked_so_paging_cannot_touch_the_csrf_token(): void
    {
        $content = (string) $this->get(route('settings'))->assertOk()->getContent();

        foreach (['design_style', 'theme'] as $field) {
            $this->assertStringContainsString('name="'.$field.'" value=', $content);
        }

        // the token comes first in the form, so the carousel must address the value field by
        // its marker — reaching for the first hidden input overwrote the token instead
        $form = substr($content, strpos($content, 'data-slide-form'));
        $token = strpos($form, 'name="_token"');
        $value = strpos($form, 'data-slide-value');

        $this->assertNotFalse($value);
        $this->assertLessThan($value, $token);
    }

    public function test_the_previewed_style_follows_the_query_parameter(): void
    {
        $this->get(route('settings', ['stil' => DesignStyle::Terminal->value]))
            ->assertOk()
            ->assertSeeInOrder(['data-slide-name', DesignStyle::Terminal->label()], escape: false)
            ->assertSee(__('app.settings.style_choose'))
            ->assertSee('value="terminal"', escape: false);
    }

    public function test_the_carousel_wraps_around_in_both_directions(): void
    {
        $styles = DesignStyle::cases();
        $first = $styles[0];
        $last = $styles[count($styles) - 1];

        $this->get(route('settings', ['stil' => $first->value]))
            ->assertOk()
            ->assertSee(route('settings', ['stil' => $last->value]), escape: false)
            ->assertSee(route('settings', ['stil' => $styles[1]->value]), escape: false);

        $this->get(route('settings', ['stil' => $last->value]))
            ->assertOk()
            ->assertSee(route('settings', ['stil' => $first->value]), escape: false);
    }

    public function test_an_unknown_preview_style_is_rejected(): void
    {
        $this->get(route('settings', ['stil' => 'vaporwave']))->assertSessionHasErrors('stil');
    }

    public function test_every_style_can_be_activated(): void
    {
        foreach (DesignStyle::cases() as $style) {
            $this->put(route('settings.style'), ['design_style' => $style->value])
                ->assertSessionHasNoErrors();

            $this->assertSame($style, $this->user->refresh()->design_style);

            $this->get(route('dashboard'))
                ->assertOk()
                ->assertSee('data-style="'.$style->value.'"', escape: false);
        }
    }

    public function test_an_unknown_style_is_rejected(): void
    {
        $this->put(route('settings.style'), ['design_style' => 'vaporwave'])
            ->assertSessionHasErrors('design_style');

        $this->assertSame(DesignStyle::Soft, $this->user->refresh()->design_style);
    }

    public function test_theme_and_style_are_independent(): void
    {
        $this->put(route('settings.style'), ['design_style' => DesignStyle::Terminal->value]);
        $this->put(route('settings.theme'), ['theme' => Theme::Daylight->value]);

        $this->get(route('dashboard'))
            ->assertSee('data-theme="daylight"', escape: false)
            ->assertSee('data-style="terminal"', escape: false);
    }

    public function test_a_tag_is_created_with_its_warning_settings(): void
    {
        $this->post(route('tags.store'), [
            'name' => '  Deadline  ',
            'color' => TagColor::Danger->value,
            'warn_lead_minutes' => 240,
            'auto_complete_expired' => '1',
        ])->assertSessionHasNoErrors();

        $tag = Tag::query()->sole();

        $this->assertSame('Deadline', $tag->name);
        $this->assertSame(TagColor::Danger, $tag->color);
        $this->assertSame(240, $tag->warn_lead_minutes);
        $this->assertTrue($tag->auto_complete_expired);
        $this->assertSame($this->user->getKey(), $tag->user_id);
    }

    public function test_a_tag_is_updated(): void
    {
        $tag = Tag::query()->create(['name' => 'Alt', 'color' => TagColor::Accent, 'warn_lead_minutes' => 60]);

        $this->put(route('tags.update', $tag), [
            'name' => 'Neu',
            'color' => TagColor::Work->value,
            'warn_lead_minutes' => 0,
        ])->assertSessionHasNoErrors();

        $tag->refresh();

        $this->assertSame('Neu', $tag->name);
        $this->assertSame(0, $tag->warn_lead_minutes);
        $this->assertFalse($tag->auto_complete_expired);
    }

    public function test_duplicate_tag_names_are_rejected_per_user(): void
    {
        Tag::query()->create(['name' => 'Deadline', 'color' => TagColor::Accent]);

        $this->post(route('tags.store'), [
            'name' => 'Deadline',
            'color' => TagColor::Accent->value,
            'warn_lead_minutes' => 60,
        ])->assertSessionHasErrors('name');

        $other = User::factory()->create();
        Tag::query()->create(['user_id' => $other->getKey(), 'name' => 'Deadline', 'color' => TagColor::Accent]);

        $this->assertSame(2, Tag::query()->withoutGlobalScope('owner')->count());
    }

    public function test_the_lead_time_bounds_are_enforced(): void
    {
        foreach ([-1, 20161] as $minutes) {
            $this->post(route('tags.store'), [
                'name' => 'Grenze '.$minutes,
                'color' => TagColor::Accent->value,
                'warn_lead_minutes' => $minutes,
            ])->assertSessionHasErrors('warn_lead_minutes');
        }

        $this->assertSame(0, Tag::query()->count());
    }

    public function test_deleting_a_tag_keeps_the_todos(): void
    {
        $tag = Tag::query()->create(['name' => 'Weg', 'color' => TagColor::Accent]);
        $todo = Todo::query()->create(['title' => 'Bleibt']);
        $todo->tags()->attach($tag);

        $this->delete(route('tags.destroy', $tag))->assertRedirect();

        $this->assertSame(0, Tag::query()->count());
        $this->assertNotNull($todo->fresh());
        $this->assertCount(0, $todo->fresh()->tags);
    }

    public function test_foreign_tags_are_untouchable(): void
    {
        $foreign = Tag::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'name' => 'Fremd',
            'color' => TagColor::Accent,
        ]);

        $this->put(route('tags.update', $foreign), [
            'name' => 'Gekapert',
            'color' => TagColor::Danger->value,
            'warn_lead_minutes' => 60,
        ])->assertNotFound();

        $this->delete(route('tags.destroy', $foreign))->assertNotFound();

        $this->assertSame('Fremd', $foreign->refresh()->name);
    }

    public function test_the_sidebar_replaces_the_gear_icon_with_a_labelled_entry(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.nav.settings'))
            ->assertSee(route('settings'), escape: false)
            ->assertSee('nav-item', escape: false);
    }

    public function test_tags_live_on_their_own_page_reachable_from_the_todo_area(): void
    {
        $this->get(route('settings'))
            ->assertOk()
            ->assertDontSee('name="warn_lead_minutes"', escape: false)
            ->assertDontSee(__('app.settings.tags_intro'));

        $this->get(route('todos.index'))
            ->assertOk()
            ->assertSee(route('tags.index'), escape: false)
            ->assertSee(__('app.tags.manage'));

        $this->get(route('tags.index'))
            ->assertOk()
            ->assertSee(__('app.settings.tags_title'))
            ->assertSee(__('app.tags.help_title'))
            ->assertSee(__('app.settings.warn_lead'));
    }

    public function test_the_todo_section_stays_highlighted_on_the_tag_page(): void
    {
        $this->get(route('tags.index'))
            ->assertOk()
            ->assertSee('nav-item nav-item-active', escape: false);
    }

    public function test_the_tag_page_shows_how_often_a_tag_is_used(): void
    {
        $tag = Tag::query()->create(['name' => 'Zähler', 'color' => TagColor::Accent]);
        Todo::query()->create(['title' => 'Eins'])->tags()->attach($tag);
        Todo::query()->create(['title' => 'Zwei'])->tags()->attach($tag);

        $this->get(route('tags.index'))
            ->assertOk()
            ->assertSee(trans_choice('app.tags.usage', 2));
    }
}
