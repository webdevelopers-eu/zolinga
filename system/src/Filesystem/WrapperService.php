<?php

declare(strict_types=1);

namespace Zolinga\System\Filesystem;

use Zolinga\System\Events\ServiceInterface;
use const Zolinga\System\ROOT_DIR;

/**
 * Class that registers as stream wrapper to allow access to 
 * 
 * - module://{module-name}/ - module root directory, e.g. ROOT_DIR . '/modules/{module-name}/'
 * - public://{module-name}/ - module public data directory, e.g. ROOT_DIR . '/public/data/{module-name}/'
 * - private://{module-name}/ - module private data directory, e.g. ROOT_DIR . '/data/{module-name}/'
 * - dist://{module-name}/ - module dist data directory, e.g. ROOT_DIR . '/public/dist/{module-name}/'
 * 
 * Accessible as the $api->fs service.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-02
 */
class WrapperService implements ServiceInterface
{
    const SCHEMES = ['module', 'public', 'private', 'dist', 'wiki'];

    /**
     * Module locations indexed by module name.
     *
     * @var array<string, array{dirname: string, realpath: string}> $moduleLocations
     */
    private array $moduleLocations = [];

    public function __construct()
    {
        global $api;

        $oldCwd = getcwd();
        chdir(ROOT_DIR);

        foreach ($api->manifest->manifestList as $manifestFile) {
            $realpath = realpath(dirname(ROOT_DIR . $manifestFile));
            if ($realpath !== false) {
                $this->moduleLocations[basename(dirname($manifestFile))] = [
                    "dirname" => dirname($manifestFile),
                    "realpath" => $realpath
                ];
            }
        }

        foreach (self::SCHEMES as $scheme) {
            stream_wrapper_register($scheme, Wrapper::class);
        }

        chdir($oldCwd ?: ROOT_DIR);
    }

    /**
     * Parses the path and returns the module name where is the path located.
     *
     * @param string $pathOrURI
     * @return string|false
     */
    public function getModuleNameByPath(string $pathOrURI): string|false
    {
        $uri = $this->toZolingaUri($pathOrURI);
        return parse_url($uri ?: 'unknown:///', PHP_URL_HOST) ?: false;
    }

    /**
     * Converts a given Zolinga scheme to a corresponding file path.
     * 
     * Example:
     * 
     * $api->fs->toPath('module://my-module/file.txt');  // Returns '/var/www/html/modules/my-module/file.txt'
     * $api->fs->toPath('public://my-module/file.txt');  // Returns '/var/www/html/public/data/my-module/file.txt'
     * $api->fs->toPath('private://my-module/file.txt'); // Returns '/var/www/html/data/my-module/file.txt'
     * $api->fs->toPath('dist://my-module/file.txt');  // Returns '/var/www/html/modules/my-module/install/dist/file.txt'
     *
     * @param string $uri The scheme to convert into file system path.
     * @return string|false The corresponding file path and false if the scheme is not recognized.
     */
    public function toPath(string $uri): string|false
    {
        if ($this->isPath($uri)) {
            return $uri;
        }

        $urlScheme = parse_url($uri, PHP_URL_SCHEME);
        $urlPath = parse_url($uri, PHP_URL_PATH);
        $urlHost = parse_url($uri, PHP_URL_HOST);

        return match ($urlScheme) {
            'wiki' => isset($this->moduleLocations[$urlHost]) ? ROOT_DIR . $this->moduleLocations[$urlHost]['dirname'] . '/wiki' . $urlPath : false,
            'module' => isset($this->moduleLocations[$urlHost]) ? ROOT_DIR . $this->moduleLocations[$urlHost]['dirname'] . $urlPath : false,
            'public' => ROOT_DIR . '/public/data/' . $urlHost . $urlPath,
            'private' => ROOT_DIR . '/data/' . $urlHost . $urlPath,
            'dist' => ROOT_DIR . '/public/dist/' . $urlHost . $urlPath,
            default => false
        };
    }

