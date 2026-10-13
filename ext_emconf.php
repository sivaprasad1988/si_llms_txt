<?php

/**
 * Metadata for the TYPO3 Extension Repository (TER).
 * TYPO3 itself (Composer mode) reads composer.json; this file is kept for TER and tailor.
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'LLMs.txt & Markdown for AI',
    'description' => 'Serves llms.txt, llms-full.txt and a Markdown version of every page per language for AI agents and crawlers – generated from the rendered page or written by editors. Optional EXT:news support. Composer mode only.',
    'category' => 'fe',
    'state' => 'stable',
    'author' => 'Siva',
    'author_email' => 'siva@webofficeit.com',
    'author_company' => '',
    'version' => '1.0.6',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'news' => '14.1.0-14.99.99',
        ],
    ],
];
