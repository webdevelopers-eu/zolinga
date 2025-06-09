<?php
declare(strict_types=1);
namespace Zolinga\System;

// gettext($context . "\004" . $text);
define('GETTEXT_CTX_END', "\004");

/**
 * The root directory of the system. Contains trailing slash removed.
 */
define('Zolinga\System\ROOT_DIR', dirname(__DIR__, 1));
define('Zolinga\System\START_TIME', microtime(true));
define('Zolinga\System\IS_HTTPS', in_array($_SERVER['HTTPS'] ?? '0', ['on', '1']) || ($_SERVER['REQUEST_SCHEME'] ?? '0') === 'https');
define('Zolinga\System\IS_CLI', PHP_SAPI === 'cli');
define('Zolinga\System\IS_INTERACTIVE', (IS_CLI && posix_isatty(STDOUT)) || getenv('INTERACTIVE'));

// Is it a secure connection or local development?
define('Zolinga\System\SECURE_CONNECTION', 
    preg_match('/^((.+\.)?localhost|127\.\d+\.\d+\.\d+)$/', $_SERVER['SERVER_NAME'] ?? '-') 
    || 
    IS_HTTPS
);
