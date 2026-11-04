<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1793780000] = [
    'nodeName' => 'languageLimits',
    'priority' => 40,
    'class' => \B13\LanguageLimits\Form\Element\LanguageLimitsElement::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['language_limits']
    = \B13\LanguageLimits\Hooks\NormalizeLanguageLimits::class;
