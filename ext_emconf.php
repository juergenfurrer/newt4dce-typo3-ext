<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4Dce',
    'description' => 'Extension with the Newt-Provider for Dce',
    'category' => 'be',
    'author' => 'SwissCode',
    'author_email' => 'info@swisscode.sk',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
            'newt' => '3.0.0-3.99.99',
            'dce' => '2.9.0-3.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
