<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Link relations recommended by llmstxt.org for a resolved page: rel="alternate" type="text/markdown"
 * (its .md version) and rel="describedby" (the llms.txt of its language), as <link> elements or HTTP Link header.
 */
final class DiscoveryLinks
{
    public function __construct(private readonly LlmsTxtBuilder $builder) {}

    /**
     * @return array{markdown: string, llmsTxt: string}|null null if the page is not in llms.txt
     */
    public function forRequest(ServerRequestInterface $request): ?array
    {
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!SiteConfig::isEnabled($site) || !$language instanceof SiteLanguage || !$pageInformation instanceof PageInformation) {
            return null;
        }
        $detailPid = SiteConfig::newsDetailPid($site);
        if ($detailPid > 0 && $pageInformation->getId() === $detailPid) {
            $newsUid = NewsCollector::uidFromRequest($request);
            if ($newsUid === 0) {
                return null;
            }
            $markdown = MarkdownUrl::fromPageUri($this->builder->newsUri($site, $language, $newsUid));
        } elseif (SiteConfig::isPageIncluded($pageInformation->getPageRecord(), $site)) {
            $markdown = MarkdownUrl::fromPageUri($this->builder->pageUri($site, $language, $pageInformation->getId()));
        } else {
            return null;
        }
        return ['markdown' => (string)$markdown, 'llmsTxt' => $this->builder->fileUri($language, LlmsTxtBuilder::INDEX_FILE)];
    }

    /** @param array{markdown: string, llmsTxt: string} $links */
    public static function htmlTags(array $links): string
    {
        return '<link rel="alternate" type="text/markdown" title="Markdown" href="' . htmlspecialchars($links['markdown']) . '">' . "\n"
            . '<link rel="describedby" type="text/plain" href="' . htmlspecialchars($links['llmsTxt']) . '">';
    }

    /**
     * Header value; the Markdown file itself only gets "describedby".
     *
     * @param array{markdown: string, llmsTxt: string} $links
     */
    public static function header(array $links, bool $withAlternate): string
    {
        $values = $withAlternate ? ['<' . $links['markdown'] . '>; rel="alternate"; type="text/markdown"'] : [];
        $values[] = '<' . $links['llmsTxt'] . '>; rel="describedby"';
        return implode(', ', $values);
    }
}
