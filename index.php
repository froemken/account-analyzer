<?php

use StefanFroemken\AccountAnalyzer\Controller\WebController;

// Require Debug helper if DDEV environment, optional
if (getenv('IS_DDEV_PROJECT') == 'true') {
    // maybe enable errors
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/vendor/autoload.php';

$controller = new WebController();
$controller->handleRequest();
