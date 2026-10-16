<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\EventListener;

use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use Woit\SiLlmsTxt\Service\DiscoveryLinks;

/**
 * <link rel="alternate" type="text/markdown" href="….md"> and <link rel="describedby" href="…/llms.txt">
 * in the <head> of every included page.
 * Registered in Services.yaml (the #[AsEventListener] attribute needs TYPO3 13+).
 */
final class AddMarkdownAlternateLink
{
    public function __construct(private readonly DiscoveryLinks $discoveryLinks) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        // TYPO3 14: content on the event; TYPO3 12/13: on the frontend controller
        $controller = method_exists($event, 'getContent') ? null : $event->getController();
        $content = $controller === null ? $event->getContent() : (string)$controller->content;
        if (!str_contains($content, '</head>')) {
            return;
        }
        $links = $this->discoveryLinks->forRequest($event->getRequest());
        if ($links === null) {
            return;
        }
        $content = preg_replace('#</head>#', DiscoveryLinks::htmlTags($links) . "\n</head>", $content, 1);
        if ($controller === null) {
            $event->setContent($content);
        } else {
            $controller->content = $content;
        }
    }
}
