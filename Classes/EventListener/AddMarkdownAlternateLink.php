<?php

declare(strict_types=1);

namespace Woit\SiLlmsTxt\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use Woit\SiLlmsTxt\Service\DiscoveryLinks;

/**
 * <link rel="alternate" type="text/markdown" href="….md"> and <link rel="describedby" href="…/llms.txt">
 * in the <head> of every included page.
 */
#[AsEventListener('woit/si-llms-txt/markdown-alternate-link')]
final class AddMarkdownAlternateLink
{
    public function __construct(private readonly DiscoveryLinks $discoveryLinks) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $content = $event->getContent();
        if (!str_contains($content, '</head>')) {
            return;
        }
        $links = $this->discoveryLinks->forRequest($event->getRequest());
        if ($links === null) {
            return;
        }
        $event->setContent(preg_replace('#</head>#', DiscoveryLinks::htmlTags($links) . "\n</head>", $content, 1));
    }
}
