<?php

declare(strict_types=1);

namespace Zolinga\System\Loader;

use const Zolinga\System\IS_HTTPS;
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
    public function checkEnvironment() {
        // 8.2.0
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            throw new \Exception('PHP 8.2.0 or higher is required.');
        }

        // Check rw permissions on
        // ROOT_DIR . '/data/'
        // ROOT_DIR . '/public/data/'
        // ROOT_DIR . '/public/dist/'
        foreach(['/data/', '/public/data/', '/public/dist/'] as $dir) {
            $path = ROOT_DIR . $dir;
            if (!is_writable($path)) {
                $this->throwNotWritableException($path);
            }
        }

    }

    private function throwNotWritableException(string $path): void {
        $type = is_dir($path) ? 'directory' : 'file';

        $message = "The $type $path is not writable. Please check the permissions. ";
        $message .= "The $type should be writable by the user " . $this->getOsUser() . ". ";

        $perms = fileperms($path);
        $permsOcta = substr(sprintf('%o', $perms), -4);
        $permsSymbolic = $this->getPermSymbolic($perms);
        $owner = $this->getOsUser(fileowner($path), filegroup($path));
        $message .= "The $type has permissions $permsSymbolic ($permsOcta) and is owned by $owner.";

        throw new \Exception($message);
    }

    /**
     * Return POSIX USER:GORUP string.
     *
     * @access private
     * @param int $userId optional user id, otherwise current user id will be used
     * @param int $groupId optional group id, otherwise current group id will be used
     * @return string in format {USER}:{GROUP}
     */
    private function getOsUser(?int $userId=null, ?int $groupId=null): string {
        $u=posix_getpwuid($userId === null ? posix_geteuid() : $userId);
        $g=posix_getgrgid($groupId === null ? posix_getegid() : $groupId);
        return $u['name'].':'.$g['name'];
    }

    /**
     * Return `ls` like file info.
     *
     * @access private
     * @param string $filename
     * @return string
     */
    private function getPermSymbolic(int $perms):string {
        if (($perms & 0xC000) == 0xC000) { // Socket
            $info='s';
        } elseif (($perms & 0xA000) == 0xA000) { // Symbolic Link
            $info='l';
        } elseif (($perms & 0x8000) == 0x8000) { // Regular
            $info='-';
        } elseif (($perms & 0x6000) == 0x6000) { // Block special
            $info='b';
        } elseif (($perms & 0x4000) == 0x4000) { // Directory
            $info='d';
        } elseif (($perms & 0x2000) == 0x2000) { // Character special
            $info='c';
        } elseif (($perms & 0x1000) == 0x1000) { // FIFO pipe
            $info='p';
        } else { // Unknown
            $info='u';
        }

        // Owner
        $info.=(($perms & 0x0100) ? 'r' : '-');
        $info.=(($perms & 0x0080) ? 'w' : '-');
        $info.=(($perms & 0x0040) ?
        (($perms & 0x0800) ? 's' : 'x' ) :
        (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info.=(($perms & 0x0020) ? 'r' : '-');
        $info.=(($perms & 0x0010) ? 'w' : '-');
        $info.=(($perms & 0x0008) ?
        (($perms & 0x0400) ? 's' : 'x' ) :
        (($perms & 0x0400) ? 'S' : '-'));

        // World
        $info.=(($perms & 0x0004) ? 'r' : '-');
        $info.=(($perms & 0x0002) ? 'w' : '-');
        $info.=(($perms & 0x0001) ?
        (($perms & 0x0200) ? 't' : 'x' ) :
              (($perms & 0x0200) ? 'T' : '-'));

        return $info;
    }

    public function initSession(): void
    {
        global $api;

        // Make sure SESSION cookie is secure, uses HTTPOnly and SameSite=Strict
        session_set_cookie_params([
            'secure' => IS_HTTPS, // set to true when HTTPS is available
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                $api->log->error('system', 'Failed to start session.');
                throw new \Exception('Cannot start session.');
            }
        }

        if (isset($_REQUEST['destroy'])) {
            $api->log->info('system', 'Session destroyed by request.');
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

        define('ZOLINGA_DEBUG', $match);

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
