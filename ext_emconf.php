<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4Dce',
    'description' => 'Extension with the Newt-Provider for Dce',
    'category' => 'be',
    'author' => 'Juergen Furrer',
    'author_email' => 'juergen@infonique.ch',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'newt' => '1.9.3-',
            'dce' => '2.8.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
