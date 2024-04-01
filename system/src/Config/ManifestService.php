<?php

declare(strict_types=1);

namespace Zolinga\System\Config;

use Zolinga\System\Events\{Event, ServiceInterface};
use Zolinga\System\Types\ModuleStatesEnum;
use Zolinga\System\Config\Atom\{AtomInterface, ListenAtom, WebComponentAtom, EmitAtom};
use ArrayObject;
use const Zolinga\System\ROOT_DIR;

/**
 * This is the $api->manifest service that provides information about all installed modules.
 * In essence, it is a supermanifest that contains all zolinga.json files from all installed modules.
 * 
 * Works also as an array.
 * 
 *   print_r($api->manifest['listen']);
 *
 * @extends ArrayObject<string, mixed>
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-02
 */
class ManifestService extends ArrayObject implements ServiceInterface
{
    /**
     * Alphabetically sorted list of all discovered manifest file paths.
     * 
     * Note:
     * $this->manifestList contains all freshly discovered zolinga.json files.
     * $this['manifests'] contains all zolinga.json files from the cache file.
     * 
     * @var array<string>
     */
    public readonly array $manifestList;

    /**
     * Alphabetically sorted list of all discovered module names.
     * 
     * @var array<string>
     */
    public readonly array $moduleNames;

    /**
     * Alphabetically sorted list of all discovered module paths.
     * 
     * Paths are without trailing slashes relative to the ROOT_DIR.
     * 
     * Example: 
     * [
     *    "/modules/zolinga-intl",
     *    "/system",
     *    "/modules/zolinga-user",
     * ]
     * 
     * @var array<string>
     */
    public readonly array $modulePaths;

    /**
     * Alphabetically sorted list of all discovered module real paths.
     * 
     * Paths are without trailing slashes.
     * 
     * Example:
     * 
     * [
     *     "/var/www/html/modules/zolinga-intl",
     *    "/var/www/html/system",
     *    "/var/www/html/modules/zolinga-user",
     * ]
     * 
     * @var array<string>
     */
    public readonly array $moduleRealPaths;

    /**
     * List of signatures
     *
     * @var array<string, string> $currentSignatures in format array(manifestPath => md5)
     */
    private array $currentSignatures;
    private const SEARCH_PATH = ROOT_DIR . '/{modules/*,system,vendor/*}/zolinga.json';
    private bool $changed = false;

    /**
     * The mapping where to store what keys. Under normal circumstances
     * we don't need a lot of data so we do store them in separate files that
     * are loaded only when requested.
     *
     * Note: If the array of keys contains only one value then the stored file
     * will contain only that key. While if there are multiple keys the stored
     * value is an array of keys.
     * 
     * @var array<string, array<string>>
     */
    private const STORAGE_MAP = [
        ROOT_DIR . '/data/system/system.cache.json' => ["signatures", "manifests", "autoload", "listen"],
        ROOT_DIR . '/public/data/system/web-components.json' => ["webComponents"],
        ROOT_DIR . '/data/system/config.cache.json' => ["config"],
        ROOT_DIR . '/data/system/emit.cache.json' => ["emit"],
    ];

    /**
     * Atom classes
     *
     * @var array<string, string>
     */
    private const ATOM_CLASSES = [
        "listen" => Atom\ListenAtom::class,
        "webComponents" => Atom\WebComponentAtom::class,
        "emit" => Atom\EmitAtom::class,
    ];

    /**
     * Module states
     *
     * @var array<string, ModuleStatesEnum> $states the Key is the zolinga.json path.
     */
    private array $states = [];

    public function __construct()
    {
        // Remove ROOT_DIR from the search path
        $found = glob(self::SEARCH_PATH, GLOB_BRACE) ?: [];

        // Filter out all zolinga.json files inside a folders "*.example|*.bak|*.old|*.disabled"
        $found = array_filter($found, fn ($path) => !preg_match('/\.(example|bak|old|disabled)\/zolinga\.json$/', $path));
        $this->manifestList = (array) $this->canonicalize($found);
        $this->moduleNames = array_map(fn ($path) => basename(dirname($path)), $this->manifestList);
        $this->modulePaths = array_map(fn ($path) => dirname($path), $this->manifestList);
        $this->moduleRealPaths = array_map(fn ($path) => ROOT_DIR . $path, $this->modulePaths);

        parent::__construct([]);

        $this->calculateSignature();
        $this->determineStates();

        // Manifests changed
        if ($this->changed) {
            $this->refresh();
            $this->save();
        }
    }

