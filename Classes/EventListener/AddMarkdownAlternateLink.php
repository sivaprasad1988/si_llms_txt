<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Woit\SiLlmsTxt\Service\LlmsTxtBuilder;
use Woit\SiLlmsTxt\Service\MarkdownUrl;
use Woit\SiLlmsTxt\Service\NewsCollector;
use Woit\SiLlmsTxt\Service\SiteConfig;

/**
 * <link rel="alternate" type="text/markdown" href="….md"> in the <head> of every included page.
 */
#[AsEventListener('woit/si-llms-txt/markdown-alternate-link')]
final class AddMarkdownAlternateLink
{
    public function __construct(private readonly LlmsTxtBuilder $builder) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!SiteConfig::isEnabled($site) || !$language instanceof SiteLanguage || !$pageInformation instanceof PageInformation) {
            return;
        }
        $page = $pageInformation->getPageRecord();
        $content = $event->getContent();
        if (!str_contains($content, '</head>')) {
            return;
        }
        $detailPid = SiteConfig::newsDetailPid($site);
        if ($detailPid > 0 && $pageInformation->getId() === $detailPid) {
            $newsUid = NewsCollector::uidFromRequest($request);
            if ($newsUid === 0) {
                return;
            }
            $href = MarkdownUrl::fromPageUri($this->builder->newsUri($site, $language, $newsUid));
        } elseif (SiteConfig::isPageIncluded($page, $site)) {
            $href = MarkdownUrl::fromPageUri($this->builder->pageUri($site, $language, $pageInformation->getId()));
        } else {
            return;
        }
        $link = '<link rel="alternate" type="text/markdown" title="Markdown" href="' . htmlspecialchars((string)$href) . '">';
        $event->setContent(preg_replace('#</head>#', $link . "\n</head>", $content, 1));
    }
}
