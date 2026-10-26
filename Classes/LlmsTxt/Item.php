<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\LlmsTxt;

/**
 * One link in llms.txt: "- [title](url) (date): description".
 * For llms-full.txt the item contributes $markdown if set, otherwise (with $includeInFull) the document
 * fetched from $url, which must then be a Markdown URL of this site.
 */
final class Item
{
    public function __construct(
        public string $title,
        public string $url,
        public string $description = '',
        public ?\DateTimeInterface $date = null,
        public bool $includeInFull = false,
        public ?string $markdown = null,
    ) {}
}
