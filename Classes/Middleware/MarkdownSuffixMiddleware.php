<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use Woit\SiLlmsTxt\Service\MarkdownUrl;

/**
 * "/de/page.md" is routed as "/de/page" with the request attribute MARKDOWN set;
 * MarkdownRenderMiddleware then answers with Markdown. Anything else becomes a plain 404.
 */
final class MarkdownSuffixMiddleware implements MiddlewareInterface
{
    public const MARKDOWN = 'sillmstxt.markdown';
    /** Set by MarkdownRenderMiddleware on its responses. */
    public const RESPONSE_HEADER = 'X-Si-Llms-Txt';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true) || !MarkdownUrl::isMarkdownPath($uri->getPath())) {
            return $handler->handle($request);
        }

        $response = $handler->handle(
            $request->withUri($uri->withPath(MarkdownUrl::toPagePath($uri->getPath())))->withAttribute(self::MARKDOWN, true)
        );

        if ($response->hasHeader(self::RESPONSE_HEADER)) {
            return $response->withoutHeader(self::RESPONSE_HEADER);
        }
        // Shortcut pages and base redirects: keep the visitor on the Markdown variant.
        if (in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true) && $response->hasHeader('Location')) {
            $location = new \TYPO3\CMS\Core\Http\Uri($response->getHeaderLine('Location'));
            if (($location->getHost() === '' || $location->getHost() === $uri->getHost()) && !MarkdownUrl::isMarkdownPath($location->getPath())) {
                $location = MarkdownUrl::fromPageUri($location);
            }
            return $response->withHeader('Location', (string)$location);
        }
        $status = $response->getStatusCode() >= 400 ? $response->getStatusCode() : 404;
        return (new Response('php://temp', $status, ['Content-Type' => 'text/plain; charset=utf-8']))
            ->withBody(self::body($status === 404 ? 'Not found' : 'Error ' . $status));
    }

    private static function body(string $text): \Psr\Http\Message\StreamInterface
    {
        $stream = new \TYPO3\CMS\Core\Http\Stream('php://temp', 'rw');
        $stream->write($text . "\n");
        return $stream;
    }
}
