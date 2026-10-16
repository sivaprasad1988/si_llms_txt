<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * The page resolved for a frontend request: "frontend.page.information" (TYPO3 13+)
 * or the TypoScriptFrontendController (TYPO3 12).
 */
final class FrontendPage
{
    /** @return array{id: int, record: array<string, mixed>}|null */
    public static function fromRequest(ServerRequestInterface $request): ?array
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            return ['id' => $pageInformation->getId(), 'record' => $pageInformation->getPageRecord()];
        }
        $controller = $request->getAttribute('frontend.controller');
        if ($controller instanceof TypoScriptFrontendController && is_array($controller->page) && $controller->page !== []) {
            return ['id' => (int)$controller->id, 'record' => $controller->page];
        }
        return null;
    }
}
