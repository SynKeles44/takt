<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

final readonly class ParsedTodoInput
{
    /** @param list<string> $tags */
    public function __construct(
        public string $title,
        public ?Carbon $dueAt = null,
        public bool $hasTime = false,
        public array $tags = [],
    ) {}
}
