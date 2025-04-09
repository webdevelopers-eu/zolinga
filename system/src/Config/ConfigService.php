<?php
declare(strict_types=1);

namespace Zolinga\System\Config;

use Zolinga\System\Events\RequestEvent;
use Zolinga\System\Events\RequestResponseEvent;
use Zolinga\System\Events\ServiceInterface;
use const Zolinga\System\ROOT_DIR;

/**
 * Configuration service that merges all zolinga.json "config" sections from all the modules
 * and then merges in config/global.json and config/local.json files on top of it.
 * 
 * You can access the configuration as an array.
 * 
 * Example:
 * 
 *   $api->config['db']['host']
 *   $api->config['wiki']['password']
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 */
class ConfigService extends ConfigArrayObject implements ServiceInterface
{
    private array $dynamicConfig = []; 

    public function __construct()
    {
        parent::__construct();
        $this->loadData($this->mergeConfigs());
    }

    public function onConfig(RequestEvent $event): void
    {
        $this->merge($event->request);
        if ($event instanceof RequestResponseEvent) {
            $event->response['config'] = $this;
        }
        $event->setStatus($event::STATUS_OK, 'Configuration merged.');
    }

    /**
     * Merge in dynamic configuration. Note that this is not persistent and is valid only for the current request.
     * 
     * You can add dynamic config from command line using the event `config`: 
     * 
     * `bin/zolinga config '{"db": {"host": "localhost"}}'`
     * `bin/zolinga config --db.host=localhost`
     *
     * @param array $config
     * @return void
     */
    public function merge(array $config): void
    {
        $this->dynamicConfig = array_replace_recursive($this->dynamicConfig, $config);
        $this->loadData($this->mergeConfigs());
    }

    // Prevent modification of the config
    public function offsetSet($index, $newval): void
    {
        throw new \Exception("Configuration object \$api->config is read-only.");
    }

    /**
     * Take all module config sections from $api->manifest['config'] and slap
     * global.json and local.json on top of it.
     *
     * @return array<mixed>
     */
    private function mergeConfigs(): array
    {
        global $api;

        $config = $api->manifest['config'] ?? [];

        foreach ([ROOT_DIR . '/data/system/config.cache.json', ROOT_DIR . '/config/global.json', ROOT_DIR . '/config/local.json'] as $configFile) {
            if (file_exists($configFile)) {
                $configObject = new ConfigArrayObject($configFile, ConfigArrayObject::FLAGS_REMOVE_COMMENTS);
                $config = array_replace_recursive($config, $configObject->getArrayCopy());
            }
        }

        $config = array_replace_recursive($config, $this->dynamicConfig);

        return $config;
    }
}