    /**
     * Add an event listener to the system dynamicaly just for the current script run.
     * 
     * Example:
     * 
     *   $api->manifest->addListener(new ListenAtom([
     *      'event' => 'system:service:myService',
     *      'class' => MyService::class,
     *      'method' => 'myMethod',
     *      'priority' => 0.5,
     *      'origin' => ['remote']
     *    ]));
     *
     * @param ListenAtom $listener
     * @return void
     */
    public function addListener(ListenAtom $listener): void
    {
        $listeners = $this['listen'];
        $listeners[] = $listener;
        $this['listen'] = $this->sortByPriority($listeners, 'event');
    }

    /**
     * Return all subscription definitions
     *
     * Note: The pattern's type character '*' works as a wild card 0 or more characters.
     * 
     * @param Event $event
     * @param integer $limit
     * @param string $section listen|emit sections in zolinga.json
     * @return array<ListenAtom|EmitAtom>|ListenAtom|EmitAtom|null - if $limit = 1 return the first match, otherwise array of matches
     */
    public function findByEvent(Event $event, string $section = 'listen', int $limit = 0): array|ListenAtom|EmitAtom|null
    {
        $list = [];
        foreach ($this[$section] as $subscription) {
            /** @var ListenAtom|EmitAtom $subscription */
            if ($subscription->matchByEvent($event)) {
                $list[] = $subscription;
                if ($limit && count($list) >= $limit) {
                    break;
                }
            }
        }
        return $limit === 1 ? array_shift($list) : $list;
    }

    public function offsetGet($index): mixed
    {
        $val = isset($this[$index]) ? parent::offsetGet($index) : null;

        if (!empty($val)) {
            return $val;
        }

        // lazy load data
        foreach (self::STORAGE_MAP as $file => $keys) {
            if (!in_array($index, $keys)) continue;

            if (is_file($file)) {
                $json = file_get_contents($file) or throw new \Exception("Cannot read cache file " . $file);
                $merge = json_decode($json, true) or throw new \Exception("Invlid JSON format in file " . $file);
            } else {
                return []; // No file empty values
            }

            // if it is only one key then it contains the data directly
            // otherwise it contains an array of keys with their data.
            if (count($keys) === 1) {
                $merge = [$keys[0] => $merge];
            }

            foreach ($merge as $key => $val) {
                $this->offsetSet($key, $this->convertToAtoms($key, $val));
            }

            break;
        }

        return parent::offsetGet($index);
    }

    /**
     * Return array of Manifests base on the state filter.
     *
     * @param ModuleStatesEnum $stateFilter
     * @return array<string>
     */
    public function filterManifestList(ModuleStatesEnum $stateFilter): array
    {
        return array_keys(array_filter($this->states, fn ($state) => $state === $stateFilter));
    }

    public function getState(string $path): ModuleStatesEnum
    {
        if (!isset($this->states[$path])) throw new \InvalidArgumentException("Path not found: $path");
        return $this->states[$path];
    }

    // Create getter for $this->changed
    public function hasChanged(): bool
    {
        return $this->changed;
    }

