<?php

declare(strict_types=1);

namespace Zolinga\System\Skeletons;

use Zolinga\System\Events\{CliRequestResponseEvent, ListenerInterface};
use const Zolinga\System\ROOT_DIR;
use Exception;

/**
 * This class is a skeleton for a module.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-03-25
 */
class ApacheSkeleton implements ListenerInterface
{
    /**
     * Print the Apache host skeleton.
     * 
     *   bin/zolinga skeleton:apache [--serverName=example.com]
     *
     * @param CliRequestResponseEvent $event
     * @return void
     */
    public function onApache(CliRequestResponseEvent $event): void
    {
        $content = file_get_contents('module://system/skeletons/apache/vhost.conf')
            or throw new Exception('Failed to read the Apache vhost skeleton.');

        if (isset($event->request['help']) || !$event->request || !isset($event->request['serverName'])) {
            $this->printHelp();
            $event->preventDefault(); // prevent default cli output
            return;
        }


        $vars = [
            '{{serverName}}' => $event->request['serverName'],
            '{{documentRoot}}' => ROOT_DIR . '/public',
            '{{errorLogPrefix}}' => $event->request['serverName'],
            '{{ip}}' => $event->request['ip'] ?? '*',
            '{{port}}' => $event->request['port'] ?? '80',
        ];

        $content = str_replace(array_keys($vars), $vars, $content);

        echo $content . PHP_EOL;

        $event->preventDefault(); // prevent default cli output
    }

    /**
     * Print the help message.
     * 
     * @return void
     */
    private function printHelp(): void
    {
        echo <<<HELP
            Usage: bin/zolinga skeleton:apache --serverName=SERVER_NAME [--ip=IP_ADDRESS] [--port=PORT]
        
            Options:
                --serverName=SERVER_NAME  The server name for the virtual host.
                --help                    Display this help message.
                --ip=IP_ADDRESS           The IP address for the virtual host. Default is '*'
                --port=PORT               The port for the virtual host. Default is '80'

            Description:

                This command prints the Apache virtual host skeleton. You can use it to create a new virtual host configuration file.

            
            HELP;
    }
}
