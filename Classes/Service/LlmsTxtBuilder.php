<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use Woit\SiLlmsTxt\Event\ModifyLlmsTxtEvent;
use Woit\SiLlmsTxt\LlmsTxt\Item;
use Woit\SiLlmsTxt\LlmsTxt\Section;

/**
 * Builds llms.txt (index) and llms-full.txt (all pages as Markdown) for one site language,
 * and the Markdown document of a single page.
 */
final class LlmsTxtBuilder
{
    public const INDEX_FILE = 'llms.txt';
    public const FULL_FILE = 'llms-full.txt';
    public const GENERATOR = 'TYPO3 si_llms_txt';
    /** llms-full.txt with pages that could not be fetched is only kept briefly. */
    private const INCOMPLETE_LIFETIME = 300;

    private bool $incomplete = false;

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly PageCollector $pageCollector,
        private readonly NewsCollector $newsCollector,
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function index(Site $site, SiteLanguage $language): string
    {
        return $this->cached('index', self::INDEX_FILE, $site, $language, function (ModifyLlmsTxtEvent $event) use ($site, $language): string {
            $out = [$this->header($event)];
            foreach ($event->getSections() as $section) {
                $out[] = $this->section($section);
            }
            $out[] = $this->languagesSection($site, $language);
            $out[] = "## Optional\n\n- [" . self::FULL_FILE . '](' . $this->fileUri($language, self::FULL_FILE) . '): '
                . ($this->isGerman($language) ? 'Vollständiger Inhalt aller Seiten als Markdown' : 'Full content of all pages as Markdown');

            return implode("\n\n", array_filter($out)) . "\n";
        });
    }

    public function full(Site $site, SiteLanguage $language): string
    {
        return $this->cached('full', self::FULL_FILE, $site, $language, function (ModifyLlmsTxtEvent $event) use ($site): string {
            $out = [$this->header($event)];
            foreach ($event->getSections() as $section) {
                foreach ($section->items as $item) {
                    if ($item->markdown === null && !$item->includeInFull) {
                        continue;
                    }
                    $uri = new Uri($item->url);
                    $markdown = $item->markdown ?? $this->fetchMarkdown($site, $uri);
                    if ($markdown === null) {
                        $this->incomplete = true;
                        continue;
                    }
                    $source = MarkdownUrl::isMarkdownPath($uri->getPath()) ? (string)$uri->withPath(MarkdownUrl::toPagePath($uri->getPath())) : $item->url;
                    $out[] = "---\n\nSource: " . $source . "\n\n" . MarkdownConverter::stripFrontmatter($markdown);
                }
            }
            return implode("\n\n", $out) . "\n";
        });
    }

    /**
     * Frontmatter of the Markdown document of one page; $source is "editor" or "generated".
     * @return array<string, string|null>
     */
    public function pageFrontmatter(array $page, Site $site, SiteLanguage $language, string $source): array
    {
        $pageUri = $this->pageUri($site, $language, (int)($page['l10n_parent'] ?: $page['uid']));
        $lastChanged = max((int)($page['SYS_LASTCHANGED'] ?? 0), (int)($page['tstamp'] ?? 0));
        return [
            'title' => (string)$page['title'],
            'description' => $this->description($page),
            'language' => $language->getLocale()->getLanguageCode(),
            'lastmod' => $lastChanged > 0 ? date('Y-m-d', $lastChanged) : null,
            'canonical' => (string)$pageUri,
            'source' => $source,
            'generator' => self::GENERATOR,
        ];
    }

    /**
     * Frontmatter of the Markdown document of one EXT:news record shown on the detail page.
     * @return array<string, string|null>
     */
    public function newsFrontmatter(array $news, Site $site, SiteLanguage $language): array
    {
        $lastChanged = max((int)($news['tstamp'] ?? 0), (int)($news['datetime'] ?? 0));
        return [
            'title' => (string)$news['title'],
            'description' => $this->newsDescription($news),
            'language' => $language->getLocale()->getLanguageCode(),
            'date' => (int)($news['datetime'] ?? 0) > 0 ? date('Y-m-d', (int)$news['datetime']) : null,
            'lastmod' => $lastChanged > 0 ? date('Y-m-d', $lastChanged) : null,
            'canonical' => (string)$this->newsUri($site, $language, (int)($news['l10n_parent'] ?: $news['uid'])),
            'source' => 'generated',
            'generator' => self::GENERATOR,
        ];
    }

