<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Markdown;
use Tests\TestCase;

/**
 * Linear's Markdown, rendered.
 *
 * The one case that matters more than the rest is the last: the whole reason this is a parser and
 * not a hand-written subset is that a parser can be told to drop raw HTML, and a subset cannot be
 * told anything.
 */
class MarkdownTest extends TestCase
{
    public function test_it_renders_the_shapes_a_ticket_actually_uses(): void
    {
        $html = Markdown::html("#### Kontext\n\n**Lösung:** ~~verworfen~~ neu\n\n- eins\n- zwei");

        $this->assertStringContainsString('<h4>Kontext</h4>', $html);
        $this->assertStringContainsString('<strong>Lösung:</strong>', $html);
        $this->assertStringContainsString('<del>verworfen</del>', $html);
        $this->assertStringContainsString('<li>eins</li>', $html);
    }

    /** Linear's uploads need a session this app does not have, so an img would be a torn box. */
    public function test_an_upload_becomes_a_link_and_not_an_image(): void
    {
        $html = Markdown::html('![grafik.png](https://uploads.linear.app/abc)');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('class="md-attachment"', $html);
        $this->assertStringContainsString('grafik.png', $html);
    }

    public function test_a_link_out_of_the_app_opens_out_of_the_app(): void
    {
        $html = Markdown::html('[COR-7034](https://linear.app/acme/issue/COR-7034)');

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_raw_html_is_dropped_rather_than_escaped(): void
    {
        $html = Markdown::html("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\nText");

        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('Text', $html);
    }

    /** A javascript: URL is a link the renderer must refuse, not one it merely passes through. */
    public function test_an_unsafe_link_does_not_survive(): void
    {
        $this->assertStringNotContainsString('javascript:', Markdown::html('[klick](javascript:alert(1))'));
    }
}
