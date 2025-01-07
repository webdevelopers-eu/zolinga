<?php

declare(strict_types=1);

namespace Zolinga\System\Gates;

use Zolinga\System\Events\CliRequestResponseEvent;
use const Zolinga\System\ROOT_DIR;
use const Zolinga\System\START_TIME;

/**
 * This is a CLI script for the Zolinga system. It is called from bin/zolinga 
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2021-08-20
 */
class Cli
{

    private int $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Parsed arguments from the command line.
     * 
     * @var array<mixed> $parsedArgs 
     */
    private array $parsedArgs = [["type" => "*options*", "params" => []]];

    /**
     * Options from the command line.
     * 
     * This should include the complete list of all supported options with their default values.
     *
     * @var array<string, string|false>
     */
    private array $options = [
        // Show help message
        "help" => false,
        // Print pure JSON format with responses joined in one array of responses. 
        "json" => false,
        // Print only the first response in JSON format - implies --json
        "single" => false,
        // Run a script
        "execute" => false,
        // Eval a PHP
        "eval" => false,
        // debugging
        "xdebug" => false,
        // Debug mode
        "xdebug-mode" => "debug,develop", // ,profile",
        // spun php server
        "server" => false,
        // Supress default output
        "quiet" => false,
        // Timing
        "timing" => false,
    ];

    /**
     * Option aliases - short versions
     *
     * @var array<string, string> $optionAliases
     */
    private array $optionAliases = [
        "h" => "help",
        "j" => "json",
        "s" => "single",
        "x" => "execute",
        "e" => "eval",
        "q" => "quiet",
        "t" => "timing"
    ];

    /**
     * List of dispatched events.
     *
     * @var array<CliRequestResponseEvent> $events
     */
    private array $events = [];

    /**
     * Parse the arguments from the command line and dispatch the events.
     *
     * @param array<string> $args you will usually need to pass the global $argv variable here.
     * @return void
     */
    public function run(array $args): void
    {
        $timing = ['Bootstrap' => microtime(true)];
        $this->parseCliArguments($args);
        $this->parseOptions();

        if (!count($this->parsedArgs) && !count(array_filter($this->options))) {
            $this->printError("No events or options provided. Use --help to see the help message.\n");
            return;
        }

        if ($this->options['help']) {
            $this->printHelp();
        }

        if ($this->options['server']) {
            $this->runServer();
        }

        if ($this->options['xdebug']) {
            $this->runDebug();
        }

        if ($this->options['execute']) {
            $script = $this->options['execute'];
            if (!is_string($script) || !file_exists($script)) {
                throw new \Exception("Script $script does not exist.");
            }
            (function ($script) {
                global $api;
                require($script);
            })($script);
        }

        if ($this->options['eval']) {
            $eval = $this->options['eval'] ?: '';
            (function ($eval) {
                global $api;
                eval($eval);
            })($eval);
        }

        $timing['Parsing options'] = microtime(true);
        $this->dispatchEvents();
        $timing['Processing events'] = microtime(true);

        if ($this->options['timing']) {
            $last = START_TIME;
            foreach ($timing as $key => $time) {
                $this->printError("🕒 $key: " . round($time - $last, 4) . "s");
                $last = $time;
            }
            $this->printError("🕒 Total: " . round($last - START_TIME, 4) . "s");
        }

        if ($this->options['quiet']) {
            // Do not print anything
        } elseif ($this->options['json'] || $this->options['single']) {
            $this->printResponsesJSON();
        } else {
            $this->printResponses();
        }
    }

    private function runDebug(): void
    {
        list($host, $port) = $this->parseHostPort($this->options['xdebug'], "127.0.0.1", 9003);

        // php -dxdebug.mode=debug,develop,profile,trace -dxdebug.start_with_request=yes  
        // Enable xdebug
        ini_set('xdebug.mode', $this->options['xdebug-mode']); // 'debug,develop,profile,trace'
        ini_set('xdebug.start_upon_error', 'yes');
        ini_set('xdebug.client_host', $host);
        ini_set('xdebug.client_port', $port);

        // Set xdebug.output_dir to writeable directory
        if (ini_get('xdebug.output_dir') == "") {
            ini_set('xdebug.output_dir', sys_get_temp_dir());
        }

        //xdebug_start_trace();
        if (function_exists('xdebug_connect_to_client')) {
            \xdebug_connect_to_client();
            $this->printError("🐞 Xdebug is enabled. Output dir: " . ini_get('xdebug.output_dir') . ", mode: " . ini_get('xdebug.mode'));
        } else {
            $this->printError("🐞 ERROR: Xdebug is not available.");
        }
    }

