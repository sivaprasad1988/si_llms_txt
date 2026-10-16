<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use Woit\SiLlmsTxt\Service\DiscoveryLinks;
use Woit\SiLlmsTxt\Service\FrontendPage;
use Woit\SiLlmsTxt\Service\LlmsTxtBuilder;
use Woit\SiLlmsTxt\Service\MarkdownConverter;
use Woit\SiLlmsTxt\Service\NewsCollector;
use Woit\SiLlmsTxt\Service\SiteConfig;

/**
 * Answers a ".md" request for the resolved page: the editor field "LLM Markdown" if filled,
 * otherwise the rendered page converted to Markdown.
 * HTML pages and Markdown responses get an HTTP Link header (alternate / describedby, see llmstxt.org).
 */
final class MarkdownRenderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LlmsTxtBuilder $builder,
        private readonly MarkdownConverter $converter,
        private readonly NewsCollector $newsCollector,
        private readonly DiscoveryLinks $discoveryLinks,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        $resolvedPage = FrontendPage::fromRequest($request);
        if (!$request->getAttribute(MarkdownSuffixMiddleware::MARKDOWN)) {
            return $this->withLinkHeader($request, $handler->handle($request), true);
        }
        if (!SiteConfig::isEnabled($site)
            || !$language instanceof SiteLanguage
            || $resolvedPage === null
        ) {
            return $handler->handle($request);
        }

        $page = $resolvedPage['record'];
        $detailPid = SiteConfig::newsDetailPid($site);
        if ($detailPid > 0 && $resolvedPage['id'] === $detailPid) {
            return $this->newsMarkdown($request, $handler);
        }
        if (!SiteConfig::isPageIncluded($page, $site)) {
            // No RESPONSE_HEADER: MarkdownSuffixMiddleware turns this into a 404.
            return new Response();
        }

        $editorMarkdown = trim((string)($page['tx_sillmstxt_markdown'] ?? ''));
        if ($editorMarkdown !== '') {
            return $this->withLinkHeader($request, $this->markdownResponse($this->builder->pageDocument($page, $site, $language, $editorMarkdown, 'editor')), false);
        }

        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 200 || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }
        $body = $this->converter->fromHtml((string)$response->getBody(), SiteConfig::contentElementId($site), $site->getBase());
        return $this->withLinkHeader($request, $this->markdownResponse($this->builder->pageDocument($page, $site, $language, $body, 'generated')), false);
    }

    /** EXT:news detail page: Markdown of the requested news record, 404 without (or with an invisible) record. */
    private function newsMarkdown(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        $newsUid = NewsCollector::uidFromRequest($request);
        $news = $newsUid > 0 ? $this->newsCollector->find($site, $language, $newsUid) : null;
        if ($news === null) {
            return new Response();
        }
        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 200 || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }
        $body = $this->converter->fromHtml((string)$response->getBody(), SiteConfig::contentElementId($site), $site->getBase());
        return $this->withLinkHeader($request, $this->markdownResponse($this->builder->newsDocument($news, $site, $language, $body)), false);
    }

    /** HTML page: alternate + describedby; Markdown response: describedby only. */
    private function withLinkHeader(ServerRequestInterface $request, ResponseInterface $response, bool $isHtml): ResponseInterface
    {
        if ($response->getStatusCode() !== 200
            || ($isHtml && !str_contains($response->getHeaderLine('Content-Type'), 'text/html'))
        ) {
            return $response;
        }
        $links = $this->discoveryLinks->forRequest($request);
        return $links === null ? $response : $response->withAddedHeader('Link', DiscoveryLinks::header($links, $isHtml));
    }

    private function markdownResponse(string $markdown): ResponseInterface
    {
        $response = new Response('php://temp', 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
            MarkdownSuffixMiddleware::RESPONSE_HEADER => '1',
        ]);
        $response->getBody()->write($markdown);
        return $response;
    }
}
