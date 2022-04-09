<?php
defined('TYPO3') || die();

call_user_func(
    function ($extKey) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['Newt']['Implementation'][] = \Infonique\Newt4Dce\Newt\DceEndpoint::class;

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
            $extKey,
            'Configuration/TypoScript',
            'Newt4Dce Configuration'
        );
    },
    $_EXTKEY ?? 'newt4dce'
);
