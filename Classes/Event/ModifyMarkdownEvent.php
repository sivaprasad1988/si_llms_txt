<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Event;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Dispatched for every ".md" response of a page or EXT:news record.
 * Listeners can change the frontmatter and the Markdown, e.g. list all records of a plugin instead of the
 * first result page, or describe the record shown on a detail page. setNotFound() answers 404 instead.
 */
final class ModifyMarkdownEvent
{
    private bool $notFound = false;

    /**
     * @param array<string, mixed> $record page record (language overlay) or news record
     * @param array<string, string|null> $frontmatter title, description, language, date, lastmod, canonical, source, generator
     */
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly string $table,
        private readonly array $record,
        private array $frontmatter,
        private string $markdown,
        private readonly ?string $html,
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getSite(): Site
    {
        return $this->request->getAttribute('site');
    }

    public function getLanguage(): SiteLanguage
    {
        return $this->request->getAttribute('language');
    }

    /** "pages" or "tx_news_domain_model_news" */
    public function getTable(): string
    {
        return $this->table;
    }

    /** @return array<string, mixed> */
    public function getRecord(): array
    {
        return $this->record;
    }

    /** @return array<string, string|null> empty values are not written */
    public function getFrontmatter(): array
    {
        return $this->frontmatter;
    }

    /** @param array<string, string|null> $frontmatter */
    public function setFrontmatter(array $frontmatter): void
    {
        $this->frontmatter = $frontmatter;
    }

    /** Markdown body without frontmatter */
    public function getMarkdown(): string
    {
        return $this->markdown;
    }

    public function setMarkdown(string $markdown): void
    {
        $this->markdown = $markdown;
    }

    /** Rendered page HTML; null if the Markdown comes from the editor field "LLM Markdown" */
    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function setNotFound(bool $notFound = true): void
    {
        $this->notFound = $notFound;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }
}
