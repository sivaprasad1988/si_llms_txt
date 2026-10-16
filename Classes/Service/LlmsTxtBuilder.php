<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

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
        private readonly MarkdownConverter $markdownConverter,
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function index(Site $site, SiteLanguage $language): string
    {
        return $this->cached('index', $site, $language, function (array $collected) use ($site, $language): string {
            $pages = $articles = [];
            $articleDoktypes = SiteConfig::articleDoktypes($site);
            foreach ($collected['pages'] as $entry) {
                if (in_array((int)$entry['page']['doktype'], $articleDoktypes, true)) {
                    $articles[] = $entry;
                } else {
                    $pages[$entry['section']][] = $entry;
                }
            }

            $out = [$this->header($site, $language, $collected['rootTitle'] ?? '')];
            // Root page and top-level pages without subpages first, then one section per top-level page.
            $general = $pages[0] ?? [];
            foreach ($pages as $section => $entries) {
                if ($section !== 0 && count($entries) === 1 && $entries[0]['uid'] === $section) {
                    $general[] = $entries[0];
                    unset($pages[$section]);
                }
            }
            unset($pages[0]);
            $out[] = $this->section($this->label('pages', $language), $general, $site, $language);
            foreach ($pages as $section => $entries) {
                $out[] = $this->section($collected['sections'][$section] ?? $this->label('pages', $language), $entries, $site, $language);
            }
            usort($articles, static fn(array $a, array $b): int => [(int)$b['page']['lastUpdated'], (int)$b['page']['crdate']] <=> [(int)$a['page']['lastUpdated'], (int)$a['page']['crdate']]);
            $out[] = $this->section($this->label('articles', $language), $articles, $site, $language);
            $out[] = $this->newsSection($collected['news'], $site, $language);
            $out[] = $this->languagesSection($site, $language);
            $out[] = '## ' . $this->label('optional', $language) . "\n\n- [" . self::FULL_FILE . '](' . $this->fileUri($language, self::FULL_FILE) . '): '
                . $this->label('optional.fullFile', $language);

            return implode("\n\n", array_filter($out)) . "\n";
        });
    }

    public function full(Site $site, SiteLanguage $language): string
    {
        return $this->cached('full', $site, $language, function (array $collected) use ($site, $language): string {
            $out = [$this->header($site, $language, $collected['rootTitle'] ?? '')];
            foreach ($collected['pages'] as $entry) {
                $pageUri = $this->pageUri($site, $language, $entry['uid']);
                $markdown = $this->fetchMarkdown($site, MarkdownUrl::fromPageUri($pageUri));
                if ($markdown === null) {
                    $this->incomplete = true;
                } else {
                    $out[] = "---\n\nSource: " . $pageUri . "\n\n" . MarkdownConverter::stripFrontmatter($markdown);
                }
            }
            foreach ($collected['news'] as $entry) {
                $newsUri = $this->newsUri($site, $language, $entry['uid']);
                $markdown = $this->fetchMarkdown($site, MarkdownUrl::fromPageUri($newsUri));
                if ($markdown === null) {
                    $this->incomplete = true;
                } else {
                    $out[] = "---\n\nSource: " . $newsUri . "\n\n" . MarkdownConverter::stripFrontmatter($markdown);
                }
            }
            return implode("\n\n", $out) . "\n";
        });
    }

    /** Markdown document of one page; $body is the editor Markdown or the converted page content. */
    public function pageDocument(array $page, Site $site, SiteLanguage $language, string $body, string $source): string
    {
        $pageUri = $this->pageUri($site, $language, (int)($page['l10n_parent'] ?: $page['uid']));
        $lastChanged = max((int)($page['SYS_LASTCHANGED'] ?? 0), (int)($page['tstamp'] ?? 0));
        return $this->markdownConverter->document([
            'title' => (string)$page['title'],
            'description' => $this->description($page),
            'language' => $language->getLocale()->getLanguageCode(),
            'lastmod' => $lastChanged > 0 ? date('Y-m-d', $lastChanged) : null,
            'canonical' => (string)$pageUri,
            'source' => $source,
            'generator' => self::GENERATOR,
        ], $body);
    }

    /** Markdown document of one EXT:news record shown on the detail page. */
    public function newsDocument(array $news, Site $site, SiteLanguage $language, string $body): string
    {
        $lastChanged = max((int)($news['tstamp'] ?? 0), (int)($news['datetime'] ?? 0));
        return $this->markdownConverter->document([
            'title' => (string)$news['title'],
            'description' => $this->newsDescription($news),
            'language' => $language->getLocale()->getLanguageCode(),
            'date' => (int)($news['datetime'] ?? 0) > 0 ? date('Y-m-d', (int)$news['datetime']) : null,
            'lastmod' => $lastChanged > 0 ? date('Y-m-d', $lastChanged) : null,
            'canonical' => (string)$this->newsUri($site, $language, (int)($news['l10n_parent'] ?: $news['uid'])),
            'source' => 'generated',
            'generator' => self::GENERATOR,
        ], $body);
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

    /** @param \Closure(array): string $build */
    private function cached(string $kind, Site $site, SiteLanguage $language, \Closure $build): string
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
        $content = $build($collected);
        foreach ($collected['pages'] as $entry) {
            $tags[] = 'pageId_' . $entry['uid'];
            if (isset($entry['page']['_LOCALIZED_UID'])) {
                $tags[] = 'pageId_' . (int)$entry['page']['_LOCALIZED_UID'];
            }
        }
        $tags[] = 'pageId_' . $site->getRootPageId();
        $this->cache->set($identifier, $content, array_values(array_unique($tags)), $this->incomplete ? self::INCOMPLETE_LIFETIME : 0);
        return $content;
    }

    /** H1: website title of the language or the site, else the title of the root page, else the site identifier. */
    private function header(Site $site, SiteLanguage $language, string $rootTitle): string
    {
        $title = $language->getWebsiteTitle() ?: (string)($site->getConfiguration()['websiteTitle'] ?? '') ?: $rootTitle ?: $site->getIdentifier();
        $intro = trim((string)($language->toArray()['sillmstxtIntro'] ?? ''));
        return '# ' . $title . ($intro !== '' ? "\n\n" . $intro : '');
    }

    /** @param list<array{uid: int, section: int, page: array<string, mixed>}> $entries */
    private function section(string $title, array $entries, Site $site, SiteLanguage $language): string
    {
        if ($entries === []) {
            return '';
        }
        $lines = ['## ' . $title, ''];
        foreach ($entries as $entry) {
            $description = $this->description($entry['page']);
            $lines[] = '- [' . $this->inline((string)$entry['page']['title']) . ']('
                . MarkdownUrl::fromPageUri($this->pageUri($site, $language, $entry['uid'])) . ')'
                . ($description !== '' ? ': ' . $description : '');
        }
        return implode("\n", $lines);
    }

    /** @param list<array{uid: int, news: array<string, mixed>}> $entries */
    private function newsSection(array $entries, Site $site, SiteLanguage $language): string
    {
        if ($entries === []) {
            return '';
        }
        $lines = ['## ' . $this->label('news', $language), ''];
        foreach ($entries as $entry) {
            $description = $this->newsDescription($entry['news']);
            $date = (int)($entry['news']['datetime'] ?? 0) > 0 ? ' (' . date('Y-m-d', (int)$entry['news']['datetime']) . ')' : '';
            $lines[] = '- [' . $this->inline((string)$entry['news']['title']) . '](' . MarkdownUrl::fromPageUri($this->newsUri($site, $language, $entry['uid'])) . ')'
                . $date . ($description !== '' ? ': ' . $description : '');
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
        return $lines === [] ? '' : '## ' . $this->label('languages', $current) . "\n\n" . implode("\n", $lines);
    }

    /** Heading/label from locallang.xlf in the language of the generated file. */
    private function label(string $key, SiteLanguage $language): string
    {
        return $this->languageServiceFactory->createFromSiteLanguage($language)
            ->sL('LLL:EXT:si_llms_txt/Resources/Private/Language/locallang.xlf:llmstxt.' . $key);
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
        $url = SiteConfig::loopbackUrl($site) . $markdownUri->getPath();
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
