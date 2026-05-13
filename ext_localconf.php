<?php

defined('TYPO3') or die();

// Global Fluid namespaces so templates can use the ViewHelpers without
// having to declare xmlns each time.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['scssComponents'][] = 'WapplerSystems\\WsComponents\\Scss\\ViewHelpers';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['jsComponents'][] = 'WapplerSystems\\WsComponents\\Js\\ViewHelpers';
