<?php

defined('TYPO3') or die();

// Generated llms.txt / llms-full.txt. In group "pages" so that editing a page flushes the
// entries tagged with its pageId_<uid> tag.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['sillmstxt'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
    'groups' => ['pages'],
];
