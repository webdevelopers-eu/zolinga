<?php
/**
 * This is a CLI script for the Zolinga system.
 *
 * Syntax: bin/zolinga [OPTIONS] EVENT [ARGUMENTS] EVENT [ARGUMENTS] ...
 *
 * Arguments:
 *
 * EVENT: The event type in the format of an URI. Example: example.org:api:myEvent
 *
 * ARGUMENTS: Parameters to the event. The parameters can be in JSON format or in Javascript dot notation.
 *
 *    - JSON formatted parameter to the event starts with '{' e.g. '{"system":{"db":{"password":"123"}}'
 *
 *    - Parameter in Javascript dot notation to the event starts with '--' e.g. --system.db.password=123
 *
 * Example:
 *
 *     bin/zolinga example.org:api:myEvent --system.db.password=123 --system.db.user=me
 *
 * is equivalent to
 *
 *     bin/zolinga example.org:api:myEvent '{"system":{"db":{"password":"123","user":"me"}}'
 *
 * You can chain more events with their parameters.
 *
 *     bin/zolinga \
 *          example.org:api:myEvent '{"system":{"db":{"password":"123","user":"me"}}' \
 *          example.org:api:anotherEvent --test.param=123 \
 *          example.org:api:yetAnotherEvent;
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-06
 */

declare(strict_types=1);

namespace Zolinga\System\Gates;

// Check that this is a running as a CLI script otherwise exit.
if (php_sapi_name() !== 'cli') {
    file_put_contents("php://stderr", "This script can only be run from the command line.\n");
    exit(1);
}

if (version_compare(phpversion(), '8.2.0', '<')) {
    file_put_contents("php://stderr", "This script requires PHP 8.2 or newer.\n");
    exit(1);
}

// Re-run with debug options?
$xdebugOpts = array_filter(
    $argv,
    fn ($opt) => str_starts_with($opt, '--xdebug')
);
if (count($xdebugOpts)) {
    $xdebugMode = array_reduce(
        $xdebugOpts,
        fn ($mode, $opt) => preg_match('/^--xdebug-mode=(\w+)/', $opt, $matches) ? $matches[1] : $mode,
        'debug,develop,trace'
    );
    // Only profile mode needs restarting PHP
    if (preg_match('/profile/', $xdebugMode) && !preg_match('/profile/', ini_get('xdebug.mode'))) {
        putenv('INTERACTIVE=1'); // Debugging is always interactive, right?
        passthru(
            escapeshellcmd(PHP_BINARY) .
                ' -dxdebug.mode=' . escapeshellarg($xdebugMode) .
                ' -dxdebug.start_with_request=yes' .
                ' ' . implode(' ', $argv),
            $exitCode
        );
        exit($exitCode);
    }
}

require(__DIR__ . '/../system/loader.php');

/** @phpstan-ignore-next-line */
set_error_handler(function ($errNo, $errStr, $errFile, $errLine) {
    $severity = match ($errNo) {
        E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_DEPRECATED, E_DEPRECATED => '🟧 WARNING',
        E_NOTICE, E_USER_NOTICE => '🟦 NOTICE',
        E_STRICT => '🟨 STRICT',
        default => '🟥 ERROR',
    };
    file_put_contents("php://stderr", "$severity: $errStr [" . basename($errFile) . ":$errLine]\n");
});

(new Cli)->run($argv);

// End of file

