<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4Dce',
    'description' => 'Extension with the Newt-Provider for Dce',
    'category' => 'be',
    'author' => 'infonique, furrer',
    'author_email' => 'info@infonique.ch',
    'state' => 'alpha',
    'clearCacheOnLoad' => true,
    'version' => '1.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'newt' => '2.1.0-',
            'dce' => '2.8.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
