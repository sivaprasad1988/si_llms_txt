<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use Psr\Http\Message\UriInterface;

/**
 * Maps page URLs to their Markdown variant and back:
 * /de/funktionen/zeiterfassung <-> /de/funktionen/zeiterfassung.md, /de/ <-> /de/index.md
 */
final class MarkdownUrl
{
    public static function fromPageUri(UriInterface $pageUri): UriInterface
    {
        $path = $pageUri->getPath();
        return $pageUri->withPath(str_ends_with($path, '/') || $path === '' ? rtrim($path, '/') . '/index.md' : $path . '.md');
    }

    public static function isMarkdownPath(string $path): bool
    {
        return str_ends_with($path, '.md');
    }

    public static function toPagePath(string $markdownPath): string
    {
        if (str_ends_with($markdownPath, '/index.md')) {
            return substr($markdownPath, 0, -strlen('index.md'));
        }
        return substr($markdownPath, 0, -strlen('.md'));
    }
}
