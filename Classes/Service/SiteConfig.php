<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Site Set "woit/si-llms-txt": whether it is active for a site, and its settings.
 */
final class SiteConfig
{
    public const SET_NAME = 'woit/si-llms-txt';
    public const STANDARD_DOKTYPE = 1;

    public static function isEnabled(mixed $site): bool
    {
        return $site instanceof Site
            && in_array(self::SET_NAME, $site->getConfiguration()['dependencies'] ?? [], true);
    }

    /** @return list<int> */
    public static function articleDoktypes(Site $site): array
    {
        return GeneralUtility::intExplode(',', (string)$site->getSettings()->get('sillmstxt.articleDoktypes', '12'), true);
    }

    /** @return list<int> */
    public static function includedDoktypes(Site $site): array
    {
        return array_values(array_unique([self::STANDARD_DOKTYPE, ...self::articleDoktypes($site)]));
    }

    public static function contentElementId(Site $site): string
    {
        return (string)$site->getSettings()->get('sillmstxt.contentElementId', 'wrapper');
    }

    public static function loopbackUrl(Site $site): string
    {
        return rtrim((string)$site->getSettings()->get('sillmstxt.loopbackUrl', 'http://127.0.0.1'), '/');
    }

    /** Page with the EXT:news detail plugin; 0 when EXT:news support is off. */
    public static function newsDetailPid(Site $site): int
    {
        if (!ExtensionManagementUtility::isLoaded('news')) {
            return 0;
        }
        return max(0, (int)$site->getSettings()->get('sillmstxt.news.detailPid', 0));
    }

    /** @return list<int> */
    public static function newsStoragePids(Site $site): array
    {
        return GeneralUtility::intExplode(',', (string)$site->getSettings()->get('sillmstxt.news.storagePids', ''), true);
    }

    /** Pages that get a .md URL and an entry in llms.txt (row may be a translation). */
    public static function isPageIncluded(array $page, Site $site): bool
    {
        return in_array((int)($page['doktype'] ?? 0), self::includedDoktypes($site), true)
            && !(bool)($page['tx_sillmstxt_exclude'] ?? false)
            && !(bool)($page['no_index'] ?? false);
    }
}
