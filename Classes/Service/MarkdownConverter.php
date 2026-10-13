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
    /** Elements that carry no readable content for an LLM; templates can mark more with data-sillmstxt-ignore. */
    private const NOISE_XPATH = './/script|.//style|.//noscript|.//template|.//svg|.//iframe|.//nav|.//aside|.//form|.//button|.//dialog|.//*[@hidden]|.//*[@aria-hidden="true"]'
        . '|.//*[@data-sillmstxt-ignore]'
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
        // Links without href are script widgets (accordion titles, tabs); they would stay as raw HTML.
        // An accordion title (role="button") becomes an h3 above its answer and its list is unwrapped
        // (an answer paragraph inside a list item loses its indentation); anything else becomes plain text.
        $accordionLists = [];
        foreach (iterator_to_array($xpath->query('.//a[not(@href)]', $root)) as $link) {
            if ($link->getAttribute('role') === 'button') {
                $replacement = $dom->createElement('h3');
                $replacement->appendChild($dom->createTextNode(trim($link->textContent)));
                $item = $link->parentNode;
                if ($item instanceof \DOMElement && $item->nodeName === 'li' && $item->parentNode instanceof \DOMElement) {
                    $accordionLists[spl_object_id($item->parentNode)] = $item->parentNode;
                }
            } else {
                $replacement = $dom->createElement('span');
                while ($link->firstChild !== null) {
                    $replacement->appendChild($link->firstChild);
                }
            }
            $link->parentNode?->replaceChild($replacement, $link);
        }
        // <details><summary>Question</summary>Answer</details>: the same as an accordion
        foreach (iterator_to_array($xpath->query('.//summary', $root)) as $summary) {
            $heading = $dom->createElement('h3');
            $heading->appendChild($dom->createTextNode(trim($summary->textContent)));
            $summary->parentNode?->replaceChild($heading, $summary);
        }
        // Links without text or image (icon links: share buttons, card overlays) would become "[](…)"
        foreach (iterator_to_array($xpath->query('.//a[@href][not(.//img)]', $root)) as $link) {
            if (trim($link->textContent) === '') {
                $link->parentNode?->removeChild($link);
            }
        }
        foreach ($accordionLists as $list) {
            foreach (iterator_to_array($list->childNodes) as $item) {
                while ($item->firstChild !== null) {
                    $list->parentNode?->insertBefore($item->firstChild, $list);
                }
            }
            $list->parentNode?->removeChild($list);
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
        $markdown = (string)preg_replace('/^(#{1,6}) {2,}/m', '$1 ', $markdown);
        // Template whitespace inside link texts: "[ Label ](…)" -> "[Label](…)"
        $markdown = (string)preg_replace('/\[[ \t]+([^\]\n]*?)[ \t]*\]\(|\[([^\]\n]*?)[ \t]+\]\(/', '[$1$2](', $markdown);
        // league/html-to-markdown escapes "&" (TextConverter, ImageConverter); &lt; and &gt; stay escaped.
        $markdown = str_replace('&amp;', '&', $markdown);
        return trim((string)preg_replace("/\n{3,}/", "\n\n", $markdown));
    }
}
