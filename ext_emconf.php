<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WS SCSS Components',
    'description' => 'Modular SCSS component loader. Extensions request a key (e.g. "form"); the active main template maps it to SCSS files, ws_scss compiles per-key.',
    'category' => 'fe',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => 'WapplerSystems',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
            'ws_scss' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];