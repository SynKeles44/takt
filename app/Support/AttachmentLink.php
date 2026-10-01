<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * An image in Linear's Markdown, rendered as a link rather than as an image.
 *
 * Linear's uploads sit behind a session this app does not have, so an `<img>` is a broken image
 * with its alt text underneath it — the worst of both. A link carries the same name, takes one
 * line instead of a torn box, and opens where the file actually is.
 */
final class AttachmentLink implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (! $node instanceof Image) {
            throw new \InvalidArgumentException('Incompatible node type: '.$node::class);
        }

        $label = trim($childRenderer->renderNodes($node->children()));

        return new HtmlElement('a', [
            'href' => $node->getUrl(),
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => 'md-attachment',
        ], $label !== '' ? $label : __('app.ticket.attachment'));
    }
}
