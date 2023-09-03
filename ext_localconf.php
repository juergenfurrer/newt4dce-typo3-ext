<?php
defined('TYPO3') || die();

call_user_func(
    function ($extKey) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['Newt']['Implementation'][] = \Swisscode\Newt4Dce\Newt\DceEndpoint::class;
    },
    'newt4dce'
);
