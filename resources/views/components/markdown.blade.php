@props(['text'])

{{--
    Rendered Markdown. The HTML comes from App\Support\Markdown, which strips raw HTML before it
    is ever built — so the one unescaped echo in this app is unescaped against a parser's output,
    never against the text Linear sent.
--}}
<div {{ $attributes->class('md') }}>{!! \App\Support\Markdown::html($text) !!}</div>