    private function determineStates(): void
    {
        global $api;

        /**
         * All zolinga.json paths from the saved list and the current list
         * @var array<string>
         */
        $fullList = array_unique([...$this['manifests'], ...$this->manifestList]);

        foreach ($fullList as $path) {
            if (!isset($this['signatures'][$path])) {
                $this->states[$path] = ModuleStatesEnum::NEW;
            } elseif (!isset($this->currentSignatures[$path])) {
                $this->states[$path] = ModuleStatesEnum::REMOVED;
            } elseif ($this->currentSignatures[$path] !== $this['signatures'][$path]) {
                $this->states[$path] = ModuleStatesEnum::CHANGED;
            } else {
                $this->states[$path] = ModuleStatesEnum::UNCHANGED;
            }

            if ($this->states[$path] !== ModuleStatesEnum::UNCHANGED) {
                $this->changed = true;
                $api->log->info("system.manifest", "Module state changed: $path", [
                    'state' => $this->states[$path]->value,
                    'oldSignature' => $this['signatures'][$path] ?? null,
                    'newSignature' => $this->currentSignatures[$path] ?? null,
                ]);
            }
        }
    }

    private function calculateSignature(): void
    {
        $this->currentSignatures = [];
        foreach ($this->manifestList as $file) {
            $fn = ROOT_DIR . $file;
            $signature = [
                filemtime($fn),
                filesize($fn),
                filectime($fn),
            ];
            $this->currentSignatures[$file] = md5(implode(',', $signature));
        }
    }

    private function refresh(): void
    {
        $data = [
            "# About" => [
                "# Warning" => "This file is automatically generated by Zolinga. Do not edit.",
                "# About" => "This file is a cache containing all zolinga.json files. It gets automatically regenerated when zolinga.json files change.",
            ]
        ];

        // Init arrays:
        foreach (self::STORAGE_MAP as $file => $keys) {
            foreach ($keys as $key) {
                $data[$key] = [];
            }
        }

        // Reset the supermanifest
        $data["signatures"] = $this->currentSignatures;
        $data["manifests"] = array_map($this->canonicalize(...), $this->manifestList);

        // Find all ROOT_DIR/modules/*/zolinga.json files
        // Glob returns an array of paths that are sorted.
        foreach ($this->manifestList as $file) {
            $mergeJSON = file_get_contents(ROOT_DIR . $file) or throw new \Exception("Cannot read config file: $file");
            $merge = json_decode($mergeJSON, true) or throw new \Exception("Invalid JSON format in $file");
            $moduleName = basename(dirname($file));

            foreach (self::STORAGE_MAP as $keys) {
                foreach ($keys as $key) {
                    if ($key === 'autoload') {
                        $data[$key] = [...$data[$key], ...$this->canonicalizeAutoloads(dirname($file), $merge[$key] ?? null)];
                    } else {
                        $data[$key] = [...$data[$key], ...$this->convertToAtoms($key, $merge[$key] ?? [], $moduleName)];
                    }
                }
            }
        }

        // Sort by priorities or names...
        foreach (['listen' => 'event', 'webComponents' => 'tag'] as $key => $altSortKey) {
            // @phpstan-ignore-next-line
            $data[$key] = $this->sortByPriority($data[$key], $altSortKey);
        }

        $this->exchangeArray($data);
    }

    /**
     * Resolve paths relative to the module.
     *
     * @param string $baseDir
     * @param array<string,string>|null $autoloads 
     * @return array<string,string>
     */
    private function canonicalizeAutoloads(string $baseDir, ?array $autoloads): array
    {
        $autoloads = array_map(
            fn ($path) => $baseDir . '/' . $path,
            $autoloads ?? []
        );

        // Remove \ prefix from all array keys
        $autoloads = array_combine(array_map(fn ($path) => ltrim($path, '\\'), array_keys($autoloads)), $autoloads);

        $autoloads = (array) $this->canonicalize($autoloads);
        return $autoloads;
    }


