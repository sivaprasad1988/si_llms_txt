<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\LlmsTxt;

/** One "## title" section of llms.txt; sections without items are left out. */
final class Section
{
    /** @param list<Item> $items */
    public function __construct(
        public string $title,
        public array $items = [],
    ) {}
}
