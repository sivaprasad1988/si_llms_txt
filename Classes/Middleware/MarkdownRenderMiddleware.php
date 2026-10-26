<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Middleware;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Woit\SiLlmsTxt\Event\ModifyMarkdownEvent;
use Woit\SiLlmsTxt\Service\DiscoveryLinks;
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
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!$request->getAttribute(MarkdownSuffixMiddleware::MARKDOWN)) {
            return $this->withLinkHeader($request, $handler->handle($request), true);
        }
        if (!SiteConfig::isEnabled($site)
            || !$language instanceof SiteLanguage
            || !$pageInformation instanceof PageInformation
        ) {
            return $handler->handle($request);
        }

        $page = $pageInformation->getPageRecord();
        $detailPid = SiteConfig::newsDetailPid($site);
        if ($detailPid > 0 && $pageInformation->getId() === $detailPid) {
            return $this->newsMarkdown($request, $handler);
        }
        if (!SiteConfig::isPageIncluded($page, $site)) {
            // No RESPONSE_HEADER: MarkdownSuffixMiddleware turns this into a 404.
            return new Response();
        }

        $editorMarkdown = trim((string)($page['tx_sillmstxt_markdown'] ?? ''));
        if ($editorMarkdown !== '') {
            return $this->document($request, 'pages', $page, $this->builder->pageFrontmatter($page, $site, $language, 'editor'), $editorMarkdown, null);
        }

        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 200 || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }
        $html = (string)$response->getBody();
        $body = $this->converter->fromHtml($html, SiteConfig::contentElementId($site), $site->getBase());
        return $this->document($request, 'pages', $page, $this->builder->pageFrontmatter($page, $site, $language, 'generated'), $body, $html);
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
        $html = (string)$response->getBody();
        $body = $this->converter->fromHtml($html, SiteConfig::contentElementId($site), $site->getBase());
        return $this->document($request, 'tx_news_domain_model_news', $news, $this->builder->newsFrontmatter($news, $site, $language), $body, $html);
    }

    /** Lets listeners of ModifyMarkdownEvent change the document; setNotFound() becomes a 404. */
    private function document(ServerRequestInterface $request, string $table, array $record, array $frontmatter, string $body, ?string $html): ResponseInterface
    {
        $event = $this->eventDispatcher->dispatch(new ModifyMarkdownEvent($request, $table, $record, $frontmatter, $body, $html));
        if ($event->isNotFound()) {
            // No RESPONSE_HEADER: MarkdownSuffixMiddleware turns this into a 404.
            return new Response();
        }
        return $this->withLinkHeader($request, $this->markdownResponse($this->converter->document($event->getFrontmatter(), $event->getMarkdown())), false);
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