    /** Detail URL of a news record (default-language uid), built by the site router (route enhancer if configured). */
    public function newsUri(Site $site, SiteLanguage $language, int $newsUid): UriInterface
    {
        return $site->getRouter()->generateUri(SiteConfig::newsDetailPid($site), [
            '_language' => $language,
            'tx_news_pi1' => ['action' => 'detail', 'controller' => 'News', 'news' => $newsUid],
        ]);
    }

    public function pageUri(Site $site, SiteLanguage $language, int $pageUid): UriInterface
    {
        return $site->getRouter()->generateUri($pageUid, ['_language' => $language]);
    }

    public function fileUri(SiteLanguage $language, string $file): string
    {
        return rtrim((string)$language->getBase(), '/') . '/' . $file;
    }

    /** @param \Closure(ModifyLlmsTxtEvent): string $build */
    private function cached(string $kind, string $fileName, Site $site, SiteLanguage $language, \Closure $build): string
    {
        $intro = (string)($language->toArray()['sillmstxtIntro'] ?? '');
        $identifier = $kind . '_' . md5($site->getIdentifier() . '|' . $language->getLanguageId() . '|' . $language->getBase() . '|' . $intro);
        $content = $this->cache->get($identifier);
        if (is_string($content)) {
            return $content;
        }
        $this->incomplete = false;
        $collected = $this->pageCollector->collect($site, $language);
        $collected['news'] = [];
        $tags = ['sillmstxt'];
        $detailPid = SiteConfig::newsDetailPid($site);
        if ($detailPid > 0) {
            // The detail page alone (without a news record) has no own content.
            $collected['pages'] = array_values(array_filter($collected['pages'], static fn(array $entry): bool => $entry['uid'] !== $detailPid));
            $collected['news'] = $this->newsCollector->collect($site, $language);
            // EXT:news flushes these tags in the "pages" cache group when news records change.
            $tags[] = 'tx_news';
            $tags[] = 'pageId_' . $detailPid;
            foreach ($this->newsCollector->storagePids($site) as $pid) {
                $tags[] = 'tx_news_pid_' . $pid;
            }
            foreach ($collected['news'] as $entry) {
                $tags[] = 'tx_news_uid_' . $entry['uid'];
            }
        }
        $event = $this->eventDispatcher->dispatch(new ModifyLlmsTxtEvent(
            $site,
            $language,
            $fileName,
            $this->title($site, $language, $collected['rootTitle'] ?? ''),
            trim($intro),
            $this->sections($collected, $site, $language),
        ));
        $content = $build($event);
        foreach ($collected['pages'] as $entry) {
            $tags[] = 'pageId_' . $entry['uid'];
            if (isset($entry['page']['_LOCALIZED_UID'])) {
                $tags[] = 'pageId_' . (int)$entry['page']['_LOCALIZED_UID'];
            }
        }
        $tags[] = 'pageId_' . $site->getRootPageId();
        $lifetime = $event->getCacheLifetime();
        if ($this->incomplete) {
            $lifetime = $lifetime > 0 ? min($lifetime, self::INCOMPLETE_LIFETIME) : self::INCOMPLETE_LIFETIME;
        }
        $this->cache->set($identifier, $content, array_values(array_unique([...$tags, ...$event->getCacheTags()])), $lifetime);
        return $content;
    }

    /** H1: website title of the language or the site, else the title of the root page, else the site identifier. */
    private function title(Site $site, SiteLanguage $language, string $rootTitle): string
    {
        return $language->getWebsiteTitle() ?: (string)($site->getConfiguration()['websiteTitle'] ?? '') ?: $rootTitle ?: $site->getIdentifier();
    }

    private function header(ModifyLlmsTxtEvent $event): string
    {
        $intro = trim($event->getIntro());
        return '# ' . $event->getTitle() . ($intro !== '' ? "\n\n" . $intro : '');
    }

