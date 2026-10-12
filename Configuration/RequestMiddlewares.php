<?php

return [
    'frontend' => [
        // Strips ".md" before the site is resolved, so routing finds the HTML page.
        'woit/si-llms-txt/markdown-suffix' => [
            'target' => \Woit\SiLlmsTxt\Middleware\MarkdownSuffixMiddleware::class,
            'after' => ['typo3/cms-core/normalized-params-attribute'],
            'before' => ['typo3/cms-frontend/site'],
        ],
        // Answers /llms.txt before the base redirect sends "/" to "/de/".
        'woit/si-llms-txt/llms-txt' => [
            'target' => \Woit\SiLlmsTxt\Middleware\LlmsTxtMiddleware::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/base-redirect-resolver'],
        ],
        // Turns the resolved page into Markdown (editor field or rendered HTML).
        'woit/si-llms-txt/markdown-render' => [
            'target' => \Woit\SiLlmsTxt\Middleware\MarkdownRenderMiddleware::class,
            // frontend.page.information is set in prepare-tsfe-rendering; shortcuts redirect first.
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
                'typo3/cms-frontend/shortcut-and-mountpoint-redirect',
            ],
        ],
    ],
];
