<?php

declare(strict_types=1);

namespace Zolinga\System\Loader;
use const Zolinga\System\ROOT_DIR;
use Zolinga\System\Events\Event;

/**
 * This class is responsible for executing bootstrap steps that are required to start the system.
 * The order of the steps is controlled by loader.php
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-02
 */
class Bootstrap
{
    public function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_REQUEST['destroy'])) {
            session_destroy();
            session_start();
        }
    }

    public function initBaseAutoloader(): void
    {
        $namespace = 'Zolinga\\System\\';
        $path = ROOT_DIR . '/system/src/';

        spl_autoload_register(function(string $className) use ($namespace, $path) : void {
            if (strpos($className, $namespace) === 0) {
                $filePath = $path . substr($className, strlen($namespace)) . '.php';
                $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $filePath);
                if (file_exists($filePath)) {
                    require($filePath);
                }
            }
        });
    }

    /**
     * Creates the global $api object that is the key to everything.
     */
    public function initApi(): void
    {
        $GLOBALS['api'] = new \Zolinga\System\Api();
    }

    /**
     * The $api->log service starts in offline mode storing all messages in
     * memory buffer. $api->log->online() is required to start logging to the file.
     * 
     * But Logger requires $api->config (which in turn requires $api->manifest, which needs $api->log)
     */
    public function initLogger(): void
    {
        global $api;

        $api->registerService('log', new \Zolinga\System\Logger\LogService());
    }

    public function startLogger(): void 
    {
        global $api;
        $api->log->online();
    }

    /**
     * Initializes the autoloader and registers it with the $api object as $api->autoloader .
     */
    public function initAutoloader(): void
    {
        global $api;

        require(__DIR__ . '/Autoloader.php');

        // Initialize the autoloader
        // Hint the class location to PHPStan
        $api->registerService('autoloader', new \Zolinga\System\Loader\Autoloader());

        // Support for composer autoloader
        if (file_exists(ROOT_DIR . '/vendor/autoload.php')) {
            require(ROOT_DIR . '/vendor/autoload.php');
        }
    }

    /**
     * Initializes the manifest object that holds the merged info 
     * from all module and system manifest.json files
     * and registers it with the $api object as $api->manifest .
     */
    public function initManifest(): void
    {
        global $api;
        $manifest = new \Zolinga\System\Config\ManifestService();

        $api->registerService('manifest', $manifest);
    }

    /**
     * Initializes the filesystem object that handles all filesystem operations
     * and registers it with the $api object as $api->fs .
     */
    public function initFilesystem(): void {
        global $api;
        // Lazy load the filesystem service.
        // When the service loads it registers also the Zolinga URI schemes for accessing files. 
        /** @phpstan-ignore-next-line */
        $api->fs;
    }

    public function initDebug(): void
    {
        global $api;

        $ips = $api->config['debug']['allowedIps'] ?? ["*", "cli", "127.0.0.1"];

        $match = array_reduce($ips, function(bool $match, string $ip): bool {
            if ($match) return true; // matches
            $regExp = '@^' . str_replace(["\\*", "\\?"], [".+", "."], preg_quote($ip, '@')) . '$@';
            return (bool) preg_match($regExp, $_SERVER['REMOTE_ADDR'] ?? php_sapi_name());
        }, false);

        if ($match) {
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }
    }

    /**
     * When the manifest has changed, it dispatches the system:install event.
     *
     * @return void
     */
    public function initModules(): void
    {
        global $api;

        if ($api->manifest->hasChanged()) {
            $api->dispatchEvent(new Event('system:install', Event::ORIGIN_INTERNAL));
        }
    }
}
