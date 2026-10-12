<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Http\Message\UriInterface;

/**
 * Rendered page HTML -> Markdown of its main content area, plus YAML frontmatter.
 */
final class MarkdownConverter
{
    /** Elements that carry no readable content for an LLM. */
    private const NOISE_XPATH = './/script|.//style|.//noscript|.//template|.//svg|.//iframe|.//nav|.//aside|.//form|.//button|.//dialog|.//*[@hidden]|.//*[@aria-hidden="true"]'
        . '|.//*[contains(@class, "breadcrumb")]'
        // Decorative images: empty alt, alt "icon" or SVG icons.
        . '|.//img[@alt="" or translate(@alt, "ICON", "icon")="icon" or contains(@src, ".svg")]';
    /** Inline text that sits next to block elements (e.g. an eyebrow above the h1) becomes its own paragraph. */
    private const INLINE_NEXT_TO_BLOCK_XPATH = './/span[../h1 or ../h2 or ../h3 or ../h4 or ../h5 or ../h6 or ../p or ../ul or ../ol or ../div]';

    public function fromHtml(string $html, string $elementId, UriInterface $siteBase): string
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $root = $dom->getElementById($elementId) ?? $dom->getElementsByTagName('body')->item(0);
        if ($root === null) {
            return '';
        }

        $xpath = new \DOMXPath($dom);
        foreach (iterator_to_array($xpath->query(self::NOISE_XPATH, $root)) as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach (iterator_to_array($xpath->query(self::INLINE_NEXT_TO_BLOCK_XPATH . '|.//figure', $root)) as $span) {
            $paragraph = $dom->createElement($span->nodeName === 'figure' ? 'div' : 'p');
            while ($span->firstChild !== null) {
                $paragraph->appendChild($span->firstChild);
            }
            $span->parentNode?->replaceChild($paragraph, $span);
        }
        $origin = $siteBase->withPath('')->withQuery('')->withFragment('');
        foreach (iterator_to_array($xpath->query('.//a[@href]|.//img[@src]', $root)) as $node) {
            $attribute = $node->nodeName === 'a' ? 'href' : 'src';
            $value = $node->getAttribute($attribute);
            if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
                $node->setAttribute($attribute, $origin . $value);
            }
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
            'hard_break' => true,
            'remove_nodes' => 'script style',
        ]);
        $converter->getEnvironment()->addConverter(new TableConverter());

        return $this->tidy($converter->convert((string)$dom->saveHTML($root)));
    }

    /** @param array<string, string|null> $fields */
    public function document(array $fields, string $body): string
    {
        $yaml = ['---'];
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $yaml[] = $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $yaml[] = '---';
        return implode("\n", $yaml) . "\n\n" . $this->tidy($body) . "\n";
    }

    public static function stripFrontmatter(string $markdown): string
    {
        return trim((string)preg_replace('/\A---\n.*?\n---\n/s', '', $markdown));
    }

    /**
     * Template indentation (4+ spaces at the start of a paragraph) would turn text into an indented code
     * block. The converter writes real code as fenced blocks, so outside ``` fences such paragraphs are
     * unindented; list continuation lines (directly below their item) are left alone.
     */
    private function unindentParagraphs(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $inFence = false;
        foreach ($lines as $i => $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = !$inFence;
                continue;
            }
            $startsParagraph = $i === 0 || trim($lines[$i - 1]) === '';
            if (!$inFence && $startsParagraph && preg_match('/^ {4,}(?![-*+] |\d+\. )\S/', $line)) {
                $lines[$i] = ltrim($line, ' ');
            }
        }
        return implode("\n", $lines);
    }

    private function tidy(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\u{00A0}"], ["\n", ' '], $markdown);
        $markdown = (string)preg_replace('/[ \t]+$/m', '', $markdown);
        // Leading spaces before text come from inline whitespace; list indents (before - * + 1.) stay.
        $markdown = (string)preg_replace('/^ {1,3}(?=[^\s\-*+0-9])/m', '', $markdown);
        $markdown = $this->unindentParagraphs($markdown);
        // league/html-to-markdown escapes "&" (TextConverter, ImageConverter); &lt; and &gt; stay escaped.
        $markdown = str_replace('&amp;', '&', $markdown);
        return trim((string)preg_replace("/\n{3,}/", "\n\n", $markdown));
    }
}
