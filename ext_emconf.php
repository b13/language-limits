<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Language Limits',
    'description' => 'Limit in which languages pages and content elements are shown, per record and per language, on top of the fallback chain of the site configuration.',
    'category' => 'fe',
    'state' => 'beta',
    'author' => 'b13 GmbH',
    'author_company' => 'b13 GmbH',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-15.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