    /**
     * Sections of the collected pages and news: root page and top-level pages without subpages first,
     * then one section per top-level page, articles (newest first), news.
     * @return array<string, Section>
     */
    private function sections(array $collected, Site $site, SiteLanguage $language): array
    {
        $pages = $articles = [];
        $articleDoktypes = SiteConfig::articleDoktypes($site);
        foreach ($collected['pages'] as $entry) {
            if (in_array((int)$entry['page']['doktype'], $articleDoktypes, true)) {
                $articles[] = $entry;
            } else {
                $pages[$entry['section']][] = $entry;
            }
        }
        $general = $pages[0] ?? [];
        foreach ($pages as $section => $entries) {
            if ($section !== 0 && count($entries) === 1 && $entries[0]['uid'] === $section) {
                $general[] = $entries[0];
                unset($pages[$section]);
            }
        }
        unset($pages[0]);

        $sections = ['pages' => new Section($this->isGerman($language) ? 'Seiten' : 'Pages', $this->pageItems($general, $site, $language))];
        foreach ($pages as $section => $entries) {
            $sections['section-' . $section] = new Section($collected['sections'][$section] ?? 'Pages', $this->pageItems($entries, $site, $language));
        }
        usort($articles, static fn(array $a, array $b): int => [(int)$b['page']['lastUpdated'], (int)$b['page']['crdate']] <=> [(int)$a['page']['lastUpdated'], (int)$a['page']['crdate']]);
        $sections['articles'] = new Section($this->isGerman($language) ? 'Artikel' : 'Articles', $this->pageItems($articles, $site, $language));
        $news = [];
        foreach ($collected['news'] as $entry) {
            $timestamp = (int)($entry['news']['datetime'] ?? 0);
            $news[] = new Item(
                $this->inline((string)$entry['news']['title']),
                (string)MarkdownUrl::fromPageUri($this->newsUri($site, $language, $entry['uid'])),
                $this->newsDescription($entry['news']),
                $timestamp > 0 ? (new \DateTimeImmutable())->setTimestamp($timestamp) : null,
                true,
            );
        }
        $sections['news'] = new Section('News', $news);
        return $sections;
    }

    /**
     * @param list<array{uid: int, section: int, page: array<string, mixed>}> $entries
     * @return list<Item>
     */
    private function pageItems(array $entries, Site $site, SiteLanguage $language): array
    {
        $items = [];
        foreach ($entries as $entry) {
            $items[] = new Item(
                $this->inline((string)$entry['page']['title']),
                (string)MarkdownUrl::fromPageUri($this->pageUri($site, $language, $entry['uid'])),
                $this->description($entry['page']),
                null,
                true,
            );
        }
        return $items;
    }

    private function section(Section $section): string
    {
        if ($section->items === []) {
            return '';
        }
        $lines = ['## ' . $section->title, ''];
        foreach ($section->items as $item) {
            $description = $this->inline($item->description);
            $lines[] = '- [' . $this->inline($item->title) . '](' . $item->url . ')'
                . ($item->date !== null ? ' (' . $item->date->format('Y-m-d') . ')' : '')
                . ($description !== '' ? ': ' . $description : '');
        }
        return implode("\n", $lines);
    }

    private function newsDescription(array $news): string
    {
        return $this->inline(strip_tags((string)(trim((string)($news['description'] ?? '')) ?: ($news['teaser'] ?? ''))));
    }

    private function languagesSection(Site $site, SiteLanguage $current): string
    {
        $lines = [];
        foreach ($site->getLanguages() as $language) {
            if ($language->getLanguageId() !== $current->getLanguageId()) {
                $lines[] = '- [' . $language->getTitle() . '](' . $this->fileUri($language, self::INDEX_FILE) . ')';
            }
        }
        return $lines === [] ? '' : "## Languages\n\n" . implode("\n", $lines);
    }

    private function isGerman(SiteLanguage $language): bool
    {
        return $language->getLocale()->getLanguageCode() === 'de';
    }

    private function description(array $page): string
    {
        return $this->inline((string)(trim((string)($page['tx_sillmstxt_description'] ?? '')) ?: ($page['description'] ?? '')));
    }

    private function inline(string $text): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $text));
    }

    /** The page's .md through the web server itself, so it is rendered like any frontend request. */
    private function fetchMarkdown(Site $site, UriInterface $markdownUri): ?string
    {
        $url = SiteConfig::loopbackUrl($site) . $markdownUri->getPath() . ($markdownUri->getQuery() !== '' ? '?' . $markdownUri->getQuery() : '');
        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => [
                    'Host' => $markdownUri->getAuthority(),
                    'X-Forwarded-Host' => $markdownUri->getAuthority(),
                    'X-Forwarded-Proto' => $markdownUri->getScheme(),
                ],
                'http_errors' => false,
                'allow_redirects' => false,
                'timeout' => 15,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('llms-full.txt: fetching {url} failed: {message}', ['url' => (string)$markdownUri, 'message' => $e->getMessage()]);
            return null;
        }
        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('llms-full.txt: {url} returned {status}', ['url' => (string)$markdownUri, 'status' => $response->getStatusCode()]);
            return null;
        }
        return (string)$response->getBody();
    }
}
