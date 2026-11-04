<?php

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', [
    'tx_languagelimits' => [
        'exclude' => true,
        'label' => 'language_limits.db:field.label',
        'description' => 'language_limits.db:field.description.pages',
        'displayCond' => 'FIELD:sys_language_uid:=:0',
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'json',
            'renderType' => 'languageLimits',
        ],
    ],
]);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'tx_languagelimits',
    '',
    'after:l18n_cfg'
);