    private function printError(string $message): void
    {
        file_put_contents("php://stderr", "» $message\n");
    }

    private function runServer(): void
    {
        global $api;

        $phpParams = [];

        // Add xdebug options
        if ($this->options['xdebug']) {
            list($dbHost, $dbgPort) = $this->parseHostPort($this->options['xdebug'], "127.0.0.1", 9003);
            $phpParams[] = "-dxdebug.mode=" . escapeshellarg($this->options['xdebug-mode']);
            $phpParams[] = "-dxdebug.start_with_request=yes";
            $phpParams[] = "-dxdebug.client_host=$dbHost";
            $phpParams[] = "-dxdebug.client_port=$dbgPort";
        }

        list($host, $port) = $this->parseHostPort($this->options['server'], "0.0.0.0", 8888);
        $phpParams[] = "-S $host:$port";
        $phpParams[] = "-t " . escapeshellarg(ROOT_DIR . "/public/");

        $cmd = PHP_BINARY . " " . implode(" ", $phpParams);

        $httpHost = preg_match("/^(0\.0\.0\.0|127\.0\.0\.1|localhost)$/", "$host") ? "localhost" : $host;
        $wiki = "http://$httpHost:$port" . $api->config['wiki']['urlPrefix'];
        $this->printError(<<<"EOT"
            Listening on $host:$port

            --------------------------------------------------------------------------------
            Now you can open your browser and go to $wiki
            --------------------------------------------------------------------------------


            $cmd

            EOT);

        // Make sure when user exits this script, the server is stopped
        passthru($cmd);
        echo "WAITING\n";
    }

    /**
     * Parse text "", "host", "host:port" int array($host, port)
     *
     * @param string $string
     * @param string $defaultHost
     * @param string $defaultPort
     * @return array{0: string, 1: int} [host, port]
     */
    private function parseHostPort(string|bool $string, string $defaultHost, string|int $defaultPort): array
    {
        $host = $defaultHost;
        $port = $defaultPort;
        $parts = !is_string($string) ? [] : explode(":", $string);

        if (is_bool($string)) { // cli parameter without value is parsed as true
            return [$host, (int) $port];
        } elseif (count($parts) == 1 && is_numeric($parts[0])) {
            $port = $parts[0];
        } elseif (count($parts) == 2) {
            $host = $parts[0];
            $port = $parts[1];
        } else {
            throw new \Exception("Invalid host:port format. Expected: '[HOST[:PORT]]', got: " . json_encode($string));
        }
        return [$host, (int) $port];
    }

    /**
     * Print the responses in some human readable format. This output may change so do not rely on parsing it.
     * If you want to parse responses use the --json or --first option to print only the responses in JSON format.
     * 
     * @return void
     */
    private function printResponses(): void
    {
        /** @var CliRequestResponseEvent $event */
        foreach ($this->events as $event) {
            if ($event->isDefaultPrevented()) {
                continue;
            }
            $this->printError("$event");
            echo json_encode($event, $this->jsonOptions) . "\n";
        }
    }

    /**
     * Print responses in JSON format.
     *
     * @return void
     */
    public function printResponsesJSON(): void
    {
        if ($this->options['single']) { // Single request only
            /** @var ?CliRequestResponseEvent $firstEvent */
            $firstEvent = array_shift($this->events);

            // For single request only, we will print errors to stdout
            if (!$firstEvent) {
                $this->printError("No response received.\n\n");
            } elseif ($firstEvent->status !== $firstEvent::STATUS_OK) {
                $this->printError("Error #{$firstEvent->status->value} {$firstEvent->message}\n\n");
            }

            $response = $firstEvent->response;
            $selector = ($this->options['single']);
            if ($selector !== true) { // is not -s or --single without selector
                foreach (explode('.', "$selector") as $key) {
                    if (!isset($response[$key])) {
                        throw new \Exception("Key \"$key\" ($selector) not found. Only keys " .
                            "\"" . implode("\", \"", array_keys($response)) . "\" " .
                            "are allowed to select values in response: " .  json_encode($response, $this->jsonOptions));
                    }
                    $response = $response[$key];
                }
            }
            if (is_scalar($response)) {
                echo $response . "\n";
            } else {
                echo json_encode($response, $this->jsonOptions) . "\n";
            }
        } else {
            $responseAll = array_map(fn ($event) => $event->response, $this->events);
            echo json_encode($responseAll, $this->jsonOptions) . "\n";
        }
    }

