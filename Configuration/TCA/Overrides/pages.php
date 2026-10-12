<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$ll = 'LLL:EXT:si_llms_txt/Resources/Private/Language/locallang_db.xlf:';

ExtensionManagementUtility::addTCAcolumns('pages', [
    'tx_sillmstxt_exclude' => [
        'label' => $ll . 'pages.tx_sillmstxt_exclude',
        'description' => $ll . 'pages.tx_sillmstxt_exclude.description',
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'tx_sillmstxt_priority' => [
        'label' => $ll . 'pages.tx_sillmstxt_priority',
        'description' => $ll . 'pages.tx_sillmstxt_priority.description',
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'number',
            'size' => 5,
            'range' => ['lower' => 0, 'upper' => 100],
            'default' => 0,
        ],
    ],
    'tx_sillmstxt_description' => [
        'label' => $ll . 'pages.tx_sillmstxt_description',
        'description' => $ll . 'pages.tx_sillmstxt_description.description',
        'config' => [
            'type' => 'text',
            'rows' => 3,
        ],
    ],
    'tx_sillmstxt_markdown' => [
        'label' => $ll . 'pages.tx_sillmstxt_markdown',
        'description' => $ll . 'pages.tx_sillmstxt_markdown.description',
        'config' => [
            'type' => 'text',
            'rows' => 20,
            'fixedFont' => true,
            'enableTabulator' => true,
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;' . $ll . 'pages.tab, tx_sillmstxt_exclude, tx_sillmstxt_priority, tx_sillmstxt_description, tx_sillmstxt_markdown',
    '1,12'
);
