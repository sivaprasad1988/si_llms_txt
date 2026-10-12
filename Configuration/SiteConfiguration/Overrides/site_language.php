<?php

$GLOBALS['SiteConfiguration']['site_language']['columns']['sillmstxtIntro'] = [
    'label' => 'LLL:EXT:si_llms_txt/Resources/Private/Language/locallang_db.xlf:site_language.sillmstxtIntro',
    'description' => 'LLL:EXT:si_llms_txt/Resources/Private/Language/locallang_db.xlf:site_language.sillmstxtIntro.description',
    'config' => [
        'type' => 'text',
        'rows' => 12,
        'fixedFont' => true,
    ],
];
$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] .= ', --div--;LLM, sillmstxtIntro';
