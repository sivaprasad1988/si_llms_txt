<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Walks the page tree of a site and returns the pages that exist in one language,
 * in tree order (LLM priority first, then sorting).
 */
final class PageCollector
{
    private const RECYCLER_DOKTYPE = 255;

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array{pages: list<array{uid: int, section: int, page: array<string, mixed>}>, sections: array<int, string>}
     *         section = uid of the top-level ancestor (0 = the root page itself), sections = their titles
     */
    public function collect(Site $site, SiteLanguage $language): array
    {
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($language);
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('language', $languageAspect);
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, $context);

        $result = ['pages' => [], 'sections' => [], 'rootTitle' => ''];
        foreach ($this->fetchRows('uid', $site->getRootPageId()) as $root) {
            $this->visit($root, 0, 0, $site, $languageAspect, $pageRepository, $result);
        }
        return $result;
    }

    private function visit(array $row, int $depth, int $section, Site $site, LanguageAspect $languageAspect, PageRepository $pageRepository, array &$result): void
    {
        $page = $pageRepository->getPageOverlay($row, $languageAspect);
        // TYPO3 12 marks a page translation with _PAGES_OVERLAY_UID instead of _LOCALIZED_UID
        if (isset($page['_PAGES_OVERLAY_UID']) && !isset($page['_LOCALIZED_UID'])) {
            $page['_LOCALIZED_UID'] = (int)$page['_PAGES_OVERLAY_UID'];
        }
        $exists = $languageAspect->getId() === 0 || isset($page['_LOCALIZED_UID']);
        if ($depth === 0 && $page !== []) {
            $result['rootTitle'] = (string)($page['nav_title'] ?: $page['title']);
        }
        if ($depth === 1) {
            $section = (int)$row['uid'];
            $result['sections'][$section] = (string)($page['nav_title'] ?: $page['title']);
        }
        if ($exists && $page !== [] && SiteConfig::isPageIncluded($page, $site)) {
            $result['pages'][] = ['uid' => (int)$row['uid'], 'section' => $section, 'page' => $page];
        }
        if ((int)$row['doktype'] === self::RECYCLER_DOKTYPE) {
            return;
        }
        foreach ($this->fetchRows('pid', (int)$row['uid']) as $child) {
            $this->visit($child, $depth + 1, $section, $site, $languageAspect, $pageRepository, $result);
        }
    }

    /** @return list<array<string, mixed>> default-language rows visible in the frontend */
    private function fetchRows(string $field, int $value): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', 0)
            )
            ->orderBy('tx_sillmstxt_priority', 'DESC')
            ->addOrderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
