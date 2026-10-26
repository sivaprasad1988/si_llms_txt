<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Event;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use Woit\SiLlmsTxt\LlmsTxt\Item;
use Woit\SiLlmsTxt\LlmsTxt\Section;

/**
 * Dispatched before llms.txt or llms-full.txt of one site language is built (only on a cache miss).
 * Listeners can add, change, reorder or remove sections and items, e.g. list records of their own extension.
 *
 * Section ids of the extension: "pages", "section-<uid of a top-level page>", "articles", "news".
 * The "Languages" and "Optional" sections are added afterwards and are not part of the event.
 */
final class ModifyLlmsTxtEvent
{
    /** @var list<string> */
    private array $cacheTags = [];
    private int $cacheLifetime = 0;

    /** @param array<string, Section> $sections */
    public function __construct(
        private readonly Site $site,
        private readonly SiteLanguage $language,
        private readonly string $fileName,
        private string $title,
        private string $intro,
        private array $sections,
    ) {}

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getLanguage(): SiteLanguage
    {
        return $this->language;
    }

    /** "llms.txt" or "llms-full.txt" */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /** H1 of the file */
    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /** Text below the H1 (site language field "LLM intro") */
    public function getIntro(): string
    {
        return $this->intro;
    }

    public function setIntro(string $intro): void
    {
        $this->intro = $intro;
    }

    /** @return array<string, Section> in output order */
    public function getSections(): array
    {
        return $this->sections;
    }

    /** @param array<string, Section> $sections */
    public function setSections(array $sections): void
    {
        $this->sections = $sections;
    }

    public function getSection(string $id): ?Section
    {
        return $this->sections[$id] ?? null;
    }

    /** Adds (or replaces) a section at the end or before the section $before. */
    public function addSection(string $id, Section $section, ?string $before = null): void
    {
        unset($this->sections[$id]);
        if ($before === null || !isset($this->sections[$before])) {
            $this->sections[$id] = $section;
            return;
        }
        $sections = [];
        foreach ($this->sections as $key => $existing) {
            if ($key === $before) {
                $sections[$id] = $section;
            }
            $sections[$key] = $existing;
        }
        $this->sections = $sections;
    }

    public function removeSection(string $id): void
    {
        unset($this->sections[$id]);
    }

    /** Adds an item to a section; a missing section is created at the end with $sectionId as title. */
    public function addItem(string $sectionId, Item $item): void
    {
        $this->sections[$sectionId] ??= new Section($sectionId);
        $this->sections[$sectionId]->items[] = $item;
    }

    /** Cache tags of the generated file, e.g. of the listed records, so that it is rebuilt when they change. */
    public function addCacheTags(string ...$tags): void
    {
        array_push($this->cacheTags, ...$tags);
    }

    /** @return list<string> */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }

    /** Lifetime of the generated file in seconds, 0 = until a cache tag is flushed. */
    public function setCacheLifetime(int $seconds): void
    {
        $this->cacheLifetime = max(0, $seconds);
    }

    public function getCacheLifetime(): int
    {
        return $this->cacheLifetime;
    }
}
