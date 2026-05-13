<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WS Components',
    'description' => 'Modular SCSS and JS component loader. Extensions request a key (e.g. "form" or "glightbox"); the active site package maps it to files, scss is compiled per key, js is enqueued via AssetCollector.',
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
