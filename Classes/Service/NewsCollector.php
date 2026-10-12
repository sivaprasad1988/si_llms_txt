<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * News records of EXT:news (tx_news) that exist in one site language, newest first.
 * Only used when EXT:news is loaded and sillmstxt.news.detailPid is set.
 */
final class NewsCollector
{
    public const TABLE = 'tx_news_domain_model_news';
    /** tx_news "type" 0 = news; 1 = internal page, 2 = external URL (no own content). */
    private const TYPE_NEWS = '0';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /** @return list<array{uid: int, news: array<string, mixed>}> */
    public function collect(Site $site, SiteLanguage $language): array
    {
        [$pageRepository, $languageAspect] = $this->repository($language);
        $result = [];
        foreach ($this->fetchRows($this->storagePids($site, $pageRepository), null) as $row) {
            $news = $this->overlay($row, $languageAspect, $pageRepository);
            if ($news !== null) {
                $result[] = ['uid' => (int)$row['uid'], 'news' => $news];
            }
        }
        return $result;
    }

    /** One news record (default-language uid) in the given language, or null if not visible there. */
    public function find(Site $site, SiteLanguage $language, int $uid): ?array
    {
        [$pageRepository, $languageAspect] = $this->repository($language);
        $row = $this->fetchRows($this->storagePids($site, $pageRepository), $uid)[0] ?? null;
        return $row === null ? null : $this->overlay($row, $languageAspect, $pageRepository);
    }

    /** News uid of a detail page request (tx_news_pi1[news], from the route enhancer or the query string); 0 if none. */
    public static function uidFromRequest(ServerRequestInterface $request): int
    {
        $routing = $request->getAttribute('routing');
        return $routing instanceof PageArguments ? (int)($routing->getArguments()['tx_news_pi1']['news'] ?? 0) : 0;
    }

    /** @return list<int> storage folders: setting sillmstxt.news.storagePids, else the whole page tree of the site */
    public function storagePids(Site $site, ?PageRepository $pageRepository = null): array
    {
        $configured = SiteConfig::newsStoragePids($site);
        if ($configured !== []) {
            return $configured;
        }
        $pageRepository ??= GeneralUtility::makeInstance(PageRepository::class);
        $root = $site->getRootPageId();
        // Storage folders are often hidden; EXT:news shows their records anyway (deleted pages stay excluded).
        return [$root, ...$pageRepository->getDescendantPageIdsRecursive($root, 99, 0, [], true)];
    }

    /** @return array{0: PageRepository, 1: LanguageAspect} */
    private function repository(SiteLanguage $language): array
    {
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($language);
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('language', $languageAspect);
        return [GeneralUtility::makeInstance(PageRepository::class, $context), $languageAspect];
    }

    private function overlay(array $row, LanguageAspect $languageAspect, PageRepository $pageRepository): ?array
    {
        if ($languageAspect->getId() === 0 || (int)$row['sys_language_uid'] === -1) {
            return $row;
        }
        $news = $pageRepository->getLanguageOverlay(self::TABLE, $row, $languageAspect);
        // Strict fallback: only real translations.
        return is_array($news) && isset($news['_LOCALIZED_UID']) ? $news : null;
    }

    /** @param list<int> $pids */
    private function fetchRows(array $pids, ?int $uid): array
    {
        if ($pids === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->in('sys_language_uid', $queryBuilder->createNamedParameter([0, -1], Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter(self::TYPE_NEWS))
            )
            ->orderBy('datetime', 'DESC')
            ->addOrderBy('uid', 'DESC');
        if ($uid !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)));
        }
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
}
