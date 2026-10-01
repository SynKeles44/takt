<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Linear's text, rendered.
 *
 * It arrived as Markdown and was shown as Markdown — headings as `####`, emphasis as `**`, every
 * link printed as its own URL twice over. That is the text a machine sends, not the text a person
 * reads, and on a comment of five paragraphs it is the difference between skimming and parsing.
 *
 * The note this replaces was right about the danger and wrong about the conclusion: a hand-written
 * Markdown subset emitting HTML would indeed be an XSS surface for text this app does not own.
 * CommonMark is not that — it is a spec-complete parser already in the tree, and with
 * `html_input: strip` the raw HTML a Linear comment may carry never reaches the page at all.
 */
final class Markdown
{
    private static ?MarkdownConverter $converter = null;

    public static function html(string $text): string
    {
        return (string) (self::$converter ??= self::converter())->convert($text);
    }

    private static function converter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,

            // every link in a Linear ticket points out of this app by definition
            'external_link' => [
                'internal_hosts' => 'localhost',
                'open_in_new_window' => true,
                'noopener' => 'all',
                'noreferrer' => 'all',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addRenderer(Image::class, new AttachmentLink, 10);

        return new MarkdownConverter($environment);
    }
}
