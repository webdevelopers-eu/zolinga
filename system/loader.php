<?php
/**
 * It is responsible for bootstrapping the system and initializing all services.
 * 
 * @package Zolinga\System
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-02
 */
declare(strict_types=1);

namespace Zolinga\System;

error_reporting(E_ALL);
if (defined('Zolinga\System\ROOT_DIR')) {
    throw new \Exception('Zolinga\System\ROOT_DIR is already defined. Was Zolinga already loaded? Cannot bootstrap the system.');
}

/**
 * The root directory of the system. Contains trailing slash removed.
 */
define('Zolinga\System\ROOT_DIR', dirname(__DIR__, 1));
define('Zolinga\System\START_TIME', microtime(true));

require(__DIR__.'/src/Loader/Bootstrap.php');

// Bootstrap the system.
(function () { // Anonymous function to prevent global variable pollution
    $bootstrap = new Loader\Bootstrap();
    $bootstrap->initSession();
    $bootstrap->initBaseAutoloader();
    $bootstrap->initApi();

    // Starts in offline mode storing all messages in memory buffer.
    $bootstrap->initLogger();
    
    $bootstrap->initManifest();

    // $api->log to save to files requires $api->config which required $api->manifest
    $bootstrap->startLogger(); 
    
    $bootstrap->initAutoloader();
    $bootstrap->initFilesystem();
    $bootstrap->initDebug();
    $bootstrap->initModules();
})();
