<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\Site;
use Woit\SiLlmsTxt\Service\LlmsTxtBuilder;
use Woit\SiLlmsTxt\Service\SiteConfig;

/**
 * /llms.txt and /llms-full.txt (default language), <language base>/llms.txt and <language base>/llms-full.txt
 */
final class LlmsTxtMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LlmsTxtBuilder $builder) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $uri = $request->getUri();
        if (!SiteConfig::isEnabled($site) || !in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || !str_ends_with($uri->getPath(), '.txt')
        ) {
            return $handler->handle($request);
        }
        /** @var Site $site */
        $sitePath = rtrim($site->getBase()->getPath(), '/') . '/';
        if ($uri->getPath() === $sitePath . LlmsTxtBuilder::INDEX_FILE) {
            return $this->textResponse($this->builder->index($site, $site->getDefaultLanguage()));
        }
        if ($uri->getPath() === $sitePath . LlmsTxtBuilder::FULL_FILE) {
            return $this->textResponse($this->builder->full($site, $site->getDefaultLanguage()));
        }
        foreach ($site->getLanguages() as $language) {
            $base = $language->getBase();
            if ($base->getHost() !== '' && $base->getHost() !== $uri->getHost()) {
                continue;
            }
            $basePath = rtrim($base->getPath(), '/') . '/';
            if ($uri->getPath() === $basePath . LlmsTxtBuilder::INDEX_FILE) {
                return $this->textResponse($this->builder->index($site, $language));
            }
            if ($uri->getPath() === $basePath . LlmsTxtBuilder::FULL_FILE) {
                return $this->textResponse($this->builder->full($site, $language));
            }
        }
        return $handler->handle($request);
    }

    private function textResponse(string $content): ResponseInterface
    {
        $response = new Response('php://temp', 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
        ]);
        $response->getBody()->write($content);
        return $response;
    }
}
