<?php

defined('TYPO3') or die();

// Global Fluid namespace so templates can use <scss:require key="form" />
// without having to declare xmlns each time.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['scss'][] = 'WapplerSystems\\WsScssComponents\\ViewHelpers';