    /**
     * Converts a given file path to a scheme.
     * 
     * Example:
     * 
     * $api->fs->toZolingaUri('/var/www/html/modules/my-module/file.txt');  // Returns 'module://my-module/file.txt'
     * $api->fs->toZolingaUri('/var/www/html/public/data/my-module/file.txt');  // Returns 'public://my-module/file.txt'
     * $api->fs->toZolingaUri('/var/www/html/data/my-module/file.txt'); // Returns 'private://my-module/file.txt'
     * $api->fs->toZolingaUri('/var/www/html/modules/my-module/install/dist/file.txt');  // Returns 'dist://my-module/file.txt'
     *
     * @param string $path The file path to convert into Zolinga URI
     * @return string|false The converted scheme or false if no matching scheme is found.
     */
    public function toZolingaUri(string $path): string|false
    {
        if ($this->isZolingaUri($path)) {
            return $path;
        }

        $scheme = match (true) {
            str_starts_with($path, ROOT_DIR . '/public/data/') => substr_replace($path, 'public://', 0, strlen(ROOT_DIR . '/public/data/')),
            str_starts_with($path, ROOT_DIR . '/data/') => substr_replace($path, 'private://', 0, strlen(ROOT_DIR . '/data/')),
            str_starts_with($path, ROOT_DIR . '/public/dist/') => substr_replace($path, 'dist://', 0, strlen(ROOT_DIR . '/public/dist/')),
            default => $this->path2ModuleUri($path)
        };

        return $scheme ?: false;
    }

    /**
     * Convert a path or Zolinga URI into standard URL in case that the path is in the public directory.
     *
     * @param string $mixed system path or Zolinga URI
     * @return string|false URL or false if the path is not in the public directory.
     */
    public function toUrl(string $mixed): string|false
    {
        // Convert to scheme if it is a path
        $uri = ($this->isZolingaUri($mixed) ? $mixed : $this->toZolingaUri($mixed)) ?: $mixed;

        // Only "public://" and "dist://" schemes are allowed to be converted to URL
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if (!in_array($scheme, ['public', 'dist'])) {
            return false;
        }

        $module = parse_url($uri, PHP_URL_HOST);
        $path = parse_url($uri, PHP_URL_PATH);

        return match ($scheme) {
            'public' => '/data/' . $module . $path,
            'dist' => '/dist/' . $module . $path,
            default => false
        };
    }

    private function isZolingaUri(string $mixed): bool
    {
        return str_contains($mixed, '://') && in_array(parse_url($mixed, PHP_URL_SCHEME), self::SCHEMES);
    }

    private function isPath(string $mixed): bool
    {
        return !str_contains($mixed, '://');
    }

    private function path2ModuleUri(string $path): string|false
    {
        if ($this->isZolingaUri($path)) {
            $path = $this->toPath($path);
        }

        $oldCwd = getcwd();
        chdir(ROOT_DIR);

        $ret = false;

        // Resolve potentionally relative path to absolute path
        // get first existing parent directory
        $realParent = $path;
        $nonExistentParts = [];
        while (!($realpath = (string) realpath($realParent ?: ''))) {
            if (!$realParent) {
                break;
            }
            array_unshift($nonExistentParts, basename($realParent));
            $realParent = dirname($realParent);
        }

        if ($realpath) {
            foreach ($this->moduleLocations as $module => ["realpath" => $moduleRealpath, "dirname" => $moduleDirname]) {
                if (str_starts_with((string) $realpath, $moduleRealpath) !== false) {
                    $path = '/' . substr($realpath, strlen($moduleRealpath)) . implode('/', $nonExistentParts);
                    if (preg_match('@^/wiki(/|$)$@', $path)) {
                        $ret = 'wiki://' . $module . substr($path, 5);
                    } else {
                        $ret = 'module://' . $module . $path;
                    }
                    break;
                }
            }
        }

        chdir($oldCwd ?: ROOT_DIR);
        return $ret;
    }

    public function __destruct()
    {
        foreach (self::SCHEMES as $scheme) {
            stream_wrapper_unregister($scheme);
        }
    }
}
