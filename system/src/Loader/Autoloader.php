<?php

declare(strict_types=1);

namespace Zolinga\System\Loader;

use const Zolinga\System\ROOT_DIR;

/**
 * Autoloader Class
 * 
 * This class is responsible for automatically loading PHP classes when they are needed.
 * 
 * @property-read array<string,string> $namespaces The namespaces that have been registered during runtime
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 */
class Autoloader implements \Zolinga\System\Events\ServiceInterface
{
    /**
     * The namespaces that have been registered during runtime
     * @example ['Zolinga\\System\\' => 'system/src/']
     * @var array<string, string>
     */
    private array $dynamicNamespaces = [];

    public function __construct()
    {
        $this->register();
    }

    /**
     * Magic getter method
     */
    public function __get(string $name): mixed
    {
        global $api;

        return match ($name) {
            'namespaces' => [...$api->manifest['autoload'], ...$this->dynamicNamespaces],
            default => throw new \Exception("Property $name does not exist.")
        };
    }

    /**
     * Register the autoloader
     *
     * This method registers the autoloader function with the PHP spl_autoload_register() function.
     * It allows the autoloader to be called whenever a class is not found.
     *
     * @return void
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Add a temporary autoloading namespace. It won't be saved and will persist only for the current script run.
     * 
     * This method adds a namespace to the list of namespaces that the autoloader can load.
     * 
     * @param string $namespace The namespace
     * @param string $path The path to the namespace
     * @return void
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->dynamicNamespaces[$namespace] = $path;
    }

    /**
     * Load the class file
     *
     * This method is called by the autoloader function and is responsible for including the class file.
     * It converts the class name to a file path and includes the file if it exists.
     *
     * @param string $className The name of the class to load
     * @return void
     */
    public function loadClass(string $className): void
    {
        $filePath = $this->convertClassNameToFilePath($className);

        if ($filePath && file_exists($filePath)) {
            require_once $filePath;
            // Check if the class exists after the file has been included
            if (
                !class_exists($className, false) &&
                !enum_exists($className, false) &&
                !interface_exists($className, false) &&
                !trait_exists($className, false)
            ) {
                throw new \Exception("Class $className not found in file $filePath.");
            }
        }
    }

    /**
     * Convert the class name to a file path
     *
     * This method converts the class name to a file path by replacing namespace separators with directory separators.
     * It also appends the ".php" file extension.
     *
     * @param string $className The name of the class
     * @return string The file path
     */
    private function convertClassNameToFilePath(string $className): string|false
    {
        $filePath = false;

        foreach ($this->namespaces as $namespace => $path) {
            if ($className === $namespace) {
                return ROOT_DIR . DIRECTORY_SEPARATOR . $path;
            } elseif (strpos($className, $namespace) === 0) {
                $filePath = $path . substr($className, strlen($namespace)) . '.php';
                $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $filePath);
                $filePath = ROOT_DIR . DIRECTORY_SEPARATOR . $filePath;
                return $filePath;
            }
        }
        return false;
    }
}
