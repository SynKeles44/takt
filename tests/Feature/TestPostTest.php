<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TestPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_three_lines_from_short_input(): void
    {
        $this->login();

        $this->get(route('dev.testpost', [
            'ticket' => 'COR-6944',
            'pr' => '2456',
            'instance' => 'b63d4865/mod/zeiterfassung/?fn=time_list&sub_fn=ma_time_list',
        ]))
            ->assertOk()
            ->assertSee('Ticket: https://linear.app/galawork/issue/COR-6944', false)
            ->assertSee('PR: https://github.com/galabau-workgroup/galawork-web/pull/2456', false)
            // the ampersand is escaped in the page; the clipboard gets the real one back
            ->assertSee('Test-Instanz: https://b63d4865-web.galawork.dev/mod/zeiterfassung/?fn=time_list&amp;sub_fn=ma_time_list', false);
    }

    public function test_full_urls_are_taken_as_they_are(): void
    {
        $user = $this->login();
        $builder = app(TestPost::class);

        $result = $builder->build($user, [
            'ticket' => 'https://linear.app/other/issue/ABC-1/etwas',
            'pr' => 'https://github.com/x/y/pull/9',
            'instance' => 'https://test.example/app',
        ]);

        $this->assertSame('https://linear.app/other/issue/ABC-1/etwas', $result['ticket']);
        $this->assertSame('https://github.com/x/y/pull/9', $result['pr']);
        $this->assertSame('https://test.example/app', $result['instance']);
        $this->assertSame([], $result['missing']);
    }

    public function test_a_path_behind_the_instance_id_lands_in_the_url(): void
    {
        $user = $this->login();

        $result = app(TestPost::class)->build($user, ['instance' => 'b63d4865/mod/zeiterfassung']);

        $this->assertSame('https://b63d4865-web.galawork.dev/mod/zeiterfassung', $result['instance']);
    }

    public function test_a_bare_instance_id_stays_without_a_path(): void
    {
        $user = $this->login();

        $result = app(TestPost::class)->build($user, ['instance' => 'abc123']);

        $this->assertSame('https://abc123-web.galawork.dev', $result['instance']);
    }

    public function test_it_names_what_is_still_missing(): void
    {
        $this->login();

        $this->get(route('dev.testpost', ['ticket' => 'COR-1']))
            ->assertOk()
            ->assertSee('Es fehlt noch: PR, Instanz');
    }

    public function test_own_templates_win_over_the_defaults(): void
    {
        $user = $this->login([
            'ticket_url_template' => 'https://tickets.example/{KEY}',
            'pr_url_template' => 'https://git.example/pr/{number}',
            'instance_url_template' => 'https://{id}.review.example{path}',
        ]);

        $result = app(TestPost::class)->build($user, [
            'ticket' => 'abc-7',
            'pr' => '#42',
            'instance' => 'feature-x/start',
        ]);

        $this->assertSame('https://tickets.example/ABC-7', $result['ticket']);
        $this->assertSame('https://git.example/pr/42', $result['pr']);
        $this->assertSame('https://feature-x.review.example/start', $result['instance']);
    }

    public function test_the_templates_are_stored_in_the_settings(): void
    {
        $user = $this->login();

        $this->put(route('settings.developer'), [
            'ticket_url_template' => 'https://tickets.example/{KEY}',
            'pr_url_template' => '',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('https://tickets.example/{KEY}', $user->fresh()->ticket_url_template);
        $this->assertNull($user->fresh()->pr_url_template);

        $this->get(route('settings'))->assertOk()->assertSee('Ticket-Vorlage');
    }

    public function test_the_page_is_reachable_from_the_development_tabs(): void
    {
        $this->login();

        $this->get(route('dev'))->assertOk()->assertSee(route('dev.testpost'), false);
        $this->get(route('dev.testpost'))->assertOk()->assertSee('Testpost');
    }
}