    private function save(): void
    {
        $data = $this->getArrayCopy();
        foreach (self::STORAGE_MAP as $file => $keys) {
            $store = [];
            foreach ($keys as $key) {
                $store[$key] = $data[$key];
            }
            // Save the cache
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            if (count($keys) === 1) {
                $store = $store[reset($keys)];
            }

            file_put_contents($file, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                or throw new \Exception("Cannot write cache file " . $file);
        }

        $this->saveApiStub();
    }

    private function saveApiStub(): void
    {
        $invokes = [];
        $services = [];
        foreach ($this['listen'] as $listener) {
            // Search for all services
            if (str_starts_with($listener['event'], 'system:service:')) {
                $serviceName = substr($listener['event'], strlen('system:service:'));
                if (!isset($services[$serviceName])) {
                    $services[$serviceName] = "{$listener['class']} \${$serviceName};";

                    // It runs before autoloaders... :-( so we cannot use Reflections on classes yet.
                    //
                    // // Intelephense has problem with the __invoke() so we add the methods too.
                    // // Check if there is a __invoke method in $listener['class']
                    // $reflector = new \ReflectionClass($listener['class']);
                    // if ($reflector->hasMethod('__invoke')) {
                    //     // Build the public function __invoke(...params) {...} declaration as a string
                    //     $params = implode(", ", array_map(fn ($param) => "{$param->getType()} \${$param->getName()}", $reflector->getMethod('__invoke')->getParameters()));
                    //     $returnValue = $reflector->getMethod('__invoke')->getReturnType();
                    //     $invokes[$serviceName] = $reflector->getMethod('__invoke')->getDocComment() . "public function {$invokes[$serviceName]}({$params}): $returnValue {}";
                    // }
                }
            }
        }

        $commentText = " * @property " . implode("\n * @property ", $services);
        $serviceText = "public " . implode("\n\tpublic ", $services);
        // $invokesText = implode("\n", $invokes);
        $stamp = date('Y-m-d H:i:s');
        $content = <<<"EOT"
            <?php
            // This file is automatically generated by Zolinga. Do not edit.
            // It contains a list of all services and events that are available in the system.
            // It is used by the API to provide autocompletion and type checking.
            // @modified $stamp

            /**
            $commentText
             */
            namespace Zolinga\System;
            class ApiStub extends Api {
                {$serviceText}
            }
            \$api = new ApiStub();
            \$GLOBALS['api'] = new ApiStub();
            EOT;
        file_put_contents(ROOT_DIR . "/data/system/api.stub.php", $content);
    }

    /**
     * Strip the ROOT_DIR from the full path. 
     *
     * @param array<string>|string $path
     * @return array<string>|string
     */
    private function canonicalize(string|array $path): string|array
    {
        if (is_array($path)) {
            /**
             * @var array<string>
             */
            $ret = array_map($this->canonicalize(...), $path);
        } else {
            $ret = str_replace(ROOT_DIR, '', $path);
        }

        return $ret;
    }

    /**
     * Sort by 'priority' numeric key or by arbitrary key.
     *
     * @param array<ListenAtom|WebComponentAtom> $list
     * @param string|null $altSortKey
     * @return array<ListenAtom|WebComponentAtom>
     */
    private function sortByPriority(array $list, ?string $altSortKey = null): array
    {
        usort($list, function (array|AtomInterface $a, array|AtomInterface $b) use ($altSortKey) {
            $aPriority = $a['priority'] ?: 0.5;
            $bPriority = $b['priority'] ?: 0.5;
            if ($aPriority === $bPriority && $altSortKey) {
                return strcmp($a[$altSortKey], $b[$altSortKey]);
            }
            return $bPriority <=> $aPriority;
        });

        return $list;
    }

    /**
     * Convert array to Atoms
     *
     * @param string $key
     * @param array<array<string,mixed>> $list
     * @param string|null $moduleName During initial merging from modules we need to know the module name to resolve relative paths.
     * @return array<ListenAtom|WebComponentAtom|EmitAtom|array<string,mixed>>
     */
    private function convertToAtoms(string $key, array $list, ?string $moduleName = null): array
    {
        $className = self::ATOM_CLASSES[$key] ?? null;

        switch ($key) {
            case 'webComponents':
                // Canonicalize paths
                if ($moduleName) {
                    array_walk($list, function (array &$data) use ($moduleName) {
                        if (!str_starts_with($data['module'], '/')) {
                            $data['module'] = "/dist/{$moduleName}/{$data['module']}";
                        }
                    });
                }
                break;
        }

        return $className ? array_map(fn ($data) => new $className($data), $list) : $list;
    }
}