    /**
     * Print the help message.
     * 
     * @todo this should extend to display help from modules as well.
     *
     * @return void
     */
    private function printHelp(): void
    {
        echo <<<EOT
            Zolinga CLI

            Usage: zolinga [options] [event [params]] [event [params]] ...

            options

                --help, -h  Show this help message

                --json, -j
                
                    Print pure JSON format with responses joined in one array of responses.


                --single, -s, --single=SELECTOR 
                
                    Print only the first response in JSON format - implies --json
                    If SELECTOR is provided (JSON dot-notation), it will print only the selected part 
                    of the response.If the printed response is not a scalar, it will be printed in JSON format
                    otherwise decoded to a string.

                    If no SELECTOR is provided the whole response part of the first response is printed.

                --execute=/path/script.php, -x=/path/script.php

                    Run a script.

                --eval="echo 'Hello world';", -e="echo 'Hello world';"

                    Eval a PHP.

                --xdebug[=[HOST:]PORT]
                    
                        Enable xdebug by calling xdebug_connect_to_client().
                        Default: 127.0.0.1:9003

                --xdebug-mode=MODES
                    
                        Set xdebug.mode value. Only valid if --xdebug is set.
                        Example: --xdebug-mode=profile
                        Default: debug,develop

                --server[=[HOST:]PORT]

                        Run a PHP built-in web server on the specified port. 
                        If no port is provided, 8888 is used.
                        Note: Only for development and debugging purposes!
                        Default: 0.0.0.0:8888

                --quiet, -q

                    Do not print any output by default. Use this option in case that listeners
                    produce output and you don't want to mix it up with default response
                    output.

                    Note: Only standard response output is suppressed. Errors and other messages
                    are still printed to stderr and whatever other modules print to stdout.

            event

                The event type in the format of an URI. Example: example.org:api:myEvent
                See Zolinga WIKI's "Zolinga Explorer/Events" article for the list of supported event types.

            params

                Parameters to the event. The parameters can be in JSON format or in Javascript dot notation.

                - JSON formatted parameter to the event starts with '{' e.g. '{"system":{"db":{"password":"123"}}'

                - Parameter in Javascript dot notation to the event starts with '--' e.g. --system.db.password=123

            Example:

                zolinga example.org:api:myEvent --system.db.password=123 --system.db.user=me

                is equivalent to

                zolinga example.org:api:myEvent '{"system":{"db":{"password":"123","user":"me"}}'

                You can chain more events with their parameters.

                zolinga \
                    example.org:api:myEvent '{"system":{"db":{"password":"123","user":"me"}}' \
                    example.org:api:anotherEvent --test.param=123 \
                    example.org:api:yetAnotherEvent;

                Print only response['db']['host'] value from the first event response:

                zolinga --single=response.db.host example.org:db:info

            EOT;

        echo "\n\n";
    }

    /**
     * List of parameters on command line before events
     *
     * @param array<string> $args
     */
    private function parseCliArguments(array $args): void
    {
        array_shift($args); // remove the script name

        $idx = 0;
        foreach ($args as $arg) {
            $argObj = new CliArgument($arg);

            if ($argObj->type == "event") {
                $this->parsedArgs[++$idx] = [
                    'type' => $argObj->value,
                    'params' => [],
                ];
            } else {
                $this->parsedArgs[$idx]['params'] = array_replace_recursive($this->parsedArgs[$idx]['params'], $argObj->value);
            }
        }
    }

    /**
     * Parse the options from the command line.
     * 
     * "Options" are the parameters before the first event.
     *
     * @return void
     */
    public function parseOptions(): void
    {
        // After $this->parseCliArguments() $this->parsedArgs contains options as the first element.               
        $options = array_shift($this->parsedArgs);

        // Check options
        $unaliasedOptions = [];
        foreach ($options['params'] as $k => $v) {
            if (!array_key_exists($k, [...$this->options, ...$this->optionAliases])) {
                throw new \Exception("Unknown option: $k = " . json_encode($v) . ". Supported options: " . json_encode(array_keys($this->options)));
            }
            $unaliasedOptions[isset($this->optionAliases[$k]) ? $this->optionAliases[$k] : $k] = $v;
        }

        $this->options = array_replace_recursive($this->options, $unaliasedOptions);
    }

    /**
     * Dispatch the events.
     *
     * @return void
     */
    private function dispatchEvents(): void
    {
        foreach ($this->parsedArgs as $parsedArgs) {
            $event = new CliRequestResponseEvent($parsedArgs['type'], CliRequestResponseEvent::ORIGIN_CLI, $parsedArgs['params'], []);
            $event->dispatch();
            $this->events[] = $event;
        }
    }
}

// End of file